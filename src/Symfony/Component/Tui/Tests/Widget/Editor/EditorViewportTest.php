<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Tui\Tests\Widget\Editor;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Tui\Widget\Editor\EditorViewport;

class EditorViewportTest extends TestCase
{
    public function testComputeViewportKeepsCursorVisible()
    {
        $viewport = new EditorViewport();
        $lines = [];
        for ($i = 0; $i < 50; ++$i) {
            $lines[] = "Line $i";
        }

        // Cursor at line 20, viewport shows 10 rows
        $result = $viewport->computeViewport($lines, 20, 10, 80, false, 1);

        $this->assertGreaterThanOrEqual($result['scroll_offset'], 20);
        $this->assertLessThan($result['scroll_offset'] + $result['visible_line_count'], 20);
    }

    public function testComputeViewportScrollsUpWhenCursorAbove()
    {
        $viewport = new EditorViewport();
        $lines = [];
        for ($i = 0; $i < 50; ++$i) {
            $lines[] = "Line $i";
        }

        // First, scroll to line 20
        $viewport->computeViewport($lines, 20, 10, 80, false, 1);

        // Now cursor goes back to line 0
        $result = $viewport->computeViewport($lines, 0, 10, 80, false, 1);

        $this->assertSame(0, $result['scroll_offset']);
    }

    public function testComputeViewportExpandedMode()
    {
        $viewport = new EditorViewport();
        $lines = ['Line 1', 'Line 2'];

        $result = $viewport->computeViewport($lines, 0, 20, 80, true, 1);

        // In expanded mode, visibleLineCount should fill available space
        $this->assertSame(2, $result['visible_line_count']);
    }

    public function testComputeViewportReportsLinesAboveAndBelow()
    {
        $viewport = new EditorViewport();
        $lines = [];
        for ($i = 0; $i < 30; ++$i) {
            $lines[] = "Line $i";
        }

        $result = $viewport->computeViewport($lines, 15, 10, 80, false, 1);

        $this->assertGreaterThan(0, $result['lines_above']);
        $this->assertGreaterThan(0, $result['lines_below']);
    }

    public function testPageScrollDown()
    {
        $viewport = new EditorViewport();
        $lines = [];
        for ($i = 0; $i < 50; ++$i) {
            $lines[] = "Line $i";
        }

        $result = $viewport->pageScroll($lines, 1, 10, 0, 0);

        $this->assertNotNull($result);
        $this->assertSame(10, $result['cursor_line']);
    }

    public function testPageScrollUp()
    {
        $viewport = new EditorViewport();
        $lines = [];
        for ($i = 0; $i < 50; ++$i) {
            $lines[] = "Line $i";
        }

        $result = $viewport->pageScroll($lines, -1, 10, 20, 0);

        $this->assertNotNull($result);
        $this->assertSame(10, $result['cursor_line']);
    }

    public function testPageScrollClampsToEnd()
    {
        $viewport = new EditorViewport();
        $lines = ['A', 'B', 'C'];

        $result = $viewport->pageScroll($lines, 1, 100, 0, 0);

        $this->assertNotNull($result);
        $this->assertSame(2, $result['cursor_line']);
    }

    public function testPageScrollReturnsNullWhenNoChange()
    {
        $viewport = new EditorViewport();
        $lines = ['A'];

        $result = $viewport->pageScroll($lines, 1, 10, 0, 0);

        $this->assertNull($result);
    }

    public function testReset()
    {
        $viewport = new EditorViewport();

        // Scroll via pageScroll to change offset, then reset
        $lines = [];
        for ($i = 0; $i < 20; ++$i) {
            $lines[] = "Line $i";
        }

        // computeViewport with cursor at line 15 will adjust scrollOffset
        $viewport->computeViewport($lines, 15, 5, 80, false, 0);
        $this->assertGreaterThan(0, $viewport->getScrollOffset());

        $viewport->reset();
        $this->assertSame(0, $viewport->getScrollOffset());
    }
}
