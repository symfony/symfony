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

use Symfony\Component\Tui\Ansi\AnsiUtils;
use Symfony\Component\Tui\Exception\OutOfBoundsException;

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
     */
    public function __construct(private readonly array $lines, ?int $maxVisibleWidth = null)
    {
        // Known by what made the lines, like a layout padding each line to its width: not measured again
        $this->maxVisibleWidth = $maxVisibleWidth;
        \assert(null === $maxVisibleWidth || $maxVisibleWidth === array_reduce($lines, static fn (int $max, string $line) => '' === $line ? $max : max($max, AnsiUtils::visibleWidth($line)), 0), 'The width given is the one of the widest line.');
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
