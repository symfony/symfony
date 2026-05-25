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

use Symfony\Component\Tui\Widget\AbstractWidget;

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

    public function __construct()
    {
        $this->widgetPositions = new \WeakMap();
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
     * Record a widget's absolute position.
     */
    public function setWidgetRect(AbstractWidget $widget, WidgetRect $rect): void
    {
        $this->widgetPositions[$widget] = $rect;
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
     * Snapshot the set of widgets currently tracked.
     *
     * @return array<int, true>
     */
    public function snapshotKeys(): array
    {
        $snapshot = [];
        foreach ($this->widgetPositions as $widget => $_) {
            $snapshot[spl_object_id($widget)] = true;
        }

        return $snapshot;
    }

    /**
     * Shift positions for all widgets added since the snapshot.
     *
     * @param array<int, true>|null $before
     */
    public function shiftDescendantPositions(?array $before, int $colOffset, int $rowOffset = 0): void
    {
        if (null === $before) {
            return;
        }

        foreach ($this->widgetPositions as $widget => $rect) {
            if (isset($before[spl_object_id($widget)])) {
                continue;
            }
            $this->widgetPositions[$widget] = new WidgetRect(
                $rect->row + $rowOffset,
                $rect->col + $colOffset,
                $rect->columns,
                $rect->rows,
            );
        }
    }
}
