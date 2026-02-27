<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpClient\Internal;

use Amp\ByteStream\ReadableBuffer;
use Amp\ByteStream\ReadableIterableStream;
use Amp\ByteStream\ReadableResourceStream;
use Amp\ByteStream\ReadableStream;
use Amp\Cancellation;
use Amp\Http\Client\HttpContent;
use Symfony\Component\HttpClient\Exception\TransportException;

/**
 * @author Nicolas Grekas <p@tchwork.com>
 *
 * @internal
 */
class AmpBody implements HttpContent, ReadableStream, \IteratorAggregate
{
    private ReadableStream $body;
    private ?string $content;
    private array $info;
    private ?int $offset = 0;
    private int $length = -1;
    private ?int $uploaded = null;

    /**
     * @param \Closure|resource|string $body
     */
    public function __construct(
        $body,
        &$info,
        private \Closure $onProgress,
    ) {
        $this->info = &$info;

        if (\is_resource($body)) {
            $this->offset = ftell($body);
            $this->length = fstat($body)['size'];
            $this->body = new ReadableResourceStream($body);
        } elseif (\is_string($body)) {
            $this->length = \strlen($body);
            $this->body = new ReadableBuffer($body);
            $this->content = $body;
        } else {
            $this->body = new ReadableIterableStream((static function () use ($body) {
                while ('' !== $data = ($body)(16372)) {
                    if (!\is_string($data)) {
                        throw new TransportException(\sprintf('Return value of the "body" option callback must be string, "%s" returned.', get_debug_type($data)));
                    }

                    yield $data;
                }
            })());
        }
    }

    public function getContent(): ReadableStream
    {
        if (null !== $this->uploaded) {
            $this->uploaded = null;

            if (\is_string($this->body)) {
                $this->offset = 0;
            } elseif ($this->body instanceof ReadableResourceStream) {
                fseek($this->body->getResource(), $this->offset);
            }
        }

        return $this;
    }

    public function getContentType(): ?string
    {
        return null;
    }

    public function getContentLength(): ?int
    {
        return 0 <= $this->length ? $this->length - $this->offset : null;
    }

    public function read(?Cancellation $cancellation = null): ?string
    {
        $this->info['size_upload'] += $this->uploaded;
        $this->uploaded = 0;
        ($this->onProgress)();

        if (null !== $data = $this->body->read($cancellation)) {
            $this->uploaded = \strlen($data);
        } else {
            $this->info['upload_content_length'] = $this->info['size_upload'];
        }

        return $data;
    }

    public function isReadable(): bool
    {
        return $this->body->isReadable();
    }

    public function close(): void
    {
        $this->body->close();
    }

    public function isClosed(): bool
    {
        return $this->body->isClosed();
    }

    public function onClose(\Closure $onClose): void
    {
        $this->body->onClose($onClose);
    }

    public function getIterator(): \Traversable
    {
        return $this->body;
    }

    public static function rewind(HttpContent $body): HttpContent
    {
        if (!$body instanceof self) {
            return $body;
        }

        $body->uploaded = null;

        if ($body->body instanceof ReadableResourceStream && !$body->body->isClosed()) {
            fseek($body->body->getResource(), $body->offset);
        }

        if ($body->body instanceof ReadableBuffer) {
            return new $body($body->content, $body->info, $body->onProgress);
        }

        return $body;
    }
}
