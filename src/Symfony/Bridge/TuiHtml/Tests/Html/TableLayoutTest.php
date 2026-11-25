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
use Symfony\Bridge\TuiHtml\Html\HtmlView;
use Symfony\Bridge\TuiHtml\Render\LayoutEngine;
use Symfony\Bridge\TuiHtml\Tests\Fixtures\HtmlTui as Tui;
use Symfony\Component\Tui\Terminal\ScreenBuffer;
use Symfony\Component\Tui\Terminal\VirtualTerminal;

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
        $this->assertSame(['┌───┐        ┌─────┐', '│one│        │three│', '└───┘        └─────┘', '      ┌────┐ ┌─────┐', '      │five│ │six  │', '      └────┘ └─────┘'], $this->screen(6));
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
        $this->assertSame(['┌─────┐ ┌──────┐ ┌───┐ ┌──────┐', '│one  │ │      │ │top│ │      │', '│two  │ │middle│ │   │ │      │', '│three│ │      │ │   │ │      │', '│four │ │      │ │   │ │bottom│', '└─────┘ └──────┘ └───┘ └──────┘'], $this->screen(6));
        $this->assertSame(2, $this->tui->getWidgetRect($view->getById('m')->all()[0])?->row, 'Its content is tracked where it is drawn.');
    }

    public function testCollapsedCellsAreAlignedInTheRowsTheySpan()
    {
        $view = $this->render('<style>table { border-collapse: collapse } td { border: 1px solid } .bottom { vertical-align: bottom }</style><table><tr><td rowspan="3" id="s">span</td><td>a</td><td rowspan="3" class="bottom">end</td></tr><tr><td>b<br>b</td></tr><tr><td>c</td></tr></table>');
        $this->assertSame(['┌────┬─┬───┐', '│    │a│   │', '│    ├─┤   │', '│span│b│   │', '│    │b│   │', '│    ├─┤   │', '│    │c│end│', '└────┴─┴───┘'], $this->screen(8));
        $this->assertSame(3, $this->tui->getWidgetRect($view->getById('s')->all()[0])?->row);
    }

    public function testTheHeightOfATableIsALeastOne()
    {
        $terminal = new VirtualTerminal(20, 8);
        $tui = new Tui(null, $terminal);
        (new HtmlBuilder())->setDefaultStyles(false)->build('<table style="height: 3; border-spacing: 0"><tr><td>a<tr><td>b<tr><td>c<tr><td>d</table><p>after</p>')->mount($tui);
        $tui->start();
        $tui->processRender();
        $tui->stop();
        $screen = new ScreenBuffer(20, 8);
        $screen->write($terminal->getOutput());
        $this->assertSame(['a', 'b', 'c', 'd', 'after'], \array_slice(array_map('rtrim', explode("\n", $screen->getScreen())), 0, 5), 'Like CSS: its rows grow it past its height.');
    }

    public function testACellSpanningColumnsHasTheBackgroundOfEachOfThem()
    {
        $backgrounds = self::backgrounds('<table style="border-spacing: 0"><col style="background: #ff0000"><col><col style="background: #0000ff"><tr><td>aaaa</td><td>bb</td><td>cccc</td></tr><tr><td colspan="3">spanning cell</td></tr><tr><td colspan="2" style="background: #00ff00">own</td><td>c</td></tr></table>');
        // The cells of the first row are each in one column, with its background
        $this->assertSame(['#ff0000', '', '#0000ff'], array_values(array_unique($backgrounds[0])));
        $this->assertSame($backgrounds[0], $backgrounds[1], 'Like CSS: each column under the part of the spanning cell in it, none where the column has none.');
        $own = array_search('#0000ff', $backgrounds[0], true);
        $this->assertSame([...array_fill(0, $own, '#00ff00'), ...\array_slice($backgrounds[0], $own)], $backgrounds[2], 'Not under its own background.');
    }

    /**
     * The background color of each cell of the first rows drawn, '' for none.
     *
     * @return list<list<string>>
     */
    private static function backgrounds(string $html): array
    {
        $terminal = new VirtualTerminal(30, 5);
        $tui = new Tui(null, $terminal);
        (new HtmlBuilder())->setDefaultStyles(false)->build($html)->mount($tui);
        $tui->start();
        $tui->processRender();
        $tui->stop();
        $screen = new ScreenBuffer(30, 5);
        $screen->write($terminal->getOutput());
        $rows = [];
        foreach (\array_slice(explode("\n", $screen->getStyledScreen()), 0, 3) as $line) {
            $cells = [];
            $background = '';
            foreach (preg_split('/(\e\[[0-9;]*m)/', $line, -1, \PREG_SPLIT_DELIM_CAPTURE | \PREG_SPLIT_NO_EMPTY) as $part) {
                if (preg_match('/^\e\[([0-9;]*)m$/', $part, $code)) {
                    if (preg_match('/(?:^|;)48;2;(\d+);(\d+);(\d+)/', $code[1], $rgb)) {
                        $background = \sprintf('#%02x%02x%02x', $rgb[1], $rgb[2], $rgb[3]);
                    } elseif (preg_match('/(?:^|;)(?:0|49)?$/', $code[1])) {
                        $background = '';
                    }
                    continue;
                }
                array_push($cells, ...array_fill(0, mb_strlen($part), $background));
            }
            $rows[] = $cells;
        }

        return $rows;
    }

    private function screen(int $rows): array
    {
        $buffer = new ScreenBuffer(60, 20);
        $buffer->write($this->tui->getTerminal()->getOutput());

        return array_map(rtrim(...), \array_slice(explode("\n", $buffer->getScreen()), 0, $rows));
    }

    private function render(string $html): HtmlView
    {
        $this->tui?->stop();
        $this->tui = new Tui(null, new VirtualTerminal(60, 20));
        $view = (new HtmlBuilder())->build($html)->mount($this->tui);
        $this->tui->start();
        $this->tui->processRender();

        return $view;
    }

    private function widths(HtmlView $view, string ...$ids): array
    {
        return array_map(fn (string $id) => $this->tui->getWidgetRect($view->getById($id))?->columns, $ids);
    }
}
