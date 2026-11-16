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
    /**
     * The children of each widget whose rect was written or forgotten, by object id, the last written last, each with
     * the serial of that write: what a layout placed and that changed since, see getChildrenWrittenSince().
     *
     * @var \WeakMap<AbstractWidget, \stdClass>
     */
    private \WeakMap $written;
    private int $writes = 0;
    /**
     * The rows some children of each widget, and what they hold, were moved down by and that their rects do not tell
     * yet: by those children, by object id; their rects are kept without them, see shiftChildrenLater().
     *
     * @var \WeakMap<AbstractWidget, list<array{array<int, int>, int}>>
     */
    private \WeakMap $pending;
    /**
     * The clip each box that clips what it holds cuts it to for the pointer, see setContentClip(): moved with it, its
     * rows moved later aside like those of rects.
     *
     * @var \WeakMap<AbstractWidget, array{int, int, int, int}>
     */
    private \WeakMap $contentClips;
    /**
     * The boxes each widget is cut by for the pointer, by object id, see clipBy(): their clips read with its rect,
     * where they are then, not where they were when it was cut, like when it moved inside them.
     *
     * @var \WeakMap<AbstractWidget, array<int, \WeakReference<AbstractWidget>>>
     */
    private \WeakMap $clippedBy;
    /**
     * The last rect read of each widget cut by boxes, see getWidgetRect(): the rect kept, the version of the clips it
     * was read at, and the rect read; the same while they are.
     *
     * @var \WeakMap<AbstractWidget, array{WidgetRect, int, WidgetRect}>
     */
    private \WeakMap $clippedRects;
    /**
     * Whether forget() cleared each widget and it was not placed since, see forgetUndrawn(): not one that lost its rect
     * otherwise, like one a scroll container shows none of, what it holds keeping theirs.
     *
     * @var \WeakMap<AbstractWidget, bool>
     */
    private \WeakMap $forgotten;
    /** Changes whenever a content clip may read differently, see getWidgetRect(): set, moved, or rows moved later */
    private int $clipsVersion = 0;

    public function __construct()
    {
        $this->widgetPositions = new \WeakMap();
        $this->untracked = new \WeakMap();
        $this->written = new \WeakMap();
        $this->pending = new \WeakMap();
        $this->contentClips = new \WeakMap();
        $this->clippedBy = new \WeakMap();
        $this->clippedRects = new \WeakMap();
        $this->forgotten = new \WeakMap();
    }

    /**
     * Moves children of a widget, and what they hold, down by some rows, at once: their rects tell it when read, not
     * written again one by one, like the blocks after one that grew, as they were but lower, see
     * LayoutEngine::layoutVertical(). The rects written meanwhile are of where the widgets are, those moves aside.
     *
     * @param list<int> $ids The object ids of those children
     */
    public function shiftChildrenLater(AbstractWidget $parent, array $ids, int $rows): void
    {
        if (0 === $rows || !$ids) {
            return;
        }
        $moves = $this->pending[$parent] ?? [];
        $moves[] = [array_flip($ids), $rows];
        $this->pending[$parent] = $moves;
        ++$this->clipsVersion;
        // A few, each looked for when a rect is read
        if (\count($moves) > 8) {
            $this->settle($parent);
        }
    }

    /**
     * Writes the moves of the children of a widget not written yet in their rects, see shiftChildrenLater(): before its
     * children change, whose object ids they are by.
     */
    public function settle(AbstractWidget $parent): void
    {
        if (null === $moves = $this->pending[$parent] ?? null) {
            return;
        }
        unset($this->pending[$parent]);
        ++$this->clipsVersion;
        foreach ($parent instanceof ParentInterface ? $parent->all() : [] as $child) {
            if (0 !== $rows = self::movedRows($moves, $child)) {
                $this->shiftSubtree($child, $rows, 0);
            }
        }
    }

    /**
     * Whether some children of a widget were moved later and their rects do not tell it yet, see shiftChildrenLater().
     */
    public function hasPendingMoves(AbstractWidget $parent): bool
    {
        return isset($this->pending[$parent]);
    }

    /**
     * The serial of the last write of a rect: what getChildrenWrittenSince() takes.
     */
    public function getWriteSerial(): int
    {
        return $this->writes;
    }

    /**
     * The object ids of the children of a widget whose rect was written, moved, cut, forgotten or marked untracked since
     * the write serial was $serial, see getWriteSerial(): the last written first. Null when they were not asked for
     * before, since when they are kept.
     *
     * @return list<int>|null
     */
    public function getChildrenWrittenSince(AbstractWidget $parent, int $serial): ?array
    {
        if (null === $journal = $this->written[$parent] ?? null) {
            $this->written[$parent] = (object) ['ids' => [], 'from' => $this->writes];

            return null;
        }
        if ($journal->from > $serial) {
            return null;
        }
        $ids = [];
        for ($write = end($journal->ids); false !== $write && $write > $serial; $write = prev($journal->ids)) {
            // An object id: the key of the write just read, never past the end
            $ids[] = (int) key($journal->ids);
        }

        return $ids;
    }

    /**
     * Record whether a widget was just rendered with its positions tracked.
     *
     * After a render without tracking, the rects of the descendants still come from an earlier frame, so moveSubtree() must not reuse them.
     */
    public function markRendered(AbstractWidget $widget): void
    {
        // Kept for the widgets they were asked for, see wrote(): inline, written for each widget laid out
        if (null !== ($parent = $widget->getParent()) && null !== $journal = $this->written[$parent] ?? null) {
            unset($journal->ids[$id = spl_object_id($widget)]);
            $journal->ids[$id] = ++$this->writes;
        }
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
        $rect = $this->widgetPositions[$widget] ?? null;
        if (null !== $rect && 0 < \count($this->pending) && 0 !== $rows = $this->pendingRows($widget)) {
            $rect = $rect->withOffset($rows, 0);
        }

        return null !== $rect && isset($this->clippedBy[$widget]) ? $this->clipped($widget, $rect) : $rect;
    }

    /**
     * A rect cut by the boxes around it that clip what they hold, where they are now, see clipBy().
     */
    private function clipped(AbstractWidget $widget, WidgetRect $rect): WidgetRect
    {
        if (null !== ($known = $this->clippedRects[$widget] ?? null) && $known[0] === $rect && $known[1] === $this->clipsVersion) {
            return $known[2];
        }
        $clip = null;
        foreach ($this->clippedBy[$widget] as $box) {
            if (null === ($box = $box->get()) || null === $boxClip = $this->contentClips[$box] ?? null) {
                continue;
            }
            if (0 < \count($this->pending) && 0 !== $rows = $this->pendingRows($box)) {
                $boxClip = self::moveClip($boxClip, $rows, 0);
            }
            $clip = null === $clip ? $boxClip : [max($clip[0], $boxClip[0]), max($clip[1], $boxClip[1]), min($clip[2], $boxClip[2]), min($clip[3], $boxClip[3])];
        }
        $this->clippedRects[$widget] = [$rect, $this->clipsVersion, $clipped = null === $clip ? $rect : $rect->withClip($clip)];

        return $clipped;
    }

    /**
     * Sets the clip a box cuts what it holds to for the pointer, in screen cells, see WidgetRect::withClip(): moved with
     * the box from now, those cut by it reading it where it is, see clipBy().
     *
     * @param array{int, int, int, int} $clip
     */
    public function setContentClip(AbstractWidget $box, array $clip): void
    {
        $clip = 0 < \count($this->pending) && 0 !== ($rows = $this->pendingRows($box)) ? self::moveClip($clip, -$rows, 0) : $clip;
        if (($this->contentClips[$box] ?? null) !== $clip) {
            $this->contentClips[$box] = $clip;
            ++$this->clipsVersion;
        }
    }

    /**
     * Cuts the rect of a widget for the pointer by the content clip of a box around it, see setContentClip(): read
     * with its rect, where that box is then; not a write of its rect, see getChildrenWrittenSince(). Until its rect is
     * written again, by the layout around it, which places it anew.
     */
    public function clipBy(AbstractWidget $widget, AbstractWidget $box): void
    {
        if (isset($this->widgetPositions[$widget])) {
            $boxes = $this->clippedBy[$widget] ?? [];
            if (!isset($boxes[$id = spl_object_id($box)])) {
                $boxes[$id] = \WeakReference::create($box);
                $this->clippedBy[$widget] = $boxes;
                unset($this->clippedRects[$widget]);
            }
        }
    }

    /**
     * Forgets where a widget was, not what it holds: a box no longer drawn, its content laid out in its place, like
     * one of display: contents; it takes no pointer.
     */
    public function forgetBox(AbstractWidget $widget): void
    {
        // Its content laid out in its place: drawn, not forgotten, see forgetUndrawn()
        $this->forgotten[$widget] = false;
        if (isset($this->widgetPositions[$widget])) {
            unset($this->widgetPositions[$widget], $this->clippedBy[$widget]);
            $this->wrote($widget);
        }
    }

    /**
     * forget() of a widget not drawn, unless it was forgotten already and not drawn since: a box drawing nothing frame
     * after frame, like a menu of many links closed by a height of 0, is not gone through again.
     */
    public function forgetUndrawn(AbstractWidget $widget): void
    {
        if (true !== ($this->forgotten[$widget] ?? null)) {
            $this->forget($widget);
        }
    }

    /**
     * Forgets where a widget and what it holds were, like one not drawn this frame: they take no pointer, and are
     * tracked again when drawn.
     */
    public function forget(AbstractWidget $widget): void
    {
        unset($this->widgetPositions[$widget], $this->clippedBy[$widget]);
        $this->untracked[$widget] = true;
        $this->forgotten[$widget] = true;
        $this->wrote($widget);
        foreach ($widget instanceof ParentInterface ? $widget->all() : [] as $child) {
            $this->forget($child);
        }
    }

    /**
     * Record a widget's absolute position.
     */
    public function setWidgetRect(AbstractWidget $widget, WidgetRect $rect): void
    {
        if (0 < \count($this->pending) && 0 !== $rows = $this->pendingRows($widget)) {
            $rect = $rect->withOffset(-$rows, 0);
        }
        // Placed anew: no longer cut by the boxes around it until they cut it again, see clipBy()
        $this->widgetPositions[$widget] = $rect;
        unset($this->clippedBy[$widget]);
        $this->forgotten[$widget] = false;
        // Kept for the widgets they were asked for, see wrote(): inline, written for each widget laid out
        if (null !== ($parent = $widget->getParent()) && null !== $journal = $this->written[$parent] ?? null) {
            unset($journal->ids[$id = spl_object_id($widget)]);
            $journal->ids[$id] = ++$this->writes;
        }
    }

    /**
     * Cuts the rect of a widget to a clip for the pointer, see WidgetRect::withClip(): where it is the same, not a write
     * of its rect, see getChildrenWrittenSince(), for a layout that placed it.
     *
     * @param array{int, int, int, int} $clip
     */
    public function clipWidgetRect(AbstractWidget $widget, array $clip): void
    {
        if (null === $rect = $this->widgetPositions[$widget] ?? null) {
            return;
        }
        // Its clip where it is when read, moves it does not tell yet aside
        $rows = 0 < \count($this->pending) ? $this->pendingRows($widget) : 0;
        $this->widgetPositions[$widget] = 0 === $rows ? $rect->withClip($clip) : $rect->withOffset($rows, 0)->withClip($clip)->withOffset(-$rows, 0);
    }

    /**
     * Move a previously tracked widget and its descendants to a new position.
     */
    public function moveSubtree(AbstractWidget $widget, WidgetRect $rect): bool
    {
        if (!isset($this->widgetPositions[$widget]) || isset($this->untracked[$widget])) {
            return false;
        }
        if (0 < \count($this->pending) && 0 !== $rows = $this->pendingRows($widget)) {
            $rect = $rect->withOffset(-$rows, 0);
        }
        $previous = $this->widgetPositions[$widget];
        $rowOffset = $rect->row - $previous->row;
        $colOffset = $rect->col - $previous->col;
        if (0 !== $rowOffset || 0 !== $colOffset) {
            $this->shiftSubtree($widget, $rowOffset, $colOffset);
        }
        $this->widgetPositions[$widget] = $rect;
        $this->wrote($widget);

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
            $this->clipSubtree($child, $rowOffset, $top, $bottom, 0 < \count($this->pending) ? $this->pendingRows($child) : 0);
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
        // The rows moved later and not written yet, see shiftChildrenLater(), left aside: cut sideways, a rect is cut the
        // same at any row, and its clip moves with it
        foreach ($children as $child) {
            $this->clipSubtreeColumns($child, $colOffset, $left, $right);
        }
    }

    private function clipSubtreeColumns(AbstractWidget $widget, int $colOffset, int $left, int $right): void
    {
        if (isset($this->widgetPositions[$widget])) {
            $this->cut($widget, $this->widgetPositions[$widget]->withOffset(0, $colOffset), true, $left, $right, 0);
        }
        if (isset($this->contentClips[$widget])) {
            $this->contentClips[$widget] = self::moveClip($this->contentClips[$widget], 0, $colOffset);
            ++$this->clipsVersion;
        }
        if ($widget instanceof ParentInterface) {
            foreach ($widget->all() as $child) {
                $this->clipSubtreeColumns($child, $colOffset, $left, $right);
            }
        }
    }

    /**
     * @param int $moved The rows it was moved down by that its rect does not tell, see shiftChildrenLater()
     */
    private function clipSubtree(AbstractWidget $widget, int $rowOffset, int $top, int $bottom, int $moved): void
    {
        if (isset($this->widgetPositions[$widget])) {
            $this->cut($widget, $this->widgetPositions[$widget]->withOffset($rowOffset + $moved, 0), false, $top, $bottom, $moved);
        }
        if (isset($this->contentClips[$widget])) {
            $this->contentClips[$widget] = self::moveClip($this->contentClips[$widget], $rowOffset, 0);
            ++$this->clipsVersion;
        }
        // No moves held back for what it holds, see shiftChildrenLater(): the rects a scroll container shows are cut
        // each frame, see cut(), a write of each, so a block in it is laid out anew, none resumed at another row
        if ($widget instanceof ParentInterface) {
            foreach ($widget->all() as $child) {
                $this->clipSubtree($child, $rowOffset, $top, $bottom, $moved);
            }
        }
    }

    /**
     * Clips the rect of a widget to the rows, or the columns, a scroll container shows, the other pieces of the widget,
     * like those in the other columns of a multi-column box, too: the rect stays that of its whole box.
     *
     * @param int $moved The rows it was moved down by that its rect does not tell: kept without them
     */
    private function cut(AbstractWidget $widget, WidgetRect $rect, bool $sideways, int $start, int $end, int $moved): void
    {
        [$from, $size] = $sideways ? [$rect->col, $rect->columns] : [$rect->row, $rect->rows];
        $shownFrom = max($start, $from);
        $shownTo = min($end, $from + $size);
        $this->wrote($widget);
        $fragmentsWhole = true;
        $fragmentShown = false;
        foreach ($rect->fragments as [$fragmentRow, $fragmentCol, $columns, $rows]) {
            [$fragmentFrom, $fragmentSize] = $sideways ? [$from + $fragmentCol, $columns] : [$from + $fragmentRow, $rows];
            $fragmentsWhole = $fragmentsWhole && $fragmentFrom >= $start && $fragmentFrom + $fragmentSize <= $end;
            $fragmentShown = $fragmentShown || (min($end, $fragmentFrom + $fragmentSize) > max($start, $fragmentFrom));
        }
        if ($shownFrom === $from && $shownTo === $from + $size && $fragmentsWhole) {
            $this->widgetPositions[$widget] = 0 === $moved ? $rect : $rect->withOffset(-$moved, 0);

            return;
        }
        // Clipped: its rect no longer tells where its descendants are, they must be tracked again
        $this->untracked[$widget] = true;
        if ($shownTo <= $shownFrom && !$fragmentShown) {
            unset($this->widgetPositions[$widget]);

            return;
        }
        // Its whole box kept, where it is, like a native control drawn at its size: what the container does not show
        // is cut by the clip, and takes no pointer, the pieces of it too
        [$top, $left, $bottom, $right] = [$rect->row, $rect->col, $rect->row + $rect->rows, $rect->col + $rect->columns];
        foreach ($rect->fragments as [$fragmentRow, $fragmentCol, $columns, $rows]) {
            $top = min($top, $rect->row + $fragmentRow);
            $left = min($left, $rect->col + $fragmentCol);
            $bottom = max($bottom, $rect->row + $fragmentRow + $rows);
            $right = max($right, $rect->col + $fragmentCol + $columns);
        }
        $rect = $rect->withClip($sideways ? [$top, $start, $bottom, $end] : [$start, $left, $end, $right]);
        $this->widgetPositions[$widget] = 0 === $moved ? $rect : $rect->withOffset(-$moved, 0);
    }

    private function shiftSubtree(AbstractWidget $widget, int $rowOffset, int $colOffset): void
    {
        if (isset($this->widgetPositions[$widget])) {
            $this->widgetPositions[$widget] = $this->widgetPositions[$widget]->withOffset($rowOffset, $colOffset);
            $this->wrote($widget);
        }
        if (isset($this->contentClips[$widget])) {
            $this->contentClips[$widget] = self::moveClip($this->contentClips[$widget], $rowOffset, $colOffset);
            ++$this->clipsVersion;
        }
        if (!$widget instanceof ParentInterface) {
            return;
        }
        foreach ($widget->all() as $child) {
            $this->shiftSubtree($child, $rowOffset, $colOffset);
        }
    }

    private function wrote(AbstractWidget $widget): void
    {
        // Kept for the widgets they were asked for, see getChildrenWrittenSince()
        if (null !== ($parent = $widget->getParent()) && null !== $journal = $this->written[$parent] ?? null) {
            unset($journal->ids[$id = spl_object_id($widget)]);
            $journal->ids[$id] = ++$this->writes;
        }
    }

    /**
     * The rows a widget was moved down by that its rect does not tell: those of the moves of it, and of its ancestors,
     * not written yet, see shiftChildrenLater().
     */
    private function pendingRows(AbstractWidget $widget): int
    {
        $rows = 0;
        for ($child = $widget; null !== $parent = $child->getParent(); $child = $parent) {
            if (null !== $moves = $this->pending[$parent] ?? null) {
                $rows += self::movedRows($moves, $child);
            }
        }

        return $rows;
    }

    /**
     * A clip moved on screen, its open sides, like the rows of a box that clips across only, left as they are.
     *
     * @param array{int, int, int, int} $clip
     *
     * @return array{int, int, int, int}
     */
    private static function moveClip(array $clip, int $rows, int $columns): array
    {
        return [
            \PHP_INT_MIN === $clip[0] ? $clip[0] : $clip[0] + $rows,
            \PHP_INT_MIN === $clip[1] ? $clip[1] : $clip[1] + $columns,
            \PHP_INT_MAX === $clip[2] ? $clip[2] : $clip[2] + $rows,
            \PHP_INT_MAX === $clip[3] ? $clip[3] : $clip[3] + $columns,
        ];
    }

    /**
     * @param list<array{array<int, int>, int}> $moves
     */
    private static function movedRows(array $moves, AbstractWidget $child): int
    {
        $rows = 0;
        $id = spl_object_id($child);
        foreach ($moves as [$ids, $shift]) {
            if (isset($ids[$id])) {
                $rows += $shift;
            }
        }

        return $rows;
    }
}
