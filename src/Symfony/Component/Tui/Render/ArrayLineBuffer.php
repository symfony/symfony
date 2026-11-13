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
     */
    public function __construct(private readonly array $lines)
    {
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
        foreach ($this->lines as $line) {
            if ('' !== $line) {
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
