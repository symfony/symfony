<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Tui\Render;

use Symfony\Component\Tui\Exception\OutOfBoundsException;

/**
 * @experimental
 *
 * @internal
 */
final class ConcatenatedLineBuffer implements LineBufferInterface
{
    /** @var list<LineBufferInterface> */
    private array $buffers;
    /** @var list<int> */
    private array $offsets = [];
    /** @var list<int> */
    private array $counts = [];
    private int $lastBuffer = 0;
    private int $lineCount = 0;
    private int $maxVisibleWidth = 0;

    /**
     * @param iterable<LineBufferInterface> $buffers
     */
    public function __construct(iterable $buffers)
    {
        $this->buffers = [];

        foreach ($buffers as $buffer) {
            if (0 === $bufferCount = \count($buffer)) {
                continue;
            }

            $this->offsets[] = $this->lineCount;
            $this->counts[] = $bufferCount;
            $this->buffers[] = $buffer;
            $this->lineCount += $bufferCount;
            $this->maxVisibleWidth = max($this->maxVisibleWidth, $buffer->getMaxVisibleWidth());
        }
    }

    public function count(): int
    {
        return $this->lineCount;
    }

    public function getLine(int $index): string
    {
        if ($index < 0 || $index >= $this->lineCount) {
            throw new OutOfBoundsException(\sprintf('Line index %d is out of bounds.', $index));
        }

        $i = $this->lastBuffer;
        if ($index < $this->offsets[$i] || $index >= $this->offsets[$i] + $this->counts[$i]) {
            $low = 0;
            $high = \count($this->offsets) - 1;
            while ($low < $high) {
                $mid = intdiv($low + $high + 1, 2);
                if ($this->offsets[$mid] <= $index) {
                    $low = $mid;
                } else {
                    $high = $mid - 1;
                }
            }
            $i = $this->lastBuffer = $low;
        }

        return $this->buffers[$i]->getLine($index - $this->offsets[$i]);
    }

    public function slice(int $offset, int $length): array
    {
        if ($offset < 0 || $length < 0) {
            throw new OutOfBoundsException('Line slice offset and length must be non-negative.');
        }
        if (0 === $length || $offset >= $this->lineCount) {
            return [];
        }

        $lines = [];
        $remaining = min($length, $this->lineCount - $offset);

        foreach ($this->buffers as $buffer) {
            $bufferCount = \count($buffer);
            if ($offset >= $bufferCount) {
                $offset -= $bufferCount;
                continue;
            }

            $bufferLines = $buffer->slice($offset, min($remaining, $bufferCount - $offset));
            foreach ($bufferLines as $line) {
                $lines[] = $line;
            }
            $remaining -= \count($bufferLines);
            if (0 === $remaining) {
                break;
            }
            $offset = 0;
        }

        return $lines;
    }

    public function toArray(): array
    {
        return $this->slice(0, $this->lineCount);
    }

    public function getMaxVisibleWidth(): int
    {
        return $this->maxVisibleWidth;
    }

    /**
     * @return list<LineBufferInterface>
     */
    public function getBuffers(): array
    {
        return $this->buffers;
    }

    public function getIterator(): \Traversable
    {
        foreach ($this->buffers as $buffer) {
            foreach ($buffer as $line) {
                yield $line;
            }
        }
    }
}
