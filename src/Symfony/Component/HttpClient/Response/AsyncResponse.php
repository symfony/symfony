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
use Symfony\Component\HttpClient\Chunk\LastChunk;
use Symfony\Component\HttpClient\Exception\TransportException;
use Symfony\Contracts\HttpClient\ChunkInterface;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * Provides a single extension point to process a response's content stream.
 *
 * @author Nicolas Grekas <p@tchwork.com>
 */
final class AsyncResponse implements ResponseInterface
{
    use CommonResponseTrait;

    private $client;
    private $response;
    private $info = ['canceled' => false];
    private $passthru;

    /**
     * @param callable(ChunkInterface, AsyncContext): ?\Iterator $passthru
     */
    public function __construct(HttpClientInterface $client, string $method, string $url, array $options, callable $passthru)
    {
        $this->client = $client;
        $this->shouldBuffer = $options['buffer'] ?? true;
        $this->response = $client->request($method, $url, ['buffer' => false] + $options);
        $this->passthru = $passthru;
        $this->initializer = static function (self $response) {
            return null !== $response->shouldBuffer;
        };
        if (\array_key_exists('user_data', $options)) {
            $this->info['user_data'] = $options['user_data'];
        }
    }

    public function getStatusCode(): int
    {
        if ($this->initializer) {
            self::initialize($this);
        }

        return $this->response->getStatusCode();
    }

    public function getHeaders(bool $throw = true): array
    {
        if ($this->initializer) {
            self::initialize($this);
        }

        $headers = $this->response->getHeaders(false);

        if ($throw) {
            $this->checkStatusCode($this->getInfo('http_code'));
        }

        return $headers;
    }

    public function getInfo(string $type = null)
    {
        if (null !== $type) {
            return $this->info[$type] ?? $this->response->getInfo($type);
        }

        return $this->info + $this->response->getInfo();
    }

    /**
     * {@inheritdoc}
     */
    public function toStream(bool $throw = true)
    {
        if ($throw) {
            // Ensure headers arrived
            $this->getHeaders(true);
        }

        $handle = function () {
            $stream = StreamWrapper::createResource($this->response);

            return stream_get_meta_data($stream)['wrapper_data']->stream_cast(STREAM_CAST_FOR_SELECT);
        };

        $stream = StreamWrapper::createResource($this);
        stream_get_meta_data($stream)['wrapper_data']
            ->bindHandles($handle, $this->content);

        return $stream;
    }

    /**
     * {@inheritdoc}
     */
    public function cancel(): void
    {
        if ($this->info['canceled']) {
            return;
        }

        $this->info['canceled'] = true;
        $this->info['error'] = 'Response has been canceled.';
        $this->close();
        $client = $this->client;
        $this->client = null;

        if (!$this->passthru) {
            return;
        }

        $context = new AsyncContext($this->passthru, $client, $this->response, $this->info, $this->content, $this->offset);
        if (null === $stream = ($this->passthru)(new LastChunk(), $context)) {
            return;
        }

        if (!$stream instanceof \Iterator) {
            throw new \LogicException(sprintf('A chunk passthru must return an "Iterator", "%s" returned.', get_debug_type($stream)));
        }

        try {
            foreach ($stream as $chunk) {
                if ($chunk->isLast()) {
                    break;
                }
            }

            $stream->next();

            if ($stream->valid()) {
                throw new \LogicException('A chunk passthru cannot yield after the last chunk.');
            }

            $stream = $this->passthru = null;
        } catch (ExceptionInterface $e) {
            // ignore any errors when canceling
        }
    }

    /**
     * @internal
     */
    public static function stream(iterable $responses, float $timeout = null, string $class = null): \Generator
    {
        while ($responses) {
            $wrappedResponses = [];
            $asyncMap = new \SplObjectStorage();
            $client = null;

            foreach ($responses as $r) {
                if (!$r instanceof self) {
                    throw new \TypeError(sprintf('"%s::stream()" expects parameter 1 to be an iterable of AsyncResponse objects, "%s" given.', $class ?? static::class, get_debug_type($r)));
                }

                if (null !== $e = $r->info['error'] ?? null) {
                    yield $r => $chunk = new ErrorChunk($r->offset, new TransportException($e));
                    $chunk->didThrow() ?: $chunk->getContent();
                    continue;
                }

                if (null === $client) {
                    $client = $r->client;
                } elseif ($r->client !== $client) {
                    throw new TransportException('Cannot stream AsyncResponse objects with many clients.');
                }

                $asyncMap[$r->response] = $r;
                $wrappedResponses[] = $r->response;
            }

            if (!$client) {
                return;
            }

            foreach ($client->stream($wrappedResponses, $timeout) as $response => $chunk) {
                $r = $asyncMap[$response];

                if (!$r->passthru) {
                    if (null !== $chunk->getError() || $chunk->isLast()) {
                        unset($asyncMap[$response]);
                    }

                    yield $r => $chunk;
                    continue;
                }

                $context = new AsyncContext($r->passthru, $r->client, $r->response, $r->info, $r->content, $r->offset);
                if (null === $stream = ($r->passthru)($chunk, $context)) {
                    if ($r->response === $response && (null !== $chunk->getError() || $chunk->isLast())) {
                        throw new \LogicException('A chunk passthru cannot swallow the last chunk.');
                    }

                    continue;
                }
                $chunk = null;

                if (!$stream instanceof \Iterator) {
                    throw new \LogicException(sprintf('A chunk passthru must return an "Iterator", "%s" returned.', get_debug_type($stream)));
                }

                while (true) {
                    try {
                        if (null !== $chunk) {
                            $stream->next();
                        }

                        if (!$stream->valid()) {
                            break;
                        }
                    } catch (\Throwable $e) {
                        $r->info['error'] = $e->getMessage();
                        $r->response->cancel();

                        yield $r => $chunk = new ErrorChunk($r->offset, $e);
                        $chunk->didThrow() ?: $chunk->getContent();
                        unset($asyncMap[$response]);
                        break;
                    }

                    $chunk = $stream->current();

                    if (!$chunk instanceof ChunkInterface) {
                        throw new \LogicException(sprintf('A chunk passthru must yield instances of "%s", "%s" yielded.', ChunkInterface::class, get_debug_type($chunk)));
                    }

                    if (null !== $chunk->getError()) {
                        // no-op
                    } elseif ($chunk->isFirst()) {
                        $e = $r->openBuffer();

                        yield $r => $chunk;

                        if (null === $e) {
                            continue;
                        }

                        $r->response->cancel();
                        $chunk = new ErrorChunk($r->offset, $e);
                    } elseif ('' !== $content = $chunk->getContent()) {
                        if (null !== $r->shouldBuffer) {
                            throw new \LogicException('A chunk passthru must yield an "isFirst()" chunk before any content chunk.');
                        }

                        if (null !== $r->content && \strlen($content) !== fwrite($r->content, $content)) {
                            $chunk = new ErrorChunk($r->offset, new TransportException(sprintf('Failed writing %d bytes to the response buffer.', \strlen($content))));
                            $r->info['error'] = $chunk->getError();
                            $r->response->cancel();
                        }
                    }

                    if (null === $chunk->getError()) {
                        $r->offset += \strlen($content);

                        yield $r => $chunk;

                        if (!$chunk->isLast()) {
                            continue;
                        }

                        $stream->next();

                        if ($stream->valid()) {
                            throw new \LogicException('A chunk passthru cannot yield after an "isLast()" chunk.');
                        }

                        $r->passthru = null;
                    } else {
                        if ($chunk instanceof ErrorChunk) {
                            $chunk->didThrow(false);
                        } else {
                            try {
                                $chunk = new ErrorChunk($chunk->getOffset(), !$chunk->isTimeout() ?: $chunk->getError());
                            } catch (TransportExceptionInterface $e) {
                                $chunk = new ErrorChunk($chunk->getOffset(), $e);
                            }
                        }

                        yield $r => $chunk;
                        $chunk->didThrow() ?: $chunk->getContent();
                    }

                    unset($asyncMap[$response]);
                    break;
                }

                $stream = $context = null;

                if ($r->response !== $response && isset($asyncMap[$response])) {
                    break;
                }
            }

            if (null === $chunk->getError() && !$chunk->isLast() && $r->response === $response && null !== $r->client) {
                throw new \LogicException('A chunk passthru must yield an "isLast()" chunk before ending a stream.');
            }

            $responses = [];
            foreach ($asyncMap as $response) {
                $r = $asyncMap[$response];

                if (null !== $r->client) {
                    $responses[] = $asyncMap[$response];
                }
            }
        }
    }

    private function openBuffer(): ?\Throwable
    {
        if (null === $shouldBuffer = $this->shouldBuffer) {
            throw new \LogicException('A chunk passthru cannot yield more than one "isFirst()" chunk.');
        }

        $e = $this->shouldBuffer = null;

        if ($shouldBuffer instanceof \Closure) {
            try {
                $shouldBuffer = $shouldBuffer($this->getHeaders(false));

                if (null !== $e = $this->response->getInfo('error')) {
                    throw new TransportException($e);
                }
            } catch (\Throwable $e) {
                $this->info['error'] = $e->getMessage();
                $this->response->cancel();
            }
        }

        if (true === $shouldBuffer) {
            $this->content = fopen('php://temp', 'w+');
        } elseif (\is_resource($shouldBuffer)) {
            $this->content = $shouldBuffer;
        }

        return $e;
    }

    private function close(): void
    {
        $this->response->cancel();
    }
}
