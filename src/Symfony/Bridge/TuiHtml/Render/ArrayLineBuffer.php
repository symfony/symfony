<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\TuiHtml\Render;

use Symfony\Bridge\TuiHtml\Exception\OutOfBoundsException;
use Symfony\Component\Tui\Ansi\AnsiUtils;
use Symfony\Component\Tui\Render\LineBufferInterface;

/**
 * @experimental
 *
 * @internal
 */
final class ArrayLineBuffer implements LineBufferInterface
{
    private ?int $maxVisibleWidth = null;

    /**
     * @param list<string> $lines
     * @param int|null     $maxVisibleWidth The width of the widest line, when it is known
     * @param bool         $uniform         Whether every line is that wide, like the rows of a box padded to its width
     */
    public function __construct(private readonly array $lines, ?int $maxVisibleWidth = null, private readonly bool $uniform = false)
    {
        // Known by what made the lines, like a layout padding each line to its width: not measured again
        $this->maxVisibleWidth = $maxVisibleWidth;
        \assert(null === $maxVisibleWidth || $maxVisibleWidth === array_reduce($lines, static fn (int $max, string $line) => '' === $line ? $max : max($max, AnsiUtils::visibleWidth($line)), 0), 'The width given is the one of the widest line.');
        \assert(!$uniform || null !== $maxVisibleWidth && [] === array_filter($lines, static fn (string $line) => AnsiUtils::visibleWidth($line) !== $maxVisibleWidth), 'Every line is as wide as the width given.');
    }

    /**
     * The width of every line of a buffer when they are all as wide and it is known, like the rows of a box padded to
     * its width; null otherwise: their widths are then measured.
     */
    public static function uniformWidthOf(LineBufferInterface $lines): ?int
    {
        return match (true) {
            $lines instanceof self => $lines->uniform ? $lines->maxVisibleWidth : null,
            $lines instanceof ConcatenatedLineBuffer => $lines->getUniformWidth(),
            default => null,
        };
    }

    /**
     * The lines of a buffer by runs, those of the buffers it joins, each with the width of all its lines when they are
     * all as wide and it is known, see uniformWidthOf().
     *
     * @return list<array{list<string>, int|null}>
     */
    public static function partsOf(LineBufferInterface $lines): array
    {
        if (!$lines instanceof ConcatenatedLineBuffer) {
            return [[$lines->toArray(), self::uniformWidthOf($lines)]];
        }

        return array_merge(...array_map(self::partsOf(...), $lines->getBuffers()));
    }

    /**
     * The lines of a part of a buffer, as wide as it when the few lines left out are narrower: not measured again, like
     * the content of a block without the margins collapsed through it.
     */
    public static function sliceOf(LineBufferInterface $lines, int $offset, int $length): self
    {
        $part = new self($lines->slice($offset, $length));
        $end = $offset + \count($part);
        $width = $lines->getMaxVisibleWidth();
        // A buffer of the part as wide as all of them, among its first ones: the widest
        $tried = 0;
        if ($lines instanceof ConcatenatedLineBuffer && self::holdsWidest($lines, $offset, $end, $width, $tried)) {
            $part->maxVisibleWidth = $width;

            return $part;
        }
        // Or the few lines left out narrower than the widest
        $left = \count($lines) - \count($part);
        if (0 < $width && $left <= 64) {
            for ($row = 0; $row < $offset; ++$row) {
                if (AnsiUtils::visibleWidth($lines->getLine($row)) >= $width) {
                    return $part;
                }
            }
            for ($row = $end; $row < \count($lines); ++$row) {
                if (AnsiUtils::visibleWidth($lines->getLine($row)) >= $width) {
                    return $part;
                }
            }
            $part->maxVisibleWidth = $width;
        }

        return $part;
    }

    /**
     * Whether a buffer joined in another, among its first ones, is in a part of its lines and as wide as all of them;
     * in those joined in a buffer joined across the edge of the part too, like the parts of a layout joined a few by a few,
     * see SegmentRope.
     */
    private static function holdsWidest(ConcatenatedLineBuffer $lines, int $offset, int $end, int $width, int &$tried, int $depth = 0): bool
    {
        $at = 0;
        foreach ($lines->getBuffers() as $buffer) {
            $count = \count($buffer);
            if ($at >= $offset && $at + $count <= $end) {
                if ($buffer->getMaxVisibleWidth() === $width) {
                    return true;
                }
                ++$tried;
            } elseif ($buffer instanceof ConcatenatedLineBuffer && $depth < 64 && $at < $end && $at + $count > $offset && $buffer->getMaxVisibleWidth() === $width && self::holdsWidest($buffer, $offset - $at, $end - $at, $width, $tried, $depth + 1)) {
                return true;
            }
            $at += $count;
            if ($at >= $end || $tried >= 8) {
                break;
            }
        }

        return false;
    }

    /**
     * Some of the lines of a buffer, like its first ones: all as wide as its lines when they all are.
     *
     * @param list<string> $lines
     */
    public static function takenFrom(LineBufferInterface $buffer, array $lines): self
    {
        return null !== ($width = self::uniformWidthOf($buffer)) ? new self($lines, $lines ? $width : 0, true) : new self($lines);
    }

    public function count(): int
    {
        return \count($this->lines);
    }

    public function getLine(int $index): string
    {
        return $this->lines[$index] ?? throw new OutOfBoundsException(\sprintf('Line index %d is out of bounds.', $index));
    }

    public function slice(int $offset, int $length): array
    {
        if ($offset < 0 || $length < 0) {
            throw new OutOfBoundsException('Line slice offset and length must be non-negative.');
        }

        return \array_slice($this->lines, $offset, $length);
    }

    public function toArray(): array
    {
        return $this->lines;
    }

    public function getMaxVisibleWidth(): int
    {
        if (null !== $this->maxVisibleWidth) {
            return $this->maxVisibleWidth;
        }
        $max = 0;
        // Each line measured once, the rows alike, like the blank ones of a tall box, many
        $seen = [];
        foreach ($this->lines as $line) {
            if ('' !== $line && !isset($seen[$line])) {
                $seen[$line] = true;
                $max = max($max, AnsiUtils::visibleWidth($line));
            }
        }

        return $this->maxVisibleWidth = $max;
    }

    public function getIterator(): \Traversable
    {
        yield from $this->lines;
    }
}
