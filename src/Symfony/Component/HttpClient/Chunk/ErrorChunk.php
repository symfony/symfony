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
    protected $didThrow = false;
    private $offset;
    private $error;

    public function __construct(int $offset, \Throwable $error)
    {
        $this->offset = $offset;
        $this->error = $error;
    }

    /**
     * {@inheritdoc}
     */
    public function isTimeout(): bool
    {
        $this->throw();
    }

    /**
     * {@inheritdoc}
     */
    public function isFirst(): bool
    {
        $this->throw();
    }

    /**
     * {@inheritdoc}
     */
    public function isLast(): bool
    {
        $this->throw();
    }

    /**
     * {@inheritdoc}
     */
    public function getContent(): string
    {
        $this->throw();
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
        return $this->error->getMessage();
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
            $this->throw();
        }
    }

    private function throw(): void
    {
        $this->didThrow = true;
        throw $this->error instanceof TransportException ? $this->error : new TransportException($this->error->getMessage(), 0, $this->error);
    }
}
