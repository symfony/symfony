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
use Symfony\Component\HttpClient\Exception\TransportException;
use Symfony\Component\HttpClient\Internal\QueueingClientState;
use Symfony\Contracts\HttpClient\ChunkInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * @internal
 */
final class QueuedResponse implements ResponseInterface, StreamableInterface
{
    use CommonResponseTrait;

    private static ?\Closure $initializerClosure = null;

    private ?ResponseInterface $response = null;
    private bool $complete = false;
    private array $chunks = [];
    /** @var resource|null */
    private $backlog;
    private array $info = [
        'canceled' => false,
        'error' => null,
        'http_code' => 0,
        'redirect_count' => 0,
        'redirect_url' => null,
        'response_headers' => [],
        'start_time' => 0.0,
    ];
    private float $timeout;
    private array $headers = [];

    public function __construct(
        private QueueingClientState $state,
        private HttpClientInterface $client,
        private string $method,
        private string $url,
        private array $options,
    ) {
        $this->shouldBuffer = $options['buffer'] ?? true;
        $this->timeout = (float) ($options['timeout'] ?? \ini_get('default_socket_timeout'));

        if (0 > $this->timeout) {
            $this->timeout = 172800.0;
        }

        $this->initializer = self::$initializerClosure ??= static fn (self $response) => null !== $response->shouldBuffer;
    }

    public function getClient(): HttpClientInterface
    {
        return $this->client;
    }

    public function start(): ResponseInterface
    {
        if (null !== $this->response) {
            return $this->response;
        }

        try {
            return $this->response = $this->client->request($this->method, $this->url, ['buffer' => false] + $this->options);
        } finally {
            $this->clearOptions();
        }
    }

    public function getResponse(): ?ResponseInterface
    {
        return $this->response;
    }

    public function getOffset(): int
    {
        return $this->offset;
    }

    public function getTimeout(): float
    {
        return $this->timeout;
    }

    public function isComplete(): bool
    {
        return $this->complete;
    }

    public function complete(): void
    {
        if (null !== $this->response) {
            $this->info = $this->getInfo();
            unset($this->info['pause_handler']);

            foreach ($this->info['response_headers'] as $header) {
                if (preg_match('#^HTTP/\d+(?:\.\d+)? \d\d\d(?: |$)#', $header)) {
                    $this->headers = [];
                } elseif (2 === \count($parts = explode(':', $header, 2))) {
                    $this->headers[strtolower($parts[0])][] = ltrim($parts[1]);
                }
            }

            $this->response = null;
        }

        $this->complete = true;
    }

    public function fail(\Throwable $error): void
    {
        $this->info['error'] = $error->getMessage();
        $this->complete = true;
        $this->state->release($this);
    }

    public function getStatusCode(): int
    {
        if ($this->initializer) {
            self::initialize($this);
        }

        return $this->getInfo('http_code');
    }

    public function getHeaders(bool $throw = true): array
    {
        if ($this->initializer) {
            self::initialize($this);
        }

        if ($throw) {
            $this->checkStatusCode();
        }

        return $this->response?->getHeaders(false) ?? $this->headers;
    }

    public function getInfo(?string $type = null): mixed
    {
        if (null !== $type) {
            if ('error' === $type && null !== $this->info['error']) {
                return $this->info['error'];
            }
            if ('canceled' === $type && $this->info['canceled']) {
                return true;
            }

            return $this->response?->getInfo($type) ?? $this->info[$type] ?? match ($type) {
                'http_method' => $this->method,
                'url' => $this->url,
                'user_data' => $this->options['user_data'] ?? null,
                'max_duration' => $this->options['max_duration'] ?? 0,
                default => null,
            };
        }

        $info = ($this->response?->getInfo() ?? []) + $this->info + [
            'http_method' => $this->method,
            'url' => $this->url,
            'user_data' => $this->options['user_data'] ?? null,
            'max_duration' => $this->options['max_duration'] ?? 0,
        ];

        if (null !== $this->info['error']) {
            $info['error'] = $this->info['error'];
        }
        if ($this->info['canceled']) {
            $info['canceled'] = true;
        }

        return $info;
    }

    public function toStream(bool $throw = true)
    {
        if ($throw) {
            $this->getHeaders(true);
        }

        $handle = function () {
            $this->getHeaders(false);

            if (null === $this->response) {
                return $this->content ?? $this->backlog ?? false;
            }

            $stream = $this->response instanceof StreamableInterface ? $this->response->toStream(false) : StreamWrapper::createResource($this->response);

            return stream_get_meta_data($stream)['wrapper_data']->stream_cast(\STREAM_CAST_FOR_SELECT);
        };

        $stream = StreamWrapper::createResource($this);
        stream_get_meta_data($stream)['wrapper_data']->bindHandles($handle, $this->content);

        return $stream;
    }

    public function cancel(): void
    {
        $this->info['canceled'] = true;
        $this->info['error'] = 'Response has been canceled.';
        $this->close();
    }

    public function push(ChunkInterface $chunk): void
    {
        if (null !== $chunk->getError()) {
            try {
                $chunk->isTimeout();
                $error = $chunk->getError();
            } catch (TransportExceptionInterface $error) {
                $this->info['error'] = $error->getMessage();
            }

            $this->chunks[] = ['offset' => $chunk->getOffset(), 'error' => $error];

            return;
        }

        if ('' === $content = $chunk->getContent()) {
            $this->chunks[] = $chunk;

            return;
        }

        $this->backlog ??= fopen('php://temp/maxmemory:0', 'w+');
        fseek($this->backlog, 0, \SEEK_END);
        $position = ftell($this->backlog);

        if (\strlen($content) !== fwrite($this->backlog, $content)) {
            throw new TransportException('Failed writing to the response buffer.');
        }

        $last = array_key_last($this->chunks);

        if (null !== $last && \is_array($this->chunks[$last]) && isset($this->chunks[$last]['position'])) {
            $this->chunks[$last]['length'] += \strlen($content);
        } else {
            $this->chunks[] = ['offset' => $chunk->getOffset(), 'position' => $position, 'length' => \strlen($content)];
        }
    }

    public function shift(): ?ChunkInterface
    {
        if (!$this->chunks) {
            return null;
        }

        $chunk = $this->chunks[0];

        if ($chunk instanceof ChunkInterface) {
            array_shift($this->chunks);

            if (!$this->chunks) {
                $this->backlog = null;
            }

            return $chunk;
        }

        if (isset($chunk['error'])) {
            array_shift($this->chunks);

            if (!$this->chunks) {
                $this->backlog = null;
            }

            return new ErrorChunk($chunk['offset'], $chunk['error']);
        }

        fseek($this->backlog, $chunk['position']);
        $content = fread($this->backlog, min(16384, $chunk['length']));

        if (false === $content || '' === $content) {
            throw new TransportException('Failed reading from the response buffer.');
        }

        $this->chunks[0]['offset'] += \strlen($content);
        $this->chunks[0]['position'] += \strlen($content);
        $this->chunks[0]['length'] -= \strlen($content);

        if (0 === $this->chunks[0]['length']) {
            array_shift($this->chunks);

            if (!$this->chunks) {
                $this->backlog = null;
            }
        }

        return new DataChunk($chunk['offset'], $content);
    }

    public function __destruct()
    {
        $this->shouldBuffer = true;

        if ($this->initializer && null === $this->getInfo('error')) {
            self::initialize($this);
            $this->checkStatusCode();
        }
    }

    public static function stream(iterable $responses, ?float $timeout = null): \Generator
    {
        $state = null;
        $pending = [];

        foreach ($responses as $response) {
            if (!$response instanceof self) {
                throw new \TypeError(\sprintf('"%s::stream()" expects an iterable of QueuedResponse objects, "%s" given.', self::class, get_debug_type($response)));
            }
            if (null !== $state && $state !== $response->state) {
                throw new TransportException('Cannot stream queued responses from different clients.');
            }

            $state = $response->state;
            $pending[] = $response;
        }

        if (null === $state) {
            return;
        }

        $stream = $state->stream($pending, $timeout);
        unset($pending, $responses);

        foreach ($stream as $response => $chunk) {
            if (null !== $chunk->getError()) {
                try {
                    $chunk->isTimeout();
                    $chunk = new ErrorChunk($response->offset, $chunk->getError());
                } catch (TransportExceptionInterface $e) {
                    $response->info['error'] = $e->getMessage();
                    $chunk = new ErrorChunk($response->offset, $e);
                }

                yield $response => $chunk;

                if (!$chunk->didThrow()) {
                    $chunk->getContent();
                }

                continue;
            }

            if ($chunk->isFirst()) {
                $error = null;

                try {
                    $response->response?->getStatusCode();

                    if ($response->shouldBuffer instanceof \Closure) {
                        $response->shouldBuffer = ($response->shouldBuffer)($response->response?->getHeaders(false) ?? $response->headers);
                    }

                    if (null !== $message = $response->getInfo('error')) {
                        throw new TransportException($message);
                    }

                    if (true === $response->shouldBuffer) {
                        $response->content = fopen('php://temp', 'w+');
                    } elseif (\is_resource($response->shouldBuffer)) {
                        $response->content = $response->shouldBuffer;
                    }
                } catch (\Throwable $error) {
                    $response->info['error'] = $error->getMessage();
                    $response->close();
                }

                $response->shouldBuffer = null;

                yield $response => $chunk;

                if (null !== $error) {
                    yield $response => $chunk = new ErrorChunk($response->offset, $error);
                    $chunk->didThrow() ?: $chunk->getContent();

                    continue;
                }

                if ($response->initializer && null === $response->getInfo('error')) {
                    $response->getHeaders(true);
                }

                continue;
            }

            $content = $chunk->getContent();

            if ('' !== $content && null !== $response->content && \strlen($content) !== fwrite($response->content, $content)) {
                $response->info['error'] = 'Failed writing to the response buffer.';
                $response->close();
                yield $response => $chunk = new ErrorChunk($response->offset, new TransportException($response->info['error']));
                $chunk->didThrow() ?: $chunk->getContent();

                continue;
            }

            $response->offset += \strlen($content);

            if ($chunk->isLast() && 0 === $response->offset && null === $response->content) {
                $response->content = fopen('php://memory', 'w+');
            }

            yield $response => $chunk;
        }
    }

    private function close(): void
    {
        if (!$this->complete) {
            $this->response?->cancel();
        }

        $this->complete = true;
        $this->state->release($this);
        $this->chunks = [];
        $this->backlog = null;
        $this->clearOptions();
    }

    private function clearOptions(): void
    {
        foreach (['user_data', 'max_duration'] as $key) {
            if (isset($this->options[$key])) {
                $this->info[$key] = $this->options[$key];
            }
        }

        $this->options = [];
    }
}
