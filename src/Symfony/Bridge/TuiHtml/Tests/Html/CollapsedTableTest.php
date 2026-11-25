<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\TuiHtml\Tests\Html;

use PHPUnit\Framework\TestCase;
use Symfony\Bridge\TuiHtml\Html\HtmlBuilder;
use Symfony\Bridge\TuiHtml\Tests\Fixtures\HtmlTui as Tui;
use Symfony\Component\Tui\Terminal\ScreenBuffer;
use Symfony\Component\Tui\Terminal\VirtualTerminal;

final class CollapsedTableTest extends TestCase
{
    public function testTheCaptionsOfASeparateTableAreWhereTheyAreDrawnOutsideItsBorder()
    {
        $tui = new Tui(null, new VirtualTerminal(40, 10));
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
        $tui = new Tui(null, new VirtualTerminal(40, 10));
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

    public function testACellSpansNoRowsPastItsRowGroup()
    {
        $html = '<table><thead><tr><td rowspan="2">A</td><td>h</td></tr></thead><tbody><tr><td>b1</td><td>b2</td></tr></tbody></table>';
        $this->assertSame(['┌──┬──┐', '│A │h │', '├──┼──┤', '│b1│b2│', '└──┴──┘'], $this->render('<style>table { border-collapse: collapse } td { border: 1px solid }</style>'.$html));
        $this->assertSame(['A  h', 'b1 b2'], $this->render('<style>td { padding: 0 } caption { text-align: left }</style>'.$html));
    }

    public function testAColumnIsAsWideAsItsColElementSays()
    {
        $this->assertSame(['┌────────┬─┐', '│a       │b│', '└────────┴─┘'], $this->render('<style>table { border-collapse: collapse } td { border: 1px solid }</style><table><colgroup><col style="width: 8ch"><col></colgroup><tr><td>a</td><td>b</td></tr></table>'));
    }

    public function testTheColumnsOfNoSetWidthTakeTheRoomOfAWiderTable()
    {
        $this->assertSame(['┌────────┬─────────┐', '│a       │b        │', '└────────┴─────────┘'], $this->render('<style>table { border-collapse: collapse; width: 20ch } td { border: 1px solid }</style><table><tr><td style="width: 8ch">a</td><td>b</td></tr></table>'));
    }

    public function testAShrunkTableKeepsTheLongestWordOfACellSpanningColumns()
    {
        $html = '<table><tr><td colspan="2">Supercalifragil</td><td>z</td></tr><tr><td>x</td><td>a b c d e f g h i j</td><td>a b c d e f g h i j k l m n o p</td></tr></table>';
        $this->assertSame('│Supercalifragil│z │', $this->render('<style>table { border-collapse: collapse } td { border: 1px solid }</style>'.$html, 20)[1]);
        $this->assertSame('Supercalifragil z', $this->render('<style>td { padding: 0 } tr { gap: 1ch }</style>'.$html, 20)[0]);
    }

    public function testACellSpanningMoreRowsIsNotMadeTallerThanItNeeds()
    {
        $this->assertSame(['┌─┬─┬─┐', '│1│a│x│', '│2│b├─┤', '│3│c│y│', '│4│d│ │', '│5├─┼─┤', '│6│p│z│', '│7│ │ │', '└─┴─┴─┘'], $this->render('<style>table { border-collapse: collapse } td { border: 1px solid; vertical-align: top }</style><table><tr><td rowspan="3">1<br>2<br>3<br>4<br>5<br>6<br>7</td><td rowspan="2">a<br>b<br>c<br>d</td><td>x</td></tr><tr><td>y</td></tr><tr><td>p</td><td>z</td></tr></table>', 12));
    }

    public function testARowspanOfZeroSpansTheRowsLeftInItsGroup()
    {
        $html = '<table><tbody><tr><td rowspan="0">A</td><td>1</td></tr><tr><td>2</td></tr></tbody><tbody><tr><td>x</td><td>3</td></tr></tbody></table>';
        $this->assertSame(['┌─┬─┐', '│ │1│', '│A├─┤', '│ │2│', '├─┼─┤', '│x│3│', '└─┴─┘'], $this->render('<style>table { border-collapse: collapse } td { border: 1px solid }</style>'.$html));
        $this->assertSame(['A 1', '  2', 'x 3'], $this->render('<style>td { padding: 0 } tr { gap: 1ch }</style>'.$html));
    }

    public function testTheHeaderGroupIsDrawnFirstAndTheFooterGroupLast()
    {
        $html = '<table><caption>Cap</caption><tfoot><tr><td>foot</td></tr></tfoot><tbody><tr><td>body</td></tr></tbody><thead><tr><td>head</td></tr></thead><thead><tr><td>head2</td></tr></thead></table>';
        $this->assertSame(['Cap', 'head', 'body', 'head2', 'foot'], $this->render('<style>td { padding: 0 } caption { text-align: left }</style>'.$html));
        $this->assertSame(['Cap', '┌─────┐', '│head │', '├─────┤', '│body │', '├─────┤', '│head2│', '├─────┤', '│foot │', '└─────┘'], $this->render('<style>table { border-collapse: collapse } td { border: 1px solid } caption { text-align: left }</style>'.$html, 12));
    }

    public function testTheHeightOfARowIsALeastOne()
    {
        $this->assertSame(['┌───┐', '│  a│', '│i b│', '│  c│', '└───┘'], $this->render('<style>td { padding: 0; vertical-align: middle }</style><table style="border: 1px solid"><tr style="height: 2"><td>i</td><td>a<br>b<br>c</td></tr></table>'));
        $this->assertSame(['┌───┐', '│i  │', '│   │', '│  a│', '└───┘'], $this->render('<style>td { padding: 0 }</style><table style="border: 1px solid"><tr style="height: 3"><td style="vertical-align: top">i</td><td style="vertical-align: bottom">a</td></tr></table>'), 'Its cells as tall as it.');
    }

    public function testATableIsNeverNarrowerThanTheLongestWordsOfItsCells()
    {
        $html = '<table><tr><td>aaaa</td><td>bbbbbbb cc</td></tr></table>';
        $this->assertSame(['aaaa bbbbbbb', '     cc'], $this->render('<style>table { width: 5ch } td { padding: 0 } tr { gap: 1ch }</style>'.$html));
        $this->assertSame(['┌────┬───────┐', '│aaaa│bbbbbbb│', '│    │cc     │', '└────┴───────┘'], $this->render('<style>table { border-collapse: collapse; max-width: 5ch } td { border: 1px solid }</style>'.$html));
    }

    public function testATableScrolledSidewaysIsAsWideAsTheLongestWordsOfItsRows()
    {
        $this->assertSame(['Types and Debian_Fre', 'standards'], \array_slice($this->render('<style>table { display: block; overflow-x: auto } th { white-space: nowrap; padding: 0 } td { padding: 0 } tr { gap: 1ch }</style><table><tr><th>Types and<br>standards</th><td>Debian_Free</td></tr></table>'), 0, 2), 'Cut at its window, not its columns.');
    }

    public function testAFooterGroupOutOfATableStaysInItsPlace()
    {
        $this->assertSame(['F B'], $this->render('<div style="display: flex; gap: 1ch"><span style="display: table-footer-group">F</span><span style="display: table-row-group">B</span></div>'), 'An item of a flex row.');
        $this->assertSame(['F  B'], $this->render('<div style="display: grid; grid-template-columns: 3ch 3ch"><span style="display: table-footer-group">F</span><span style="display: table-row-group">B</span></div>'), 'An item of a grid.');
    }

    public function testTheRowsOfAGroupWithoutABoxAreThoseOfTheTable()
    {
        $html = '<table><tr><td rowspan="2">A<br>B</td><td>1</td></tr><tr><td>2</td></tr></table>';
        $this->assertSame(['┌─┬─┐', '│A│1│', '│B├─┤', '│ │2│', '└─┴─┘'], $this->render('<style>table { border-collapse: collapse } tbody { display: contents } td { border: 1px solid }</style>'.$html));
        $this->assertSame(['A 1', 'B 2'], $this->render('<style>tbody { display: contents } td { padding: 0 } tr { gap: 1ch }</style>'.$html));
    }

    private function render(string $html, int $rows = 8): array
    {
        $terminal = new VirtualTerminal(20, $rows);
        $tui = new Tui(null, $terminal);
        (new HtmlBuilder())->setDefaultStyles(false)->build($html)->mount($tui);
        $tui->start();
        $tui->processRender();
        $buffer = new ScreenBuffer(20, $rows);
        $buffer->write($terminal->getOutput());
        $tui->stop();

        return array_values(array_filter(array_map(rtrim(...), explode("\n", $buffer->getScreen())), static fn (string $line) => '' !== $line));
    }
}
