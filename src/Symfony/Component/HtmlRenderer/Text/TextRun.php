<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HtmlRenderer\Text;

/**
 * @internal
 */
final class TextRun
{
    public function __construct(public readonly int $start, public readonly int $end, public readonly ?TextSelection $selection = null, public readonly ?array $sourceOffsets = null, public readonly ?array $caretPositions = null)
    {
        if ($start < 0 || $end < $start) {
            throw new \InvalidArgumentException('A text run requires an ordered non-negative grapheme range.');
        }
    }

    public function toDisplay(int $source): int
    {
        if (null === $this->sourceOffsets) {
            return max(0, min($this->end - $this->start, $source - $this->start));
        }
        foreach ($this->sourceOffsets as $index => $offset) {
            if ($offset >= $source) {
                while (isset($this->sourceOffsets[$index + 1]) && $this->sourceOffsets[$index + 1] === $offset) {
                    ++$index;
                }

                return $index;
            }
        }

        return \count($this->sourceOffsets) - 1;
    }

    public function toSource(int $display): int
    {
        return null === $this->sourceOffsets ? min($this->end, $this->start + max(0, $display)) : $this->sourceOffsets[max(0, min(\count($this->sourceOffsets) - 1, $display))];
    }

    public function slice(int $start, int $length): self
    {
        $positions = null === $this->caretPositions ? null : \array_slice($this->caretPositions, $start, $length + 1);
        if (null !== $positions) {
            $left = min($positions);
            $positions = array_map(static fn (float $position): float => $position - $left, $positions);
        }

        return new self($this->toSource($start), $this->toSource($start + $length), $this->selection, null === $this->sourceOffsets ? null : \array_slice($this->sourceOffsets, $start, $length + 1), $positions);
    }

    /** @internal Maps discretionary characters without changing the editable source. */
    public static function fromPreparedLine(array $source, string $prepared, string $visible, int &$cursor, ?TextSelection $selection, bool $afterFirst = false): self
    {
        if ($afterFirst && isset($source[$cursor]) && \in_array($source[$cursor], ["\r", "\n", "\r\n"], true)) {
            ++$cursor;
        }
        $start = $cursor;
        $offsets = [$cursor];
        preg_match_all('/\X/u', $prepared, $matches);
        $count = preg_match_all('/\X/u', $visible) ?: 0;
        $unbrokenCount = preg_match_all('/\X/u', str_replace("\u{00AD}", '', $prepared)) ?: 0;
        foreach ($matches[0] as $index => $grapheme) {
            if ("\u{00AD}" === $grapheme) {
                if ("\u{00AD}" === ($source[$cursor] ?? null)) {
                    ++$cursor;
                    if ($index < \count($matches[0]) - 1 || $count <= $unbrokenCount) {
                        $offsets[\count($offsets) - 1] = $cursor;
                    }
                }
                continue;
            }
            while (isset($source[$cursor]) && ("\u{00AD}" === $source[$cursor] || $source[$cursor] !== $grapheme && preg_match('/^[\t\r\n\f ]+$/D', $source[$cursor]))) {
                ++$cursor;
                $offsets[\count($offsets) - 1] = $cursor;
            }
            if (isset($source[$cursor])) {
                ++$cursor;
            }
            $offsets[] = $cursor;
        }
        // A chosen discretionary break appends the hyphen without consuming source text.
        while (\count($offsets) <= $count) {
            $offsets[] = $cursor;
        }
        while ("\u{00AD}" === ($source[$cursor] ?? null)) {
            ++$cursor;
            $offsets[\count($offsets) - 1] = $cursor;
        }

        return new self($start, $cursor, $selection, $offsets);
    }
}
