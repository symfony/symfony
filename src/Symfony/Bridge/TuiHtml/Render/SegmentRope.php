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

use Symfony\Component\Tui\Render\LineBufferInterface;

/**
 * The lines of a layout by the part each child gives, joined a few by a few up to a single buffer: a part changed, only the
 * buffers above it are joined again, those of the others kept with their lines, see LayoutEngine::layoutVertical().
 *
 * @experimental
 *
 * @internal
 */
final class SegmentRope
{
    /** How many buffers each one above joins: a few, fewer joined for a layout, as few joined again for a part changed */
    private const FAN_OUT = 4;
    /** How many parts replaced in a row, the root not read, its lines then made from those before: else joined again */
    private const SOURCES = 8;

    /** @var list<list<LineBufferInterface>> The buffers of each part */
    private array $leaves;
    /** @var list<list<LineBufferInterface>> The buffers joined, level by level: the parts first, a single one last; a part of one buffer is that buffer */
    private array $levels = [];
    /** How many parts replaced since the root's lines were last kept, see replace() */
    private int $sources = 0;

    /**
     * @param list<list<LineBufferInterface>> $leaves
     */
    public function __construct(array $leaves)
    {
        $this->leaves = $leaves;
        $this->levels[0] = array_map(self::part(...), $leaves ?: [[]]);
        for ($level = 0; \count($this->levels[$level]) > 1; ++$level) {
            $this->levels[$level + 1] = array_map(static fn (array $group): ConcatenatedLineBuffer => new ConcatenatedLineBuffer($group), array_chunk($this->levels[$level], self::FAN_OUT));
        }
        $this->keepRootLines();
    }

    public function count(): int
    {
        return \count($this->leaves);
    }

    /**
     * The buffers of the parts from $from to $to, excluded.
     *
     * @return list<list<LineBufferInterface>>
     */
    public function getLeaves(int $from, int $to): array
    {
        return \array_slice($this->leaves, $from, $to - $from);
    }

    /**
     * Replaces the parts from $from on by as many others, joining again only what holds them.
     *
     * @param list<list<LineBufferInterface>> $leaves
     */
    public function replace(int $from, array $leaves): void
    {
        if (!$leaves) {
            return;
        }
        $to = $from + \count($leaves);
        // The lines of the root before, those of the parts left spliced around those of the new ones: made when read,
        // from those kept, or from what makes them, a few times in a row only
        $root = $this->levels[\count($this->levels) - 1][0];
        $previous = 1 < \count($this->levels) && $root instanceof ConcatenatedLineBuffer ? $root->getLineSource() : null;
        $this->sources = null === $previous ? 0 : ($root->hasKeptLines() ? 1 : $this->sources + 1);
        if ($this->sources > self::SOURCES) {
            [$previous, $this->sources] = [null, 0];
        }
        $before = null !== $previous ? $this->linesBefore($from) : 0;
        $after = 0;
        for ($leaf = $from; null !== $previous && $leaf < $to; ++$leaf) {
            $after += $this->linesOf($leaf);
        }
        array_splice($this->leaves, $from, \count($leaves), $leaves);
        foreach ($leaves as $i => $buffers) {
            $this->levels[0][$from + $i] = self::part($buffers);
        }
        for ($level = 1, $first = intdiv($from, self::FAN_OUT), $last = intdiv($to - 1, self::FAN_OUT); $level < \count($this->levels); ++$level, $first = intdiv($first, self::FAN_OUT), $last = intdiv($last, self::FAN_OUT)) {
            for ($i = $first; $i <= $last; ++$i) {
                $this->levels[$level][$i] = new ConcatenatedLineBuffer(\array_slice($this->levels[$level - 1], self::FAN_OUT * $i, self::FAN_OUT));
            }
        }
        $parts = \array_slice($this->levels[0], $from, $to - $from);
        $this->keepRootLines(null === $previous ? null : static function () use ($previous, $before, $after, $parts): array {
            $kept = $previous();
            $lines = [\array_slice($kept, 0, $before)];
            foreach ($parts as $part) {
                $lines[] = ConcatenatedLineBuffer::linesOf($part);
            }
            $lines[] = \array_slice($kept, $before + $after);

            return array_merge(...$lines);
        });
    }

    /**
     * The lines of a part.
     */
    public function linesOf(int $leaf): int
    {
        return \count($this->levels[0][$leaf]);
    }

    /**
     * The lines of the parts before one: those of the buffers joined on its left, level by level.
     */
    public function linesBefore(int $leaf): int
    {
        $lines = 0;
        foreach ($this->levels as $buffers) {
            for ($i = $leaf - $leaf % self::FAN_OUT; $i < $leaf; ++$i) {
                $lines += \count($buffers[$i]);
            }
            $leaf = intdiv($leaf, self::FAN_OUT);
        }

        return $lines;
    }

    public function getRoot(): ConcatenatedLineBuffer
    {
        $root = $this->levels[\count($this->levels) - 1][0];

        return $root instanceof ConcatenatedLineBuffer ? $root : new ConcatenatedLineBuffer([$root]);
    }

    /**
     * The root keeps its lines, joined in a layout around too: from them, a part replaced, the lines of the root after are
     * those of the others spliced around its own, not the parts joined again, nor every node of the rope keeping its own.
     *
     * @param (\Closure(): list<string>)|null $source
     */
    private function keepRootLines(?\Closure $source = null): void
    {
        // Not a part of its own, the buffer of a child maybe
        $root = $this->levels[\count($this->levels) - 1][0];
        if (1 < \count($this->levels) && $root instanceof ConcatenatedLineBuffer) {
            $root->keepLines($source);
        }
    }

    /**
     * The buffer of a part: its only one as it is, like the lines of a child rendered whole, not joined again.
     *
     * @param list<LineBufferInterface> $buffers
     */
    private static function part(array $buffers): LineBufferInterface
    {
        return 1 === \count($buffers) ? $buffers[0] : new ConcatenatedLineBuffer($buffers);
    }
}
