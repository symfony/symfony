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

use Symfony\Component\Tui\Render\WidgetRect;
use Symfony\Component\Tui\Widget\AbstractWidget;
use Symfony\Component\Tui\Widget\ParentInterface;

/**
 * Tracks absolute positions of rendered widgets on screen.
 *
 * Maintains a stack of absolute [row, col] offsets for the current rendering
 * context and records each widget's final position as a WidgetRect.
 *
 * @experimental
 *
 * @internal
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
final class PositionTracker
{
    /** @var \WeakMap<AbstractWidget, WidgetRect> */
    private \WeakMap $widgetPositions;
    /**
     * Stack of absolute [row, col] offsets for the current rendering context.
     *
     * @var list<array{int, int}>
     */
    private array $positionStack = [];
    /**
     * The widgets whose last render was not tracked, like the measuring pass of a layout.
     *
     * @var \WeakMap<AbstractWidget, true>
     */
    private \WeakMap $untracked;

    public function __construct()
    {
        $this->widgetPositions = new \WeakMap();
        $this->untracked = new \WeakMap();
    }

    /**
     * Record whether a widget was just rendered with its positions tracked.
     *
     * After a render without tracking, the rects of the descendants still come from an earlier frame, so moveSubtree() must not reuse them.
     */
    public function markRendered(AbstractWidget $widget): void
    {
        if ($this->isActive()) {
            unset($this->untracked[$widget]);
        } else {
            $this->untracked[$widget] = true;
        }
    }

    /**
     * Reset the position stack for a new render pass.
     *
     * Previous widget positions are preserved so that cached subtrees
     * (which skip re-rendering) keep their tracked rects. Any widget
     * whose parent is re-rendered gets a fresh rect, replacing the old
     * entry. Removed widgets are garbage-collected by the WeakMap.
     */
    public function reset(): void
    {
        $this->positionStack = [[0, 0]];
    }

    /**
     * Get the tracked position of a widget from the last render pass.
     */
    public function getWidgetRect(AbstractWidget $widget): ?WidgetRect
    {
        return $this->widgetPositions[$widget] ?? null;
    }

    /**
     * Forgets where a widget and what it holds were, like one not drawn this frame: they take no pointer, and are
     * tracked again when drawn.
     */
    public function forget(AbstractWidget $widget): void
    {
        unset($this->widgetPositions[$widget]);
        $this->untracked[$widget] = true;
        foreach ($widget instanceof ParentInterface ? $widget->all() : [] as $child) {
            $this->forget($child);
        }
    }

    /**
     * Record a widget's absolute position.
     */
    public function setWidgetRect(AbstractWidget $widget, WidgetRect $rect): void
    {
        $this->widgetPositions[$widget] = $rect;
    }

    /**
     * Move a previously tracked widget and its descendants to a new position.
     */
    public function moveSubtree(AbstractWidget $widget, WidgetRect $rect): bool
    {
        if (!isset($this->widgetPositions[$widget]) || isset($this->untracked[$widget])) {
            return false;
        }
        $previous = $this->widgetPositions[$widget];
        $rowOffset = $rect->row - $previous->row;
        $colOffset = $rect->col - $previous->col;
        if (0 !== $rowOffset || 0 !== $colOffset) {
            $this->shiftSubtree($widget, $rowOffset, $colOffset);
        }
        $this->widgetPositions[$widget] = $rect;

        return true;
    }

    /**
     * Whether position tracking is active (has a non-empty stack).
     */
    public function isActive(): bool
    {
        return (bool) $this->positionStack;
    }

    /**
     * Get the current absolute [row, col] offset from the top of the stack.
     *
     * @return array{int, int}
     */
    public function currentOffset(): array
    {
        return $this->positionStack[\count($this->positionStack) - 1];
    }

    /**
     * Push a new absolute [row, col] offset onto the stack.
     */
    public function push(int $row, int $col): void
    {
        $this->positionStack[] = [$row, $col];
    }

    /**
     * Pop the top offset from the stack.
     */
    public function pop(): void
    {
        if (\count($this->positionStack) > 1) {
            array_pop($this->positionStack);
        }
    }

    /**
     * Save the position stack and replace it with an empty one.
     *
     * Used to suppress position tracking during measurement passes.
     *
     * @return list<array{int, int}>
     */
    public function suppressStack(): array
    {
        $saved = $this->positionStack;
        $this->positionStack = [];

        return $saved;
    }

    /**
     * Restore a previously saved position stack.
     *
     * @param list<array{int, int}> $stack
     */
    public function restoreStack(array $stack): void
    {
        $this->positionStack = $stack;
    }

    /**
     * Shift the tracked positions of laid-out content by the given offsets.
     *
     * Called when alignment moves a container's content after layout, so the
     * children and their descendants keep matching where they are drawn.
     *
     * @param AbstractWidget[] $children
     */
    public function shiftContentPositions(array $children, int $colOffset, int $rowOffset = 0): void
    {
        if (0 === $colOffset && 0 === $rowOffset) {
            return;
        }
        foreach ($children as $child) {
            $this->shiftSubtree($child, $rowOffset, $colOffset);
        }
    }

    /**
     * Shifts the positions of scrolled content and clips them to the rows shown: a widget scrolled out has no position.
     *
     * @param AbstractWidget[] $children
     */
    public function scrollContentPositions(array $children, int $rowOffset, int $top, int $bottom): void
    {
        foreach ($children as $child) {
            $this->clipSubtree($child, $rowOffset, $top, $bottom);
        }
    }

    /**
     * Shifts the positions of content scrolled sideways and clips them to the columns shown: a widget scrolled out has
     * no position.
     *
     * @param AbstractWidget[] $children
     */
    public function scrollContentColumns(array $children, int $colOffset, int $left, int $right): void
    {
        foreach ($children as $child) {
            $this->clipSubtreeColumns($child, $colOffset, $left, $right);
        }
    }

    private function clipSubtreeColumns(AbstractWidget $widget, int $colOffset, int $left, int $right): void
    {
        if (isset($this->widgetPositions[$widget])) {
            $rect = $this->widgetPositions[$widget];
            $from = max($left, $rect->col + $colOffset);
            $to = min($right, $rect->col + $colOffset + $rect->columns);
            if ($to > $from && $from === $rect->col + $colOffset && $to - $from === $rect->columns) {
                $this->widgetPositions[$widget] = new WidgetRect($rect->row, $from, $rect->columns, $rect->rows);
            } else {
                $this->untracked[$widget] = true;
                if ($to > $from) {
                    $this->widgetPositions[$widget] = new WidgetRect($rect->row, $from, $to - $from, $rect->rows);
                } else {
                    unset($this->widgetPositions[$widget]);
                }
            }
        }
        if ($widget instanceof ParentInterface) {
            foreach ($widget->all() as $child) {
                $this->clipSubtreeColumns($child, $colOffset, $left, $right);
            }
        }
    }

    private function clipSubtree(AbstractWidget $widget, int $rowOffset, int $top, int $bottom): void
    {
        if (isset($this->widgetPositions[$widget])) {
            $rect = $this->widgetPositions[$widget];
            $from = max($top, $rect->row + $rowOffset);
            $to = min($bottom, $rect->row + $rowOffset + $rect->rows);
            if ($to > $from && $from === $rect->row + $rowOffset && $to - $from === $rect->rows) {
                $this->widgetPositions[$widget] = new WidgetRect($from, $rect->col, $rect->columns, $rect->rows);
            } else {
                // Clipped: its rect no longer tells where its descendants are, they must be tracked again
                $this->untracked[$widget] = true;
                if ($to > $from) {
                    $this->widgetPositions[$widget] = new WidgetRect($from, $rect->col, $rect->columns, $to - $from);
                } else {
                    unset($this->widgetPositions[$widget]);
                }
            }
        }
        if ($widget instanceof ParentInterface) {
            foreach ($widget->all() as $child) {
                $this->clipSubtree($child, $rowOffset, $top, $bottom);
            }
        }
    }

    private function shiftSubtree(AbstractWidget $widget, int $rowOffset, int $colOffset): void
    {
        if (isset($this->widgetPositions[$widget])) {
            $rect = $this->widgetPositions[$widget];
            $this->widgetPositions[$widget] = new WidgetRect($rect->row + $rowOffset, $rect->col + $colOffset, $rect->columns, $rect->rows, $rect->fragments);
        }
        if (!$widget instanceof ParentInterface) {
            return;
        }
        foreach ($widget->all() as $child) {
            $this->shiftSubtree($child, $rowOffset, $colOffset);
        }
    }
}
