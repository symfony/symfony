<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpClient\Tests\Fixtures;

use Nyholm\Psr7\Stream;
use Psr\Http\Message\StreamInterface;

/**
 * A stream that doesn't know its size, as allowed by PSR-7 and exhibited by
 * pipe-backed streams (e.g. "php://input") or by Guzzle's Pump/CachingStream.
 */
class UnknownSizeStream implements StreamInterface
{
    private StreamInterface $inner;

    public function __construct(
        string $content,
        private bool $seekable = true,
    ) {
        $this->inner = Stream::create($content);
    }

    public function getSize(): ?int
    {
        return null;
    }

    public function isSeekable(): bool
    {
        return $this->seekable;
    }

    public function seek(int $offset, int $whence = \SEEK_SET): void
    {
        if (!$this->seekable) {
            throw new \RuntimeException('Stream is not seekable.');
        }

        $this->inner->seek($offset, $whence);
    }

    public function rewind(): void
    {
        $this->seek(0);
    }

    public function __toString(): string
    {
        return $this->inner->__toString();
    }

    public function close(): void
    {
        $this->inner->close();
    }

    public function detach(): mixed
    {
        return $this->inner->detach();
    }

    public function tell(): int
    {
        return $this->inner->tell();
    }

    public function eof(): bool
    {
        return $this->inner->eof();
    }

    public function isWritable(): bool
    {
        return false;
    }

    public function write(string $string): int
    {
        throw new \RuntimeException('Stream is not writable.');
    }

    public function isReadable(): bool
    {
        return true;
    }

    public function read(int $length): string
    {
        return $this->inner->read($length);
    }

    public function getContents(): string
    {
        return $this->inner->getContents();
    }

    public function getMetadata(?string $key = null): mixed
    {
        return $this->inner->getMetadata($key);
    }
}
