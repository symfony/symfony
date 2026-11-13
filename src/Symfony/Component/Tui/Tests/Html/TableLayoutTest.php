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
use Symfony\Component\Tui\Html\HtmlView;
use Symfony\Component\Tui\Render\LayoutEngine;
use Symfony\Component\Tui\Terminal\ScreenBuffer;
use Symfony\Component\Tui\Terminal\VirtualTerminal;
use Symfony\Component\Tui\Tui;

final class TableLayoutTest extends TestCase
{
    private const ROWS = '<tr><td id="a">a</td><td id="b">bb</td><td id="c">c</td></tr><tr><td id="d">a very long cell content here</td><td>x</td><td>y</td></tr>';

    private ?Tui $tui = null;

    protected function tearDown(): void
    {
        $this->tui?->stop();
    }

    public function testAFixedTableSharesItsWidthEvenly()
    {
        $view = $this->render('<style>table { table-layout: fixed; width: 40ch }</style><table>'.self::ROWS.'</table>');

        // 40 columns, less the two gaps between the cells
        $this->assertSame([13, 13, 12], $this->widths($view, 'a', 'b', 'c'), 'The long cell of the second row does not widen its column.');
        $this->assertSame(13, $this->tui->getWidgetRect($view->getById('d'))?->columns, 'It wraps in its column.');
    }

    public function testTheFirstRowAndTheColumnsSetTheWidths()
    {
        $view = $this->render('<style>table { table-layout: fixed; width: 40ch } #a { width: 8ch }</style><table>'.self::ROWS.'</table>');
        $this->assertSame([8, 15, 15], $this->widths($view, 'a', 'b', 'c'));

        $view = $this->render('<style>table { table-layout: fixed; width: 40ch } #d { width: 30ch }</style><table><col style="width: 20ch">'.self::ROWS.'</table>');
        $this->assertSame([20, 9, 9], $this->widths($view, 'a', 'b', 'c'), 'The <col> width, not that of a cell after the first row.');
    }

    public function testWithoutAWidthTheLayoutIsAuto()
    {
        $view = $this->render('<style>table { table-layout: fixed }</style><table>'.self::ROWS.'</table>');

        $this->assertSame([29, 2, 1], $this->widths($view, 'a', 'b', 'c'));
    }

    public function testAPercentageWidthIsAWidth()
    {
        $view = $this->render('<style>table { table-layout: fixed; width: 50% }</style><table>'.self::ROWS.'</table>');

        $this->assertSame([10, 9, 9], $this->widths($view, 'a', 'b', 'c'), '30 columns of 60.');
    }

    public function testACollapsedFixedTable()
    {
        $view = $this->render('<style>table { table-layout: fixed; width: 40ch; border-collapse: collapse } td { border: 1px solid }</style><table>'.self::ROWS.'</table>');

        // 40 columns, less the outer lines and the two between the cells
        $this->assertSame([12, 12, 12], $this->widths($view, 'a', 'b', 'c'));
    }

    public function testSharingTheWidths()
    {
        $this->assertSame([10, 10, 10], LayoutEngine::shareFixedWidths(3, 30, []));
        $this->assertSame([11, 10, 10], LayoutEngine::shareFixedWidths(3, 31, []), 'The columns left over go to the first columns.');
        $this->assertSame([20, 5, 5], LayoutEngine::shareFixedWidths(3, 30, [0 => 20]));
        $this->assertSame([20, 10], LayoutEngine::shareFixedWidths(2, 30, [0 => 10, 1 => 5]), 'Every column set and narrower: the rest goes to them by their widths.');
        $this->assertSame(30, array_sum(LayoutEngine::shareFixedWidths(3, 30, [0 => 40, 1 => 40])), 'Too wide: the set widths shrink.');
        $this->assertSame(1, LayoutEngine::shareFixedWidths(3, 30, [0 => 40, 1 => 40])[2], 'Each other column keeps a cell.');
    }

    public function testEmptyCellsCanBeHidden()
    {
        $rows = '<tr><td>one</td><td id="empty"></td><td>three</td></tr><tr><td> </td><td>five</td><td>six</td></tr>';
        $view = $this->render('<style>table { empty-cells: hide } td { border: 1px solid }</style><table>'.$rows.'</table>');

        $this->assertSame([
            '┌───┐        ┌─────┐',
            '│one│        │three│',
            '└───┘        └─────┘',
            '      ┌────┐ ┌─────┐',
            '      │five│ │six  │',
            '      └────┘ └─────┘',
        ], $this->screen(6));
        $this->assertSame(6, $this->tui->getWidgetRect($view->getById('empty'))?->columns, 'It keeps its room.');
    }

    public function testEmptyCellsOfACollapsedTableAreDrawn()
    {
        $this->render('<style>table { empty-cells: hide; border-collapse: collapse } td { border: 1px solid }</style><table><tr><td>one</td><td></td></tr></table>');

        $this->assertSame(['┌───┬─┐', '│one│ │', '└───┴─┘'], $this->screen(3), 'Its column is one cell wide, between lines.');
    }

    public function testCellsAreInTheMiddleOfTheirRowByDefault()
    {
        $view = $this->render('<style>td { border: 1px solid } .top { vertical-align: top } .bottom { vertical-align: bottom }</style><table><tr><td>one<br>two<br>three<br>four</td><td id="m">middle</td><td class="top">top</td><td class="bottom">bottom</td></tr></table>');

        $this->assertSame([
            '┌─────┐ ┌──────┐ ┌───┐ ┌──────┐',
            '│one  │ │      │ │top│ │      │',
            '│two  │ │middle│ │   │ │      │',
            '│three│ │      │ │   │ │      │',
            '│four │ │      │ │   │ │bottom│',
            '└─────┘ └──────┘ └───┘ └──────┘',
        ], $this->screen(6));
        $this->assertSame(2, $this->tui->getWidgetRect($view->getById('m')->all()[0])?->row, 'Its content is tracked where it is drawn.');
    }

    public function testCollapsedCellsAreAlignedInTheRowsTheySpan()
    {
        $view = $this->render('<style>table { border-collapse: collapse } td { border: 1px solid } .bottom { vertical-align: bottom }</style><table><tr><td rowspan="3" id="s">span</td><td>a</td><td rowspan="3" class="bottom">end</td></tr><tr><td>b<br>b</td></tr><tr><td>c</td></tr></table>');

        $this->assertSame([
            '┌────┬─┬───┐',
            '│    │a│   │',
            '│    ├─┤   │',
            '│span│b│   │',
            '│    │b│   │',
            '│    ├─┤   │',
            '│    │c│end│',
            '└────┴─┴───┘',
        ], $this->screen(8));
        $this->assertSame(3, $this->tui->getWidgetRect($view->getById('s')->all()[0])?->row);
    }

    /**
     * @return list<string>
     */
    private function screen(int $rows): array
    {
        $buffer = new ScreenBuffer(60, 20);
        $buffer->write((fn () => $this->terminal->getOutput())->call($this->tui));

        return array_map(rtrim(...), \array_slice(explode("\n", $buffer->getScreen()), 0, $rows));
    }

    private function render(string $html): HtmlView
    {
        $this->tui?->stop();
        $this->tui = new Tui(terminal: new VirtualTerminal(60, 20));
        $view = (new HtmlBuilder())->build($html)->mount($this->tui);
        $this->tui->start();
        $this->tui->processRender();

        return $view;
    }

    /**
     * @return list<int|null>
     */
    private function widths(HtmlView $view, string ...$ids): array
    {
        return array_map(fn (string $id) => $this->tui->getWidgetRect($view->getById($id))?->columns, $ids);
    }
}
