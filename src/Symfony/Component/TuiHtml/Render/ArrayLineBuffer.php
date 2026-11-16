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

use Symfony\Component\Tui\Ansi\AnsiUtils;
use Symfony\Component\Tui\Render\LineBufferInterface;
use Symfony\Component\TuiHtml\Exception\OutOfBoundsException;

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
