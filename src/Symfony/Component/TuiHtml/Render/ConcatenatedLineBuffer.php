<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\TuiHtml\Render;

use Symfony\Component\Tui\Render\LineBufferInterface;
use Symfony\Component\TuiHtml\Exception\OutOfBoundsException;

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
    /** The width of every line when the buffers each have lines all as wide, the same, see ArrayLineBuffer::uniformWidthOf() */
    private ?int $uniformWidth = null;
    /** @var list<string>|null All the lines, once joined: a buffer is kept, like the render of a widget, and read again */
    private ?array $lines = null;
    /**
     * How many buffers in each other keep the lines read at most, the roots of ropes in them keeping theirs: one more for
     * each rope in another, nested ropes keep a bounded number of copies; 2 keeps the splices of the tables and lists of
     * a page as cheap as when every rope kept its lines, see join().
     */
    private const KEPT_COPIES = 2;
    /** Whether it keeps its lines when a buffer it is joined in joins them all, like the root of a SegmentRope */
    private bool $keepsLines = false;
    /** @var (\Closure(): list<string>)|null Its lines from those of another, see SegmentRope::replace() */
    private ?\Closure $source = null;

    /**
     * @param iterable<LineBufferInterface> $buffers
     */
    public function __construct(iterable $buffers)
    {
        $this->buffers = [];
        $uniform = true;
        foreach ($buffers as $buffer) {
            if (0 === $bufferCount = \count($buffer)) {
                continue;
            }
            $this->offsets[] = $this->lineCount;
            $this->counts[] = $bufferCount;
            $this->buffers[] = $buffer;
            $this->lineCount += $bufferCount;
            $this->maxVisibleWidth = max($this->maxVisibleWidth, $buffer->getMaxVisibleWidth());
            if ($uniform && (null === ($width = ArrayLineBuffer::uniformWidthOf($buffer)) || null !== $this->uniformWidth && $width !== $this->uniformWidth)) {
                $uniform = false;
                $this->uniformWidth = null;
            } elseif ($uniform) {
                $this->uniformWidth = $width;
            }
        }
    }

    /**
     * The width of every line when they are all as wide and it is known, else null, see ArrayLineBuffer::uniformWidthOf().
     */
    public function getUniformWidth(): ?int
    {
        return $this->uniformWidth;
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
        if (null !== $this->lines) {
            return \array_slice($this->lines, $offset, $length);
        }
        $length = min($length, $this->lineCount - $offset);
        // All of them, joined once; or most of those of the root of a rope, like the lines between its margins
        if (0 === $offset && $length === $this->lineCount) {
            return $this->toArray();
        }
        if ($this->keepsLines && 2 * $length >= $this->lineCount) {
            return \array_slice($this->toArray(), $offset, $length);
        }

        // A few: through the buffers, its lines left to make, like a window of a long page
        return $this->join($offset, $length);
    }

    /**
     * Its lines kept, $kept buffers around keeping them too, then some of them.
     *
     * @return list<string>
     */
    private function keptSlice(int $offset, int $length, int $kept): array
    {
        if (null === $this->lines) {
            if (null !== $source = $this->source) {
                $this->source = null;
                $this->lines = $source();
            } else {
                $this->lines = $this->join(0, $this->lineCount, $kept);
            }
        }

        return 0 === $offset && $length === $this->lineCount ? $this->lines : \array_slice($this->lines, $offset, $length);
    }

    /**
     * Keeps its lines when a buffer it is joined in joins them all, not only when it is read itself: the root of a rope,
     * its lines spliced when a part of it changes, see SegmentRope::replace().
     *
     * @param (\Closure(): list<string>)|null $source Its lines, from those of the root before
     *
     * @internal
     */
    public function keepLines(?\Closure $source = null): void
    {
        $this->keepsLines = true;
        $this->source = $source;
    }

    /**
     * What makes its lines without joining its buffers: those it keeps, or what makes them from those of another.
     *
     * @return (\Closure(): list<string>)|null
     *
     * @internal
     */
    public function getLineSource(): ?\Closure
    {
        if (null !== $lines = $this->lines) {
            return static fn (): array => $lines;
        }

        return $this->source;
    }

    /**
     * @internal
     */
    public function hasKeptLines(): bool
    {
        return null !== $this->lines;
    }

    /**
     * The lines of a buffer, kept by none of those it joins.
     *
     * @return list<string>
     *
     * @internal
     */
    public static function linesOf(LineBufferInterface $buffer): array
    {
        if (!$buffer instanceof self) {
            return $buffer->slice(0, \count($buffer));
        }

        return $buffer->lines ?? (null !== $buffer->source ? ($buffer->source)() : $buffer->join(0, $buffer->lineCount, self::KEPT_COPIES));
    }

    /**
     * @param int $kept How many of the buffers it is joined in, it included, keep the lines joined: the roots of ropes in
     *                  it keep theirs only under a few, else each would keep them once more for each rope around it
     *
     * @return list<string>
     */
    private function join(int $offset, int $length, int $kept = 0): array
    {
        // The lines of each buffer joined at once, not one by one
        $parts = [];
        $remaining = min($length, $this->lineCount - $offset);
        foreach ($this->buffers as $buffer) {
            $bufferCount = \count($buffer);
            if ($offset >= $bufferCount) {
                $offset -= $bufferCount;
                continue;
            }
            // A buffer joined in it joined at the same time, not kept joined too: else each of those in each other
            // would keep all its lines, like the boxes of a page in boxes; but for the root of a rope most of whose
            // lines are read, when nothing around keeps them
            $take = min($remaining, $bufferCount - $offset);
            if (!$buffer instanceof self || null !== $buffer->lines) {
                $parts[] = $bufferLines = $buffer->slice($offset, $take);
            } elseif ($kept < self::KEPT_COPIES && $buffer->keepsLines && 2 * $take >= $bufferCount) {
                $parts[] = $bufferLines = $buffer->keptSlice($offset, $take, $kept + 1);
            } elseif (null !== $buffer->source && 0 === $offset && $take === $bufferCount) {
                $parts[] = $bufferLines = ($buffer->source)();
            } else {
                $parts[] = $bufferLines = $buffer->join($offset, $take, $kept);
            }
            $remaining -= \count($bufferLines);
            if (0 === $remaining) {
                break;
            }
            $offset = 0;
        }

        return array_merge(...$parts);
    }

    public function toArray(): array
    {
        if (null === $this->lines && null !== $source = $this->source) {
            $this->source = null;

            return $this->lines = $source();
        }

        return $this->lines ??= $this->join(0, $this->lineCount, 1);
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
        // Joined at once, not each line passed on by each buffer in turn, through those nested in it
        yield from $this->toArray();
    }
}
