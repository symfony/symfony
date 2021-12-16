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

use Symfony\Component\HttpClient\Chunk\ErrorChunk;
use Symfony\Component\HttpClient\Chunk\FirstChunk;
use Symfony\Component\HttpClient\Exception\InvalidArgumentException;
use Symfony\Component\HttpClient\Exception\TransportException;
use Symfony\Component\HttpClient\Internal\ClientState;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * A test-friendly response.
 *
 * @author Nicolas Grekas <p@tchwork.com>
 */
class MockResponse implements ResponseInterface, StreamableInterface
{
    use CommonResponseTrait;
    use TransportResponseTrait {
        doDestruct as public __destruct;
    }

    private $body;
    private $requestOptions = [];
    private $requestUrl;
    private $requestMethod;

    private static $mainMulti;
    private static $idSequence = 0;

    /**
     * @param string|string[]|iterable $body The response body as a string or an iterable of strings,
     *                                       yielding an empty string simulates an idle timeout,
     *                                       throwing an exception yields an ErrorChunk
     *
     * @see ResponseInterface::getInfo() for possible info, e.g. "response_headers"
     */
    public function __construct($body = '', array $info = [])
    {
        $this->body = is_iterable($body) ? $body : (string) $body;
        $this->info = $info + ['http_code' => 200] + $this->info;

        if (!isset($info['response_headers'])) {
            return;
        }

        $responseHeaders = [];

        foreach ($info['response_headers'] as $k => $v) {
            foreach ((array) $v as $v) {
                $responseHeaders[] = (\is_string($k) ? $k.': ' : '').$v;
            }
        }

        $this->info['response_headers'] = [];
        self::addResponseHeaders($responseHeaders, $this->info, $this->headers);
    }

    /**
     * Returns the options used when doing the request.
     */
    public function getRequestOptions(): array
    {
        return $this->requestOptions;
    }

    /**
     * Returns the URL used when doing the request.
     */
    public function getRequestUrl(): string
    {
        return $this->requestUrl;
    }

    /**
     * Returns the method used when doing the request.
     */
    public function getRequestMethod(): string
    {
        return $this->requestMethod;
    }

    /**
     * {@inheritdoc}
     */
    public function getInfo(string $type = null)
    {
        return null !== $type ? $this->info[$type] ?? null : $this->info;
    }

    /**
     * {@inheritdoc}
     */
    public function cancel(): void
    {
        $this->info['canceled'] = true;
        $this->info['error'] = 'Response has been canceled.';
        try {
            $this->body = null;
        } catch (TransportException $e) {
            // ignore errors when canceling
        }
    }

    /**
     * {@inheritdoc}
     */
    protected function close(): void
    {
        $this->inflate = null;
        $this->body = [];
    }

    /**
     * @internal
     */
    public static function fromRequest(string $method, string $url, array $options, ResponseInterface $mock): self
    {
        $response = new self([]);
        $response->requestOptions = $options;
        $response->id = ++self::$idSequence;
        $response->shouldBuffer = $options['buffer'] ?? true;
        $response->initializer = static function (self $response) {
            return \is_array($response->body[0] ?? null);
        };

        $response->info['redirect_count'] = 0;
        $response->info['redirect_url'] = null;
        $response->info['start_time'] = microtime(true);
        $response->info['http_method'] = $method;
        $response->info['http_code'] = 0;
        $response->info['user_data'] = $options['user_data'] ?? null;
        $response->info['url'] = $url;

        if ($mock instanceof self) {
            $mock->requestOptions = $response->requestOptions;
            $mock->requestMethod = $method;
            $mock->requestUrl = $url;
        }

        self::writeRequest($response, $options, $mock);
        $response->body[] = [$options, $mock];

        return $response;
    }

    /**
     * {@inheritdoc}
     */
    protected static function schedule(self $response, array &$runningResponses): void
    {
        if (!$response->id) {
            throw new InvalidArgumentException('MockResponse instances must be issued by MockHttpClient before processing.');
        }

        $multi = self::$mainMulti ?? self::$mainMulti = new ClientState();

        if (!isset($runningResponses[0])) {
            $runningResponses[0] = [$multi, []];
        }

        $runningResponses[0][1][$response->id] = $response;
    }

    /**
     * {@inheritdoc}
     */
    protected static function perform(ClientState $multi, array &$responses): void
    {
        foreach ($responses as $response) {
            $id = $response->id;

            if (null === $response->body) {
                // Canceled response
                $response->body = [];
            } elseif ([] === $response->body) {
                // Error chunk
                $multi->handlesActivity[$id][] = null;
                $multi->handlesActivity[$id][] = null !== $response->info['error'] ? new TransportException($response->info['error']) : null;
            } elseif (null === $chunk = array_shift($response->body)) {
                // Last chunk
                $multi->handlesActivity[$id][] = null;
                $multi->handlesActivity[$id][] = array_shift($response->body);
            } elseif (\is_array($chunk)) {
                // First chunk
                try {
                    $offset = 0;
                    $chunk[1]->getStatusCode();
                    $chunk[1]->getHeaders(false);
                    self::readResponse($response, $chunk[0], $chunk[1], $offset);
                    $multi->handlesActivity[$id][] = new FirstChunk();
                    $buffer = $response->requestOptions['buffer'] ?? null;

                    if ($buffer instanceof \Closure && $response->content = $buffer($response->headers) ?: null) {
                        $response->content = \is_resource($response->content) ? $response->content : fopen('php://temp', 'w+');
                    }
                } catch (\Throwable $e) {
                    $multi->handlesActivity[$id][] = null;
                    $multi->handlesActivity[$id][] = $e;
                }
            } elseif ($chunk instanceof \Throwable) {
                $multi->handlesActivity[$id][] = null;
                $multi->handlesActivity[$id][] = $chunk;
            } else {
                // Data or timeout chunk
                $multi->handlesActivity[$id][] = $chunk;
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    protected static function select(ClientState $multi, float $timeout): int
    {
        return 42;
    }

    /**
     * Simulates sending the request.
     */
    private static function writeRequest(self $response, array $options, ResponseInterface $mock)
    {
        $onProgress = $options['on_progress'] ?? static function () {};
        $response->info += $mock->getInfo() ?: [];

        // simulate "size_upload" if it is set
        if (isset($response->info['size_upload'])) {
            $response->info['size_upload'] = 0.0;
        }

        // simulate "total_time" if it is not set
        if (!isset($response->info['total_time'])) {
            $response->info['total_time'] = microtime(true) - $response->info['start_time'];
        }

        // "notify" DNS resolution
        $onProgress(0, 0, $response->info);

        // consume the request body
        if (\is_resource($body = $options['body'] ?? '')) {
            $data = stream_get_contents($body);
            if (isset($response->info['size_upload'])) {
                $response->info['size_upload'] += \strlen($data);
            }
        } elseif ($body instanceof \Closure) {
            while ('' !== $data = $body(16372)) {
                if (!\is_string($data)) {
                    throw new TransportException(sprintf('Return value of the "body" option callback must be string, "%s" returned.', get_debug_type($data)));
                }

                // "notify" upload progress
                if (isset($response->info['size_upload'])) {
                    $response->info['size_upload'] += \strlen($data);
                }

                $onProgress(0, 0, $response->info);
            }
        }
    }

    /**
     * Simulates reading the response.
     */
    private static function readResponse(self $response, array $options, ResponseInterface $mock, int &$offset)
    {
        $onProgress = $options['on_progress'] ?? static function () {};

        // populate info related to headers
        $info = $mock->getInfo() ?: [];
        $response->info['http_code'] = ($info['http_code'] ?? 0) ?: $mock->getStatusCode() ?: 200;
        $response->addResponseHeaders($info['response_headers'] ?? [], $response->info, $response->headers);
        $dlSize = isset($response->headers['content-encoding']) || 'HEAD' === $response->info['http_method'] || \in_array($response->info['http_code'], [204, 304], true) ? 0 : (int) ($response->headers['content-length'][0] ?? 0);

        $response->info = [
            'start_time' => $response->info['start_time'],
            'user_data' => $response->info['user_data'],
            'http_code' => $response->info['http_code'],
        ] + $info + $response->info;

        if (null !== $response->info['error']) {
            throw new TransportException($response->info['error']);
        }

        if (!isset($response->info['total_time'])) {
            $response->info['total_time'] = microtime(true) - $response->info['start_time'];
        }

        // "notify" headers arrival
        $onProgress(0, $dlSize, $response->info);

        // cast response body to activity list
        $body = $mock instanceof self ? $mock->body : $mock->getContent(false);

        if (!\is_string($body)) {
            try {
                foreach ($body as $chunk) {
                    if ('' === $chunk = (string) $chunk) {
                        // simulate an idle timeout
                        $response->body[] = new ErrorChunk($offset, sprintf('Idle timeout reached for "%s".', $response->info['url']));
                    } else {
                        $response->body[] = $chunk;
                        $offset += \strlen($chunk);
                        // "notify" download progress
                        $onProgress($offset, $dlSize, $response->info);
                    }
                }
            } catch (\Throwable $e) {
                $response->body[] = $e;
            }
        } elseif ('' !== $body) {
            $response->body[] = $body;
            $offset = \strlen($body);
        }

        if (!isset($response->info['total_time'])) {
            $response->info['total_time'] = microtime(true) - $response->info['start_time'];
        }

        // "notify" completion
        $onProgress($offset, $dlSize, $response->info);

        if ($dlSize && $offset !== $dlSize) {
            throw new TransportException(sprintf('Transfer closed with %d bytes remaining to read.', $dlSize - $offset));
        }
    }
}
