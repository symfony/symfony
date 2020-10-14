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

use Symfony\Component\HttpClient\Chunk\DataChunk;
use Symfony\Component\HttpClient\Chunk\ErrorChunk;
use Symfony\Component\HttpClient\Chunk\FirstChunk;
use Symfony\Component\HttpClient\Chunk\LastChunk;
use Symfony\Component\HttpClient\Exception\ClientException;
use Symfony\Component\HttpClient\Exception\JsonException;
use Symfony\Component\HttpClient\Exception\RedirectionException;
use Symfony\Component\HttpClient\Exception\ServerException;
use Symfony\Component\HttpClient\Exception\TransportException;
use Symfony\Component\HttpClient\Internal\ClientState;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;

/**
 * Implements the common logic for response classes.
 *
 * @author Nicolas Grekas <p@tchwork.com>
 *
 * @internal
 */
trait ResponseTrait
{
    private $logger;
    private $headers = [];
    private $canary;

    /**
     * @var callable|null A callback that initializes the two previous properties
     */
    private $initializer;

    private $info = [
        'response_headers' => [],
        'http_code' => 0,
        'error' => null,
        'canceled' => false,
    ];

    /** @var object|resource */
    private $handle;
    private $id;
    private $timeout = 0;
    private $inflate;
    private $shouldBuffer;
    private $content;
    private $finalInfo;
    private $offset = 0;
    private $jsonData;

    /**
     * {@inheritdoc}
     */
    public function getStatusCode(): int
    {
        if ($this->initializer) {
            self::initialize($this);
        }

        return $this->info['http_code'];
    }

    /**
     * {@inheritdoc}
     */
    public function getHeaders(bool $throw = true): array
    {
        if ($this->initializer) {
            self::initialize($this);
        }

        if ($throw) {
            $this->checkStatusCode();
        }

        return $this->headers;
    }

    /**
     * {@inheritdoc}
     */
    public function getContent(bool $throw = true): string
    {
        if ($this->initializer) {
            self::initialize($this);
        }

        if ($throw) {
            $this->checkStatusCode();
        }

        if (null === $this->content) {
            $content = null;

            foreach (self::stream([$this]) as $chunk) {
                if (!$chunk->isLast()) {
                    $content .= $chunk->getContent();
                }
            }

            if (null !== $content) {
                return $content;
            }

            if (null === $this->content) {
                throw new TransportException('Cannot get the content of the response twice: buffering is disabled.');
            }
        } else {
            foreach (self::stream([$this]) as $chunk) {
                // Chunks are buffered in $this->content already
            }
        }

        rewind($this->content);

        return stream_get_contents($this->content);
    }

    /**
     * {@inheritdoc}
     */
    public function toArray(bool $throw = true): array
    {
        if ('' === $content = $this->getContent($throw)) {
            throw new JsonException('Response body is empty.');
        }

        if (null !== $this->jsonData) {
            return $this->jsonData;
        }

        try {
            $content = json_decode($content, true, 512, \JSON_BIGINT_AS_STRING | (\PHP_VERSION_ID >= 70300 ? \JSON_THROW_ON_ERROR : 0));
        } catch (\JsonException $e) {
            throw new JsonException($e->getMessage().sprintf(' for "%s".', $this->getInfo('url')), $e->getCode());
        }

        if (\PHP_VERSION_ID < 70300 && \JSON_ERROR_NONE !== json_last_error()) {
            throw new JsonException(json_last_error_msg().sprintf(' for "%s".', $this->getInfo('url')), json_last_error());
        }

        if (!\is_array($content)) {
            throw new JsonException(sprintf('JSON content was expected to decode to an array, "%s" returned for "%s".', get_debug_type($content), $this->getInfo('url')));
        }

        if (null !== $this->content) {
            // Option "buffer" is true
            return $this->jsonData = $content;
        }

        return $content;
    }

    /**
     * {@inheritdoc}
     */
    public function cancel(): void
    {
        $this->info['canceled'] = true;
        $this->info['error'] = 'Response has been canceled.';
        $this->close();
    }

    /**
     * Casts the response to a PHP stream resource.
     *
     * @return resource
     *
     * @throws TransportExceptionInterface   When a network error occurs
     * @throws RedirectionExceptionInterface On a 3xx when $throw is true and the "max_redirects" option has been reached
     * @throws ClientExceptionInterface      On a 4xx when $throw is true
     * @throws ServerExceptionInterface      On a 5xx when $throw is true
     */
    public function toStream(bool $throw = true)
    {
        if ($throw) {
            // Ensure headers arrived
            $this->getHeaders($throw);
        }

        $stream = StreamWrapper::createResource($this);
        stream_get_meta_data($stream)['wrapper_data']
            ->bindHandles($this->handle, $this->content);

        return $stream;
    }

    /**
     * Closes the response and all its network handles.
     */
    private function close(): void
    {
        $this->canary->cancel();
        $this->inflate = null;
    }

    /**
     * Adds pending responses to the activity list.
     */
    abstract protected static function schedule(self $response, array &$runningResponses): void;

    /**
     * Performs all pending non-blocking operations.
     */
    abstract protected static function perform(ClientState $multi, array &$responses): void;

    /**
     * Waits for network activity.
     */
    abstract protected static function select(ClientState $multi, float $timeout): int;

    private static function initialize(self $response): void
    {
        if (null !== $response->info['error']) {
            throw new TransportException($response->info['error']);
        }

        try {
            if (($response->initializer)($response)) {
                foreach (self::stream([$response]) as $chunk) {
                    if ($chunk->isFirst()) {
                        break;
                    }
                }
            }
        } catch (\Throwable $e) {
            // Persist timeouts thrown during initialization
            $response->info['error'] = $e->getMessage();
            $response->close();
            throw $e;
        }

        $response->initializer = null;
    }

    private static function addResponseHeaders(array $responseHeaders, array &$info, array &$headers, string &$debug = ''): void
    {
        foreach ($responseHeaders as $h) {
            if (11 <= \strlen($h) && '/' === $h[4] && preg_match('#^HTTP/\d+(?:\.\d+)? ([1-9]\d\d)(?: |$)#', $h, $m)) {
                if ($headers) {
                    $debug .= "< \r\n";
                    $headers = [];
                }
                $info['http_code'] = (int) $m[1];
            } elseif (2 === \count($m = explode(':', $h, 2))) {
                $headers[strtolower($m[0])][] = ltrim($m[1]);
            }

            $debug .= "< {$h}\r\n";
            $info['response_headers'][] = $h;
        }

        $debug .= "< \r\n";

        if (!$info['http_code']) {
            throw new TransportException(sprintf('Invalid or missing HTTP status line for "%s".', implode('', $info['url'])));
        }
    }

    private function checkStatusCode()
    {
        if (500 <= $this->info['http_code']) {
            throw new ServerException($this);
        }

        if (400 <= $this->info['http_code']) {
            throw new ClientException($this);
        }

        if (300 <= $this->info['http_code']) {
            throw new RedirectionException($this);
        }
    }

    /**
     * Ensures the request is always sent and that the response code was checked.
     */
    private function doDestruct()
    {
        $this->shouldBuffer = true;

        if ($this->initializer && null === $this->info['error']) {
            self::initialize($this);
            $this->checkStatusCode();
        }
    }

    /**
     * Implements an event loop based on a buffer activity queue.
     *
     * @internal
     */
    public static function stream(iterable $responses, float $timeout = null): \Generator
    {
        $runningResponses = [];

        foreach ($responses as $response) {
            self::schedule($response, $runningResponses);
        }

        $lastActivity = microtime(true);
        $elapsedTimeout = 0;

        while (true) {
            $hasActivity = false;
            $timeoutMax = 0;
            $timeoutMin = $timeout ?? \INF;

            /** @var ClientState $multi */
            foreach ($runningResponses as $i => [$multi]) {
                $responses = &$runningResponses[$i][1];
                self::perform($multi, $responses);

                foreach ($responses as $j => $response) {
                    $timeoutMax = $timeout ?? max($timeoutMax, $response->timeout);
                    $timeoutMin = min($timeoutMin, $response->timeout, 1);
                    $chunk = false;

                    if (isset($multi->handlesActivity[$j])) {
                        // no-op
                    } elseif (!isset($multi->openHandles[$j])) {
                        unset($responses[$j]);
                        continue;
                    } elseif ($elapsedTimeout >= $timeoutMax) {
                        $multi->handlesActivity[$j] = [new ErrorChunk($response->offset, sprintf('Idle timeout reached for "%s".', $response->getInfo('url')))];
                    } else {
                        continue;
                    }

                    while ($multi->handlesActivity[$j] ?? false) {
                        $hasActivity = true;
                        $elapsedTimeout = 0;

                        if (\is_string($chunk = array_shift($multi->handlesActivity[$j]))) {
                            if (null !== $response->inflate && false === $chunk = @inflate_add($response->inflate, $chunk)) {
                                $multi->handlesActivity[$j] = [null, new TransportException(sprintf('Error while processing content unencoding for "%s".', $response->getInfo('url')))];
                                continue;
                            }

                            if ('' !== $chunk && null !== $response->content && \strlen($chunk) !== fwrite($response->content, $chunk)) {
                                $multi->handlesActivity[$j] = [null, new TransportException(sprintf('Failed writing %d bytes to the response buffer.', \strlen($chunk)))];
                                continue;
                            }

                            $chunkLen = \strlen($chunk);
                            $chunk = new DataChunk($response->offset, $chunk);
                            $response->offset += $chunkLen;
                        } elseif (null === $chunk) {
                            $e = $multi->handlesActivity[$j][0];
                            unset($responses[$j], $multi->handlesActivity[$j]);
                            $response->close();

                            if (null !== $e) {
                                $response->info['error'] = $e->getMessage();

                                if ($e instanceof \Error) {
                                    throw $e;
                                }

                                $chunk = new ErrorChunk($response->offset, $e);
                            } else {
                                if (0 === $response->offset && null === $response->content) {
                                    $response->content = fopen('php://memory', 'w+');
                                }

                                $chunk = new LastChunk($response->offset);
                            }
                        } elseif ($chunk instanceof ErrorChunk) {
                            unset($responses[$j]);
                            $elapsedTimeout = $timeoutMax;
                        } elseif ($chunk instanceof FirstChunk) {
                            if ($response->logger) {
                                $info = $response->getInfo();
                                $response->logger->info(sprintf('Response: "%s %s"', $info['http_code'], $info['url']));
                            }

                            $response->inflate = \extension_loaded('zlib') && $response->inflate && 'gzip' === ($response->headers['content-encoding'][0] ?? null) ? inflate_init(\ZLIB_ENCODING_GZIP) : null;

                            if ($response->shouldBuffer instanceof \Closure) {
                                try {
                                    $response->shouldBuffer = ($response->shouldBuffer)($response->headers);

                                    if (null !== $response->info['error']) {
                                        throw new TransportException($response->info['error']);
                                    }
                                } catch (\Throwable $e) {
                                    $response->close();
                                    $multi->handlesActivity[$j] = [null, $e];
                                }
                            }

                            if (true === $response->shouldBuffer) {
                                $response->content = fopen('php://temp', 'w+');
                            } elseif (\is_resource($response->shouldBuffer)) {
                                $response->content = $response->shouldBuffer;
                            }
                            $response->shouldBuffer = null;

                            yield $response => $chunk;

                            if ($response->initializer && null === $response->info['error']) {
                                // Ensure the HTTP status code is always checked
                                $response->getHeaders(true);
                            }

                            continue;
                        }

                        yield $response => $chunk;
                    }

                    unset($multi->handlesActivity[$j]);

                    if ($chunk instanceof ErrorChunk && !$chunk->didThrow()) {
                        // Ensure transport exceptions are always thrown
                        $chunk->getContent();
                    }
                }

                if (!$responses) {
                    unset($runningResponses[$i]);
                }

                // Prevent memory leaks
                $multi->handlesActivity = $multi->handlesActivity ?: [];
                $multi->openHandles = $multi->openHandles ?: [];
            }

            if (!$runningResponses) {
                break;
            }

            if ($hasActivity) {
                $lastActivity = microtime(true);
                continue;
            }

            if (-1 === self::select($multi, min($timeoutMin, $timeoutMax - $elapsedTimeout))) {
                usleep(min(500, 1E6 * $timeoutMin));
            }

            $elapsedTimeout = microtime(true) - $lastActivity;
        }
    }
}
