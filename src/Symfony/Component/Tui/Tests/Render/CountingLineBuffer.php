<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Tui\Tests\Render;

use Symfony\Component\Tui\Render\LineBufferInterface;

/** @internal */
final class CountingLineBuffer implements LineBufferInterface
{
    private int $readCount = 0;

    public function __construct(private readonly int $lineCount)
    {
    }

    public function count(): int
    {
        return $this->lineCount;
    }

    public function getLine(int $index): string
    {
        ++$this->readCount;

        return 'transcript '.$index;
    }

    public function slice(int $offset, int $length): array
    {
        $lines = [];
        for ($i = $offset; $i < min($offset + $length, $this->lineCount); ++$i) {
            $lines[] = $this->getLine($i);
        }

        return $lines;
    }

    public function toArray(): array
    {
        return $this->slice(0, $this->lineCount);
    }

    public function getMaxVisibleWidth(): int
    {
        return 20;
    }

    public function getIterator(): \Traversable
    {
        for ($i = 0; $i < $this->lineCount; ++$i) {
            yield $this->getLine($i);
        }
    }

    public function resetReadCount(): void
    {
        $this->readCount = 0;
    }

    public function getReadCount(): int
    {
        return $this->readCount;
    }
}
