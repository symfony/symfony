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
use Symfony\Component\HttpClient\Internal\Canary;
use Symfony\Component\HttpClient\Internal\ClientState;
use Symfony\Component\HttpClient\Internal\NativeClientState;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * @author Nicolas Grekas <p@tchwork.com>
 *
 * @internal
 */
final class NativeResponse implements ResponseInterface, StreamableInterface
{
    use CommonResponseTrait;
    use TransportResponseTrait;

    /**
     * @var resource
     */
    private $context;
    private string $url;
    private \Closure $resolver;
    private ?\Closure $onProgress;
    private ?int $remaining = null;

    /**
     * @var resource|null
     */
    private $buffer;

    private NativeClientState $multi;
    private float $pauseExpiry = 0.0;

    /**
     * @internal
     */
    public function __construct(NativeClientState $multi, $context, string $url, array $options, array &$info, callable $resolver, ?callable $onProgress, ?LoggerInterface $logger)
    {
        $this->multi = $multi;
        $this->id = $id = (int) $context;
        $this->context = $context;
        $this->url = $url;
        $this->logger = $logger;
        $this->timeout = $options['timeout'];
        $this->info = &$info;
        $this->resolver = $resolver(...);
        $this->onProgress = $onProgress ? $onProgress(...) : null;
        $this->inflate = !isset($options['normalized_headers']['accept-encoding']);
        $this->shouldBuffer = $options['buffer'] ?? true;

        // Temporary resource to dechunk the response stream
        $this->buffer = fopen('php://temp', 'w+');

        $info['original_url'] = implode('', $info['url']);
        $info['user_data'] = $options['user_data'];
        $info['max_duration'] = $options['max_duration'];
        ++$multi->responseCount;

        $this->initializer = static fn (self $response) => null === $response->remaining;

        $pauseExpiry = &$this->pauseExpiry;
        $info['pause_handler'] = static function (float $duration) use (&$pauseExpiry) {
            $pauseExpiry = 0 < $duration ? hrtime(true) / 1E9 + $duration : 0;
        };

        $this->canary = new Canary(static function () use ($multi, $id) {
            if (null !== ($host = $multi->openHandles[$id][6] ?? null) && 0 >= --$multi->hosts[$host]) {
                unset($multi->hosts[$host]);
            }
            unset($multi->openHandles[$id], $multi->handlesActivity[$id]);
        });
    }

    public function getInfo(?string $type = null): mixed
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
            $this->doDestruct();
        } finally {
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
            if (\E_NOTICE !== $type || 'fopen(): Content-type not specified assuming application/x-www-form-urlencoded' !== $msg) {
                throw new TransportException($msg);
            }

            $this->logger?->info(sprintf('%s for "%s".', $msg, $url ?? $this->url));
        });

        try {
            $this->info['start_time'] = microtime(true);

            [$resolver, $url] = ($this->resolver)($this->multi);

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

                if (\array_key_exists('peer_name', $context['ssl']) && null === $context['ssl']['peer_name']) {
                    unset($context['ssl']['peer_name']);
                    $this->context = stream_context_create([], ['options' => $context] + stream_context_get_params($this->context));
                }

                // Send request and follow redirects when needed
                $this->handle = $h = fopen($url, 'r', false, $this->context);
                self::addResponseHeaders(stream_get_meta_data($h)['wrapper_data'], $this->info, $this->headers, $this->info['debug']);
                $url = $resolver($this->multi, $this->headers['location'][0] ?? null, $this->context);

                if (null === $url) {
                    break;
                }

                $this->logger?->info(sprintf('Redirecting: "%s %s"', $this->info['http_code'], $url ?? $this->url));
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
        unset($this->context, $this->resolver);

        // Create dechunk buffers
        if (isset($this->headers['content-length'])) {
            $this->remaining = (int) $this->headers['content-length'][0];
        } elseif ('chunked' === ($this->headers['transfer-encoding'][0] ?? null)) {
            stream_filter_append($this->buffer, 'dechunk', \STREAM_FILTER_WRITE);
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

        $host = parse_url($this->info['redirect_url'] ?? $this->url, \PHP_URL_HOST);
        $this->multi->lastTimeout = null;
        $this->multi->openHandles[$this->id] = [&$this->pauseExpiry, $h, $this->buffer, $this->onProgress, &$this->remaining, &$this->info, $host];
        $this->multi->hosts[$host] = 1 + ($this->multi->hosts[$host] ?? 0);
    }

    private function close(): void
    {
        $this->canary->cancel();
        $this->handle = $this->buffer = $this->inflate = $this->onProgress = null;
    }

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
     * @param NativeClientState $multi
     */
    private static function perform(ClientState $multi, ?array &$responses = null): void
    {
        foreach ($multi->openHandles as $i => [$pauseExpiry, $h, $buffer, $onProgress]) {
            if ($pauseExpiry) {
                if (hrtime(true) / 1E9 < $pauseExpiry) {
                    continue;
                }

                $multi->openHandles[$i][0] = 0;
            }

            $hasActivity = false;
            $remaining = &$multi->openHandles[$i][4];
            $info = &$multi->openHandles[$i][5];
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
                if (null !== ($host = $multi->openHandles[$i][6] ?? null) && 0 >= --$multi->hosts[$host]) {
                    unset($multi->hosts[$host]);
                }
                unset($multi->openHandles[$i]);
                $multi->sleep = false;
            }
        }

        if (null === $responses) {
            return;
        }

        $maxHosts = $multi->maxHostConnections;

        foreach ($responses as $i => $response) {
            if (null !== $response->remaining || null === $response->buffer) {
                continue;
            }

            if ($response->pauseExpiry && hrtime(true) / 1E9 < $response->pauseExpiry) {
                // Create empty open handles to tell we still have pending requests
                $multi->openHandles[$i] = [\INF, null, null, null];
            } elseif ($maxHosts && $maxHosts > ($multi->hosts[parse_url($response->url, \PHP_URL_HOST)] ?? 0)) {
                // Open the next pending request - this is a blocking operation so we do only one of them
                $response->open();
                $multi->sleep = false;
                self::perform($multi);
                $maxHosts = 0;
            }
        }
    }

    /**
     * @param NativeClientState $multi
     */
    private static function select(ClientState $multi, float $timeout): int
    {
        if (!$multi->sleep = !$multi->sleep) {
            return -1;
        }

        $_ = $handles = [];
        $now = null;

        foreach ($multi->openHandles as [$pauseExpiry, $h]) {
            if (null === $h) {
                continue;
            }

            if ($pauseExpiry && ($now ??= hrtime(true) / 1E9) < $pauseExpiry) {
                $timeout = min($timeout, $pauseExpiry - $now);
                continue;
            }

            $handles[] = $h;
        }

        if (!$handles) {
            usleep((int) (1E6 * $timeout));

            return 0;
        }

        return stream_select($handles, $_, $_, (int) $timeout, (int) (1E6 * ($timeout - (int) $timeout)));
    }
}
