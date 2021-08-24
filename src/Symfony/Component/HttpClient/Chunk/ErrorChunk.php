<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpClient\Chunk;

use Symfony\Component\HttpClient\Exception\TimeoutException;
use Symfony\Component\HttpClient\Exception\TransportException;
use Symfony\Contracts\HttpClient\ChunkInterface;

/**
 * @author Nicolas Grekas <p@tchwork.com>
 *
 * @internal
 */
class ErrorChunk implements ChunkInterface
{
    private $didThrow = false;
    private $offset;
    private $errorMessage;
    private $error;

    /**
     * @param \Throwable|string $error
     */
    public function __construct(int $offset, $error)
    {
        $this->offset = $offset;

        if (\is_string($error)) {
            $this->errorMessage = $error;
        } else {
            $this->error = $error;
            $this->errorMessage = $error->getMessage();
        }
    }

    /**
     * {@inheritdoc}
     */
    public function isTimeout(): bool
    {
        $this->didThrow = true;

        if (null !== $this->error) {
            throw new TransportException($this->errorMessage, 0, $this->error);
        }

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function isFirst(): bool
    {
        $this->didThrow = true;
        throw null !== $this->error ? new TransportException($this->errorMessage, 0, $this->error) : new TimeoutException($this->errorMessage);
    }

    /**
     * {@inheritdoc}
     */
    public function isLast(): bool
    {
        $this->didThrow = true;
        throw null !== $this->error ? new TransportException($this->errorMessage, 0, $this->error) : new TimeoutException($this->errorMessage);
    }

    /**
     * {@inheritdoc}
     */
    public function getInformationalStatus(): ?array
    {
        $this->didThrow = true;
        throw null !== $this->error ? new TransportException($this->errorMessage, 0, $this->error) : new TimeoutException($this->errorMessage);
    }

    /**
     * {@inheritdoc}
     */
    public function getContent(): string
    {
        $this->didThrow = true;
        throw null !== $this->error ? new TransportException($this->errorMessage, 0, $this->error) : new TimeoutException($this->errorMessage);
    }

    /**
     * {@inheritdoc}
     */
    public function getOffset(): int
    {
        return $this->offset;
    }

    /**
     * {@inheritdoc}
     */
    public function getError(): ?string
    {
        return $this->errorMessage;
    }

    /**
     * @return bool Whether the wrapped error has been thrown or not
     */
    public function didThrow(bool $didThrow = null): bool
    {
        if (null !== $didThrow && $this->didThrow !== $didThrow) {
            return !$this->didThrow = $didThrow;
        }

        return $this->didThrow;
    }

    public function __sleep(): array
    {
        throw new \BadMethodCallException('Cannot serialize '.__CLASS__);
    }

    public function __wakeup()
    {
        throw new \BadMethodCallException('Cannot unserialize '.__CLASS__);
    }

    public function __destruct()
    {
        if (!$this->didThrow) {
            $this->didThrow = true;
            throw null !== $this->error ? new TransportException($this->errorMessage, 0, $this->error) : new TimeoutException($this->errorMessage);
        }
    }
}
