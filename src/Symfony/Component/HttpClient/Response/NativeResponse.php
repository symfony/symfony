<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpClient\Response;

use Psr\Log\LoggerInterface;
use Symfony\Component\HttpClient\Chunk\FirstChunk;
use Symfony\Component\HttpClient\Exception\TransportException;
use Symfony\Component\HttpClient\Internal\ClientState;
use Symfony\Component\HttpClient\Internal\NativeClientState;
use Symfony\Contracts\HttpClient\Exception\HttpExceptionInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * @author Nicolas Grekas <p@tchwork.com>
 *
 * @internal
 */
final class NativeResponse implements ResponseInterface
{
    use ResponseTrait;

    private $context;
    private $url;
    private $resolveRedirect;
    private $onProgress;
    private $remaining;
    private $buffer;
    private $multi;
    private $debugBuffer;
    private $shouldBuffer;

    /**
     * @internal
     */
    public function __construct(NativeClientState $multi, $context, string $url, array $options, array &$info, callable $resolveRedirect, ?callable $onProgress, ?LoggerInterface $logger)
    {
        $this->multi = $multi;
        $this->id = (int) $context;
        $this->context = $context;
        $this->url = $url;
        $this->logger = $logger;
        $this->timeout = $options['timeout'];
        $this->info = &$info;
        $this->resolveRedirect = $resolveRedirect;
        $this->onProgress = $onProgress;
        $this->inflate = !isset($options['normalized_headers']['accept-encoding']);
        $this->shouldBuffer = $options['buffer'] ?? true;

        // Temporary resource to dechunk the response stream
        $this->buffer = fopen('php://temp', 'w+');

        $info['user_data'] = $options['user_data'];
        ++$multi->responseCount;

        $this->initializer = static function (self $response) {
            return null === $response->remaining;
        };
    }

    /**
     * {@inheritdoc}
     */
    public function getInfo(string $type = null)
    {
        if (!$info = $this->finalInfo) {
            $info = $this->info;
            $info['url'] = implode('', $info['url']);
            unset($info['size_body'], $info['request_header']);

            if (null === $this->buffer) {
                $this->finalInfo = $info;
            }
        }

        return null !== $type ? $info[$type] ?? null : $info;
    }

    public function __destruct()
    {
        try {
            $e = null;
            $this->doDestruct();
        } catch (HttpExceptionInterface $e) {
            throw $e;
        } finally {
            if ($e ?? false) {
                throw $e;
            }

            $this->close();

            // Clear the DNS cache when all requests completed
            if (0 >= --$this->multi->responseCount) {
                $this->multi->responseCount = 0;
                $this->multi->dnsCache = [];
            }
        }
    }

    private function open(): void
    {
        $url = $this->url;

        set_error_handler(function ($type, $msg) use (&$url) {
            if (E_NOTICE !== $type || 'fopen(): Content-type not specified assuming application/x-www-form-urlencoded' !== $msg) {
                throw new TransportException($msg);
            }

            $this->logger && $this->logger->info(sprintf('%s for "%s".', $msg, $url ?? $this->url));
        });

        try {
            $this->info['start_time'] = microtime(true);

            while (true) {
                $context = stream_context_get_options($this->context);

                if ($proxy = $context['http']['proxy'] ?? null) {
                    $this->info['debug'] .= "* Establish HTTP proxy tunnel to {$proxy}\n";
                    $this->info['request_header'] = $url;
                } else {
                    $this->info['debug'] .= "*   Trying {$this->info['primary_ip']}...\n";
                    $this->info['request_header'] = $this->info['url']['path'].$this->info['url']['query'];
                }

                $this->info['request_header'] = sprintf("> %s %s HTTP/%s \r\n", $context['http']['method'], $this->info['request_header'], $context['http']['protocol_version']);
                $this->info['request_header'] .= implode("\r\n", $context['http']['header'])."\r\n\r\n";

                // Send request and follow redirects when needed
                $this->handle = $h = fopen($url, 'r', false, $this->context);
                self::addResponseHeaders($http_response_header, $this->info, $this->headers, $this->info['debug']);
                $url = ($this->resolveRedirect)($this->multi, $this->headers['location'][0] ?? null, $this->context);

                if (null === $url) {
                    break;
                }

                $this->logger && $this->logger->info(sprintf('Redirecting: "%s %s"', $this->info['http_code'], $url ?? $this->url));
            }
        } catch (\Throwable $e) {
            $this->close();
            $this->multi->handlesActivity[$this->id][] = null;
            $this->multi->handlesActivity[$this->id][] = $e;

            return;
        } finally {
            $this->info['pretransfer_time'] = $this->info['total_time'] = microtime(true) - $this->info['start_time'];
            restore_error_handler();
        }

        if (isset($context['ssl']['capture_peer_cert_chain']) && isset(($context = stream_context_get_options($this->context))['ssl']['peer_certificate_chain'])) {
            $this->info['peer_certificate_chain'] = $context['ssl']['peer_certificate_chain'];
        }

        stream_set_blocking($h, false);
        $this->context = $this->resolveRedirect = null;

        // Create dechunk buffers
        if (isset($this->headers['content-length'])) {
            $this->remaining = (int) $this->headers['content-length'][0];
        } elseif ('chunked' === ($this->headers['transfer-encoding'][0] ?? null)) {
            stream_filter_append($this->buffer, 'dechunk', STREAM_FILTER_WRITE);
            $this->remaining = -1;
        } else {
            $this->remaining = -2;
        }

        $this->multi->handlesActivity[$this->id] = [new FirstChunk()];

        if ('HEAD' === $context['http']['method'] || \in_array($this->info['http_code'], [204, 304], true)) {
            $this->multi->handlesActivity[$this->id][] = null;
            $this->multi->handlesActivity[$this->id][] = null;

            return;
        }

        $this->multi->openHandles[$this->id] = [$h, $this->buffer, $this->onProgress, &$this->remaining, &$this->info];
    }

    /**
     * {@inheritdoc}
     */
    private function close(): void
    {
        unset($this->multi->openHandles[$this->id], $this->multi->handlesActivity[$this->id]);
        $this->handle = $this->buffer = $this->inflate = $this->onProgress = null;
    }

    /**
     * {@inheritdoc}
     */
    private static function schedule(self $response, array &$runningResponses): void
    {
        if (!isset($runningResponses[$i = $response->multi->id])) {
            $runningResponses[$i] = [$response->multi, []];
        }

        $runningResponses[$i][1][$response->id] = $response;

        if (null === $response->buffer) {
            // Response already completed
            $response->multi->handlesActivity[$response->id][] = null;
            $response->multi->handlesActivity[$response->id][] = null !== $response->info['error'] ? new TransportException($response->info['error']) : null;
        }
    }

    /**
     * {@inheritdoc}
     *
     * @param NativeClientState $multi
     */
    private static function perform(ClientState $multi, array &$responses = null): void
    {
        foreach ($multi->openHandles as $i => [$h, $buffer, $onProgress]) {
            $hasActivity = false;
            $remaining = &$multi->openHandles[$i][3];
            $info = &$multi->openHandles[$i][4];
            $e = null;

            // Read incoming buffer and write it to the dechunk one
            try {
                if ($remaining && '' !== $data = (string) fread($h, 0 > $remaining ? 16372 : $remaining)) {
                    fwrite($buffer, $data);
                    $hasActivity = true;
                    $multi->sleep = false;

                    if (-1 !== $remaining) {
                        $remaining -= \strlen($data);
                    }
                }
            } catch (\Throwable $e) {
                $hasActivity = $onProgress = false;
            }

            if (!$hasActivity) {
                if ($onProgress) {
                    try {
                        // Notify the progress callback so that it can e.g. cancel
                        // the request if the stream is inactive for too long
                        $info['total_time'] = microtime(true) - $info['start_time'];
                        $onProgress();
                    } catch (\Throwable $e) {
                        // no-op
                    }
                }
            } elseif ('' !== $data = stream_get_contents($buffer, -1, 0)) {
                rewind($buffer);
                ftruncate($buffer, 0);

                if (null === $e) {
                    $multi->handlesActivity[$i][] = $data;
                }
            }

            if (null !== $e || !$remaining || feof($h)) {
                // Stream completed
                $info['total_time'] = microtime(true) - $info['start_time'];
                $info['starttransfer_time'] = $info['starttransfer_time'] ?: $info['total_time'];

                if ($onProgress) {
                    try {
                        $onProgress(-1);
                    } catch (\Throwable $e) {
                        // no-op
                    }
                }

                if (null === $e) {
                    if (0 < $remaining) {
                        $e = new TransportException(sprintf('Transfer closed with %s bytes remaining to read.', $remaining));
                    } elseif (-1 === $remaining && fwrite($buffer, '-') && '' !== stream_get_contents($buffer, -1, 0)) {
                        $e = new TransportException('Transfer closed with outstanding data remaining from chunked response.');
                    }
                }

                $multi->handlesActivity[$i][] = null;
                $multi->handlesActivity[$i][] = $e;
                unset($multi->openHandles[$i]);
                $multi->sleep = false;
            }
        }

        if (null === $responses) {
            return;
        }

        // Create empty activity lists to tell ResponseTrait::stream() we still have pending requests
        foreach ($responses as $i => $response) {
            if (null === $response->remaining && null !== $response->buffer) {
                $multi->handlesActivity[$i] = [];
            }
        }

        if (\count($multi->openHandles) >= $multi->maxHostConnections) {
            return;
        }

        // Open the next pending request - this is a blocking operation so we do only one of them
        foreach ($responses as $i => $response) {
            if (null === $response->remaining && null !== $response->buffer) {
                $response->open();
                $multi->sleep = false;
                self::perform($multi);

                break;
            }
        }
    }

    /**
     * {@inheritdoc}
     *
     * @param NativeClientState $multi
     */
    private static function select(ClientState $multi, float $timeout): int
    {
        $_ = [];
        $handles = array_column($multi->openHandles, 0);

        return (!$multi->sleep = !$multi->sleep) ? -1 : stream_select($handles, $_, $_, (int) $timeout, (int) (1E6 * ($timeout - (int) $timeout)));
    }
}
