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

    public function __construct(int $offset, \Throwable $error = null)
    {
        $this->offset = $offset;
        $this->error = $error;
        $this->errorMessage = null !== $error ? $error->getMessage() : 'Reading from the response stream reached the inactivity timeout.';
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
        throw new TransportException($this->errorMessage, 0, $this->error);
    }

    /**
     * {@inheritdoc}
     */
    public function isLast(): bool
    {
        $this->didThrow = true;
        throw new TransportException($this->errorMessage, 0, $this->error);
    }

    /**
     * {@inheritdoc}
     */
    public function getContent(): string
    {
        $this->didThrow = true;
        throw new TransportException($this->errorMessage, 0, $this->error);
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
    public function didThrow(): bool
    {
        return $this->didThrow;
    }

    public function __destruct()
    {
        if (!$this->didThrow) {
            $this->didThrow = true;
            throw new TransportException($this->errorMessage, 0, $this->error);
        }
    }
}
