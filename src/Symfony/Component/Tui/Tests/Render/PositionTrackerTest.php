<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Tui\Tests\Render;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Tui\Render\PositionTracker;
use Symfony\Component\Tui\Render\WidgetRect;
use Symfony\Component\Tui\Widget\ContainerWidget;
use Symfony\Component\Tui\Widget\TextWidget;

/**
 * @author Fabien Potencier <fabien@symfony.com>
 */
final class PositionTrackerTest extends TestCase
{
    // ---------------------------------------------------------------
    // Widget position tracking
    // ---------------------------------------------------------------

    public function testGetWidgetRectReturnsNullForUntracked()
    {
        $tracker = new PositionTracker();
        $widget = new TextWidget('hello');

        $this->assertNull($tracker->getWidgetRect($widget));
    }

    public function testSetAndGetWidgetRect()
    {
        $tracker = new PositionTracker();
        $widget = new TextWidget('hello');
        $rect = new WidgetRect(5, 10, 20, 3);

        $tracker->setWidgetRect($widget, $rect);

        $this->assertSame($rect, $tracker->getWidgetRect($widget));
    }

    public function testResetPreservesTrackedWidgets()
    {
        $tracker = new PositionTracker();
        $widget = new TextWidget('hello');
        $tracker->setWidgetRect($widget, new WidgetRect(0, 0, 10, 1));

        $tracker->reset();

        // Positions are preserved across reset() so that cached subtrees
        // (which skip re-rendering) keep their tracked rects.
        $this->assertNotNull($tracker->getWidgetRect($widget));
    }

    // ---------------------------------------------------------------
    // Position stack
    // ---------------------------------------------------------------

    public function testPushAndCurrentOffset()
    {
        $tracker = new PositionTracker();
        $tracker->reset();
        $tracker->push(5, 10);

        $this->assertSame([5, 10], $tracker->currentOffset());
    }

    public function testPopRestoresPreviousOffset()
    {
        $tracker = new PositionTracker();
        $tracker->reset();
        $tracker->push(5, 10);
        $tracker->push(15, 20);

        $tracker->pop();

        $this->assertSame([5, 10], $tracker->currentOffset());
    }

    public function testPopDoesNotRemoveLastEntry()
    {
        $tracker = new PositionTracker();
        $tracker->reset();

        // Pop on single-entry stack should be a no-op
        $tracker->pop();

        $this->assertTrue($tracker->isActive());
        $this->assertSame([0, 0], $tracker->currentOffset());
    }

    // ---------------------------------------------------------------
    // Suppress and restore stack
    // ---------------------------------------------------------------

    public function testSuppressAndRestoreStack()
    {
        $tracker = new PositionTracker();
        $tracker->reset();
        $tracker->push(3, 7);

        $saved = $tracker->suppressStack();

        $this->assertFalse($tracker->isActive());

        $tracker->restoreStack($saved);

        $this->assertTrue($tracker->isActive());
        $this->assertSame([3, 7], $tracker->currentOffset());
    }

    // ---------------------------------------------------------------
    // shiftContentPositions
    // ---------------------------------------------------------------

    public function testShiftContentPositionsShiftsChildrenAndTheirDescendants()
    {
        $tracker = new PositionTracker();
        $outsider = new TextWidget('outsider');
        $child = new ContainerWidget();
        $leaf = new TextWidget('leaf');
        $child->add($leaf);

        $tracker->setWidgetRect($outsider, new WidgetRect(0, 0, 10, 1));
        $tracker->setWidgetRect($child, new WidgetRect(2, 3, 10, 1));
        $tracker->setWidgetRect($leaf, new WidgetRect(2, 3, 10, 1));

        $tracker->shiftContentPositions([$child], 5, 10);

        $outsiderRect = $tracker->getWidgetRect($outsider);
        $this->assertSame([0, 0], [$outsiderRect->row, $outsiderRect->col]);
        $childRect = $tracker->getWidgetRect($child);
        $this->assertSame([12, 8], [$childRect->row, $childRect->col]);
        $leafRect = $tracker->getWidgetRect($leaf);
        $this->assertSame([12, 8], [$leafRect->row, $leafRect->col]);
    }

    public function testShiftContentPositionsWithoutOffsetIsNoop()
    {
        $tracker = new PositionTracker();
        $widget = new TextWidget('a');
        $tracker->setWidgetRect($widget, new WidgetRect(2, 3, 10, 1));

        $tracker->shiftContentPositions([$widget], 0, 0);

        $rect = $tracker->getWidgetRect($widget);
        $this->assertSame([2, 3], [$rect->row, $rect->col]);
    }

    public function testMoveSubtreeShiftsTrackedDescendants()
    {
        $tracker = new PositionTracker();
        $root = new ContainerWidget();
        $inner = new ContainerWidget();
        $leaf = new TextWidget('leaf');
        $inner->add($leaf);
        $root->add($inner);

        $tracker->setWidgetRect($root, new WidgetRect(1, 2, 20, 4));
        $tracker->setWidgetRect($inner, new WidgetRect(2, 3, 18, 2));
        $tracker->setWidgetRect($leaf, new WidgetRect(3, 4, 16, 1));

        $this->assertTrue($tracker->moveSubtree($root, new WidgetRect(5, 7, 20, 4)));

        $rootRect = $tracker->getWidgetRect($root);
        $this->assertSame([5, 7, 20, 4], [$rootRect->row, $rootRect->col, $rootRect->columns, $rootRect->rows]);
        $innerRect = $tracker->getWidgetRect($inner);
        $this->assertSame([6, 8, 18, 2], [$innerRect->row, $innerRect->col, $innerRect->columns, $innerRect->rows]);
        $leafRect = $tracker->getWidgetRect($leaf);
        $this->assertSame([7, 9, 16, 1], [$leafRect->row, $leafRect->col, $leafRect->columns, $leafRect->rows]);
    }

    public function testMoveUntrackedSubtreeReturnsFalse()
    {
        $tracker = new PositionTracker();
        $root = new ContainerWidget();

        $this->assertFalse($tracker->moveSubtree($root, new WidgetRect(5, 7, 20, 4)));
    }
}
