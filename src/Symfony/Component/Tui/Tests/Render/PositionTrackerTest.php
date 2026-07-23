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
    // shiftDescendantPositions
    // ---------------------------------------------------------------

    public function testShiftDescendantPositions()
    {
        $tracker = new PositionTracker();
        $widgetOld = new TextWidget('old');
        $widgetNew = new TextWidget('new');

        $tracker->setWidgetRect($widgetOld, new WidgetRect(0, 0, 10, 1));
        $snapshot = $tracker->snapshotKeys();

        $tracker->setWidgetRect($widgetNew, new WidgetRect(2, 3, 10, 1));

        $tracker->shiftDescendantPositions($snapshot, 5, 10);

        // Old widget should be unchanged
        $oldRect = $tracker->getWidgetRect($widgetOld);
        $this->assertSame(0, $oldRect->row);
        $this->assertSame(0, $oldRect->col);

        // New widget should be shifted
        $newRect = $tracker->getWidgetRect($widgetNew);
        $this->assertSame(12, $newRect->row);  // 2 + 10
        $this->assertSame(8, $newRect->col);    // 3 + 5
    }

    public function testShiftDescendantPositionsWithNullSnapshotIsNoop()
    {
        $tracker = new PositionTracker();
        $widget = new TextWidget('a');
        $tracker->setWidgetRect($widget, new WidgetRect(2, 3, 10, 1));

        $tracker->shiftDescendantPositions(null, 5, 10);

        $rect = $tracker->getWidgetRect($widget);
        $this->assertSame(2, $rect->row);
        $this->assertSame(3, $rect->col);
    }
}
