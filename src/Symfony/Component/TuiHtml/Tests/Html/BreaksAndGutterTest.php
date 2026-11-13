<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\TuiHtml\Tests\Html;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Tui\Terminal\ScreenBuffer;
use Symfony\Component\Tui\Terminal\VirtualTerminal;
use Symfony\Component\TuiHtml\Html\HtmlBuilder;
use Symfony\Component\TuiHtml\Tests\Fixtures\HtmlTui as Tui;

final class BreaksAndGutterTest extends TestCase
{
    public function testAForcedColumnBreak()
    {
        $screen = $this->render('<style>.cols { columns: 3; column-gap: 1 } p { margin: 0 } .new { break-before: column } .last { break-after: column }</style>
            <div class="cols"><p>a</p><p>b</p><p class="new">c</p><p class="last">d</p><p>e</p><p>f</p></div>', 32);
        $this->assertSame(['a          c          e', 'b          d          f'], array_map('rtrim', \array_slice($screen, 0, 2)), 'c starts the second column, e the third, after d.');
    }

    public function testBreaksDoNotGoPastTheLastColumn()
    {
        $screen = $this->render('<style>.cols { columns: 2; column-gap: 1 } p { margin: 0; break-before: column }</style><div class="cols"><p>a</p><p>b</p><p>c</p></div>', 32);
        $this->assertSame(['a               b', '                c'], array_map('rtrim', \array_slice($screen, 0, 2)), 'What is left goes into the last column.');
    }

    public function testOrphansAndWidowsKeepLinesTogether()
    {
        $html = '<style>.cols { columns: 2; column-gap: 1 } p { margin: 0; orphans: %d; widows: %d }</style><div class="cols"><p>1<br>2<br>3<br>4</p></div>';
        $columns = fn (int $orphans, int $widows) => array_map('rtrim', \array_slice($this->render(\sprintf($html, $orphans, $widows), 24, 6), 0, 3));
        $this->assertSame(['1           3', '2           4'], $columns(1, 1), 'Balanced: two lines in each column.');
        $this->assertSame(['1           4', '2', '3'], $columns(3, 1), 'Three lines before the break.');
        $this->assertSame(['1           2', '            3', '            4'], $columns(1, 3), 'Three lines after it.');
    }

    public function testColumnFillAutoFillsTheColumnsOneAfterTheOther()
    {
        $html = '<style>.cols { columns: 3; column-gap: 1; %s } p { margin: 0 }</style><div class="cols"><p>a</p><p>b</p><p>c</p><p>d</p></div><p>end</p>';
        $screen = fn (string $css) => array_map('rtrim', \array_slice($this->render(\sprintf($html, $css), 23), 0, 4));
        $this->assertSame(['a       c', 'b       d', '', 'end'], $screen('height: 3ch'), 'Balanced by default, in a box of its height.');
        $this->assertSame(['a       d', 'b', 'c', 'end'], $screen('height: 3ch; column-fill: auto'), 'Filled up to the height.');
        $this->assertSame(['a       c', 'b       d', 'end'], $screen('column-fill: auto'), 'Balanced without a height, like browsers.');
        $this->assertSame(['a       c', 'end'], $screen('height: 1ch; column-fill: auto'), 'Content that does not fit makes the columns taller, cut at the height like any content.');
    }

    public function testANestedBlockThatAvoidsBreaksIsKeptWhole()
    {
        $screen = $this->render('<style>.cols { columns: 2; column-gap: 1 } p { margin: 0 } .keep { break-inside: avoid }</style><div class="cols"><section><p>a</p><p class="keep">b<br>c<br>d</p><p>f</p></section></div>', 16, 6);
        $this->assertSame(['a       f', 'b', 'c', 'd'], array_map('rtrim', \array_slice($screen, 0, 4)));
    }

    public function testAClonedBoxHasItsEdgesAroundEachPiece()
    {
        $html = '<style>.cols { columns: 2; column-gap: 1 } section { border: 1px solid } p { margin: 0; orphans: 1; widows: 1 } .clone { box-decoration-break: clone }</style><div class="cols"><section class="%s"><p>a<br>b<br>c<br>d</p></section></div>';
        $this->assertSame(['┌─────┐ │c    │', '│a    │ │d    │', '│b    │ └─────┘'], array_map('rtrim', \array_slice($this->render(\sprintf($html, 'slice'), 15, 6), 0, 4)), 'Sliced: the top edge in the first piece, the bottom one in the last.');
        $this->assertSame(['┌─────┐ ┌─────┐', '│a    │ │c    │', '│b    │ │d    │', '└─────┘ └─────┘'], array_map('rtrim', \array_slice($this->render(\sprintf($html, 'clone'), 15, 6), 0, 4)), 'Cloned: both edges around each piece.');
    }

    public function testTheBlocksOfACutBoxAreWhereTheyAreDrawn()
    {
        $tui = new Tui(null, new VirtualTerminal(21, 8));
        $view = (new HtmlBuilder())->build('<style>.cols { columns: 2; column-gap: 1 } p { margin: 0 }</style><div class="cols"><section><p id="a">a</p><p id="b">b</p></section></div>')->mount($tui);
        $tui->start();
        $tui->processRender();
        $this->assertSame([0, 0], [$tui->getWidgetRect($view->getById('a'))->row, $tui->getWidgetRect($view->getById('a'))->col]);
        $this->assertSame([0, 11], [$tui->getWidgetRect($view->getById('b'))->row, $tui->getWidgetRect($view->getById('b'))->col], 'In the second column, though its section starts in the first.');
    }

    public function testABlockCutAcrossColumnsIsUnderThePointerInEachOfThem()
    {
        $tui = new Tui(null, new VirtualTerminal(21, 8));
        $view = (new HtmlBuilder())->build('<style>.cols { columns: 2; column-gap: 1 } p { margin: 0; orphans: 1; widows: 1 }</style><div class="cols"><p id="p">1<br>2<br>3<br>4</p></div>')->mount($tui);
        $tui->start();
        $tui->processRender();
        $paragraph = $view->getById('p');
        $this->assertSame($paragraph, $view->widgetAt(0, 0));
        $this->assertSame($paragraph, $view->widgetAt(1, 11), 'Its other piece, in the second column.');
        $this->assertNotSame($paragraph, $view->widgetAt(0, 10), 'Not in the gap between them.');
    }

    public function testAStableGutterKeepsTheScrollbarColumn()
    {
        $width = static function (string $class): int {
            $tui = new Tui(null, new VirtualTerminal(30, 8));
            $view = (new HtmlBuilder())->build('<style>.box { overflow: auto; max-height: 4; width: 20ch } p { margin: 0 } .stable { scrollbar-gutter: stable }</style><div class="box '.$class.'"><p id="p">fits</p></div>')->mount($tui);
            $tui->start();
            $tui->processRender();

            return $tui->getWidgetRect($view->getById('p'))->columns;
        };
        $this->assertSame(20, $width(''), 'The content fits: the whole width.');
        $this->assertSame(19, $width('stable'), 'Stable: the column of the scrollbar is kept.');
    }

    private function render(string $html, int $columns, int $rows = 8): array
    {
        $terminal = new VirtualTerminal($columns, $rows);
        $tui = new Tui(null, $terminal);
        (new HtmlBuilder())->build($html)->mount($tui);
        $tui->start();
        $tui->processRender();
        $buffer = new ScreenBuffer($columns, 8);
        $buffer->write($terminal->consumeOutput());

        return explode("\n", $buffer->getScreen());
    }
}
