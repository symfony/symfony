<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Tui\Tests\Html;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Tui\Html\HtmlBuilder;
use Symfony\Component\Tui\Terminal\VirtualTerminal;
use Symfony\Component\Tui\Tui;

final class CollapsedTableTest extends TestCase
{
    public function testTheCaptionsOfASeparateTableAreWhereTheyAreDrawnOutsideItsBorder()
    {
        $tui = new Tui(terminal: new VirtualTerminal(40, 10));
        $view = (new HtmlBuilder())->build('<table style="border: 1px solid"><caption id="top">Above</caption><tr><td id="cell">cell</td></tr><caption id="below" style="caption-side: bottom">Below</caption></table>')->mount($tui);
        $tui->start();
        $tui->processRender();

        $top = $tui->getWidgetRect($view->getById('top'));
        $cell = $tui->getWidgetRect($view->getById('cell'));
        $below = $tui->getWidgetRect($view->getById('below'));
        $this->assertSame([0, 0], [$top->row, $top->col], 'Above the border.');
        $this->assertSame([2, 1], [$cell->row, $cell->col], 'Inside it.');
        $this->assertSame([4, 0], [$below->row, $below->col], 'Below it.');
        $this->assertSame($top->columns, $below->columns, 'As wide as the box.');
    }

    public function testCellsAreWhereTheyAreDrawn()
    {
        $tui = new Tui(terminal: new VirtualTerminal(40, 10));
        $view = (new HtmlBuilder())->build('<style>table { border-collapse: collapse } td { border: 1px solid }</style><table id="t"><tr><td id="a">Ada</td><td id="b">Admin</td></tr><tr><td id="c">Grace</td><td id="d">Dev</td></tr></table>')->mount($tui);
        $tui->start();
        $tui->processRender();

        $rect = $tui->getWidgetRect($view->getById('d'));
        $this->assertSame([3, 7, 5, 1], [$rect?->row, $rect?->col, $rect?->columns, $rect?->rows], 'Past the top line, the first column and the line between.');
        // The deepest widget is the text of the cell
        $this->assertSame('a', $view->widgetAt(1, 2)?->getParent()?->getId());
        $this->assertSame('d', $view->widgetAt(3, 8)?->getParent()?->getId());
        $this->assertSame('t', $view->widgetAt(2, 3)?->getId() ?? $view->widgetAt(2, 3)?->getParent()?->getId(), 'A line between rows is the row group\'s, in the table.');
        $tui->stop();
    }
}
