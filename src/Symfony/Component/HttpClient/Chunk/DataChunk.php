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

use Symfony\Contracts\HttpClient\ChunkInterface;

/**
 * @author Nicolas Grekas <p@tchwork.com>
 *
 * @internal
 */
class DataChunk implements ChunkInterface
{
    private int $offset = 0;
    private string $content = '';

    public function __construct(int $offset = 0, string $content = '')
    {
        $this->offset = $offset;
        $this->content = $content;
    }

    /**
     * {@inheritdoc}
     */
    public function isTimeout(): bool
    {
        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function isFirst(): bool
    {
        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function isLast(): bool
    {
        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function getInformationalStatus(): ?array
    {
        return null;
    }

    /**
     * {@inheritdoc}
     */
    public function getContent(): string
    {
        return $this->content;
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
        return null;
    }
}
