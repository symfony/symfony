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

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Tui\Terminal\ScreenBuffer;
use Symfony\Component\Tui\Terminal\VirtualTerminal;
use Symfony\Component\TuiHtml\Html\HtmlBuilder;
use Symfony\Component\TuiHtml\Tests\Fixtures\HtmlTui as Tui;

final class GridAutoFlowTest extends TestCase
{
    private const ITEMS = '<p>1</p><p>2</p><p>3</p><p>4</p><p>5</p>';

    public function testItemsFillTheRowsColumnAfterColumn()
    {
        $this->assertSame(['┌─┐ ┌─┐ ┌─┐', '│1│ │3│ │5│', '└─┘ └─┘ └─┘', '┌─┐ ┌─┐', '│2│ │4│', '└─┘ └─┘'], $this->render('.g { grid-template-rows: auto auto; grid-auto-flow: column }'));
    }

    public function testWithoutRowsEachItemHasItsColumn()
    {
        $this->assertSame(['┌─┐ ┌─┐ ┌─┐', '│1│ │2│ │3│', '└─┘ └─┘ └─┘'], $this->render('.g { grid-auto-flow: column }', '<p>1</p><p>2</p><p>3</p>'));
    }

    public function testTheShorthandAndTheWidthOfTheImplicitColumns()
    {
        $expected = ['┌────┐ ┌────┐ ┌────┐', '│1   │ │3   │ │5   │', '└────┘ └────┘ └────┘', '┌────┐ ┌────┐', '│2   │ │4   │', '└────┘ └────┘'];
        $this->assertSame($expected, $this->render('.g { grid: auto auto / auto-flow 6ch }'));
        $this->assertSame($expected, $this->render('.g { grid-template-rows: auto auto; grid-auto-flow: column; grid-auto-columns: 6ch }'));
    }

    public function testExplicitColumnsShareWhatTheImplicitOnesLeave()
    {
        $this->assertSame(['┌──────────────────────────────┐ ┌─┐ ┌─┐', '│1                             │ │3│ │5│'], \array_slice($this->render('.g { grid-template: auto auto / 1fr; grid-auto-flow: column }'), 0, 2));
    }

    public function testASubgridCacheFollowsChangedParentTracksAtTheSameWidth()
    {
        $terminal = new VirtualTerminal(20, 4);
        $tui = new Tui(null, $terminal);
        $view = (new HtmlBuilder())->setDefaultStyles(false)->build('<style>#g { display: grid; grid-template-columns: 10ch 10ch } #g.changed { grid-template-columns: 5ch 15ch } #sub { display: grid; grid-template-columns: subgrid; grid-column: span 2 }</style><div id="g"><div id="sub"><div>A</div><div id="b">B</div></div></div>');
        $view->mount($tui);
        $tui->start();
        $tui->processRender();
        $this->assertSame(10, $tui->getWidgetRect($view->getById('b'))->col);
        $view->getById('g')->addStyleClass('changed');
        $tui->requestRender();
        $tui->processRender();
        $this->assertSame(5, $tui->getWidgetRect($view->getById('b'))->col);
        $tui->stop();
    }

    public function testAnEmptyGridTrackPaintsNoBackgroundColumn()
    {
        $terminal = new VirtualTerminal(20, 4);
        $tui = new Tui(null, $terminal);
        $view = (new HtmlBuilder())->setDefaultStyles(false)->build('<style>.g { display: grid; grid-template-columns: min-content 1fr } #empty { background: #ff0000 }</style><div class="g"><div id="empty"></div><div>X</div></div>');
        $view->mount($tui);
        $tui->start();
        $tui->processRender();
        $this->assertStringNotContainsString('48;2;255;0;0', $terminal->getOutput());
        $tui->stop();
    }

    public function testAnEmptyImplicitMinContentColumnHasNoWidth()
    {
        foreach (['column', 'row'] as $flow) {
            $this->assertSame(['  X'], $this->render('.g { grid-template-columns: min-content; grid-auto-flow: '.$flow.'; grid-auto-columns: min-content } .g p { border: 0; grid-row: 1 } .g p:nth-child(2) { grid-column: 2 } .g p:nth-child(3) { grid-column: 3 }', '<p></p><p></p><p>X</p>'));
        }
    }

    public function testDenseFillsTheCellsLeftBefore()
    {
        $this->assertSame(['┌─┐ ┌──────┐ ┌─┐', '│1│ │2 tall│ │4│', '└─┘ │      │ └─┘', '┌─┐ │      │', '│3│ │      │', '└─┘ └──────┘'], $this->render('.g { grid-template-rows: auto auto; grid-auto-flow: column dense } .w { grid-row: span 2 }', '<p>1</p><p class="w">2 tall</p><p>3</p><p>4</p>'));
    }

    public function testAnItemSpanningAutoColumnsWidensThem()
    {
        $this->assertSame(['┌─────────────┐ ┌──────────────────────┐', '│spanning text│ │z                     │', '└─────────────┘ └──────────────────────┘', '┌─────┐ ┌─────┐', '│x    │ │y    │', '└─────┘ └─────┘'], $this->render('.g { grid-template-columns: auto auto 1fr } .s { grid-column: span 2 }', '<p class="s">spanning text</p><p>z</p><p>x</p><p>y</p>'));
    }

    public function testTheItemsAfterOneOfAFixedColumnGoPastIt()
    {
        $this->assertSame(['┌───┐       ┌───┐', '│1  │       │2  │', '└───┘       └───┘', '┌───┐', '│3  │', '└───┘'], $this->render('.g { grid-template-columns: repeat(3, 5ch) } .g p:nth-child(2) { grid-column: 3 }', '<p>1</p><p>2</p><p>3</p>'));
        $this->assertSame(['┌───┐ ┌───┐', '│1  │ │3  │', '└───┘ └───┘', '┌───┐', '│2  │', '└───┘'], $this->render('.g { grid-template-rows: auto auto auto; grid-auto-columns: 5ch; grid-auto-flow: column } .g p:nth-child(2) { grid-row: 3 }', '<p>1</p><p>2</p><p>3</p>'));
    }

    public function testTheItemsOfARowGoPastThoseBeforeThemAndPastTheExplicitColumns()
    {
        $this->assertSame(['┌───┐ ┌───┐ ┌─┐', '│1  │ │2  │ │3│', '└───┘ └───┘ └─┘'], $this->render('.g { grid-template-columns: 5ch 5ch } .g p { grid-row: 1 }', '<p>1</p><p>2</p><p>3</p>'));
        $this->assertSame(['      ┌───┐ ┌─────────┐ ┌─┐', '      │D  │ │A        │ │B│', '      └───┘ └─────────┘ └─┘'], $this->render('.g { grid-template-columns: repeat(4, 5ch) } .g p { grid-row: 1 } .d { grid-column: 2 } .s { grid-column: span 2 }', '<p class="d">D</p><p class="s">A</p><p>B</p>'));
        $this->assertSame(['┌───┐ ┌───┐ ┌─────────┐', '│B  │ │D  │ │A        │', '└───┘ └───┘ └─────────┘'], $this->render('.g { grid-template-columns: repeat(4, 5ch); grid-auto-flow: dense } .g p { grid-row: 1 } .d { grid-column: 2 } .s { grid-column: span 2 }', '<p class="d">D</p><p class="s">A</p><p>B</p>'), 'Dense: in the first cells free.');
    }

    public function testFractionsOfRowsShareTheHeightOfTheGridTheirContentIn()
    {
        $this->assertSame(['┌───┐', '│1  │', '│   │', '│   │', '└───┘', '┌───┐', '│2  │', '│3  │', '│4  │', '└───┘'], $this->render('.g { grid-template-columns: 5ch; grid-template-rows: 1fr 1fr; height: 10 }', '<p>1</p><p>2<br>3<br>4</p>'));
    }

    public function testAFractionIsNoNarrowerThanTheLongestWordOfItsItems()
    {
        $this->assertSame(['┌────────────────────┐ ┌───────────────┐', '│Supercalifragilistic│ │b              │', '└────────────────────┘ └───────────────┘'], $this->render('.g { grid-template-columns: 1fr 3fr }', '<p>Supercalifragilistic</p><p>b</p>'));
        $this->assertSame(['┌────────┐ ┌───────────────────────────┐', '│Supercal│ │b                          │', '│ifragili│ │                           │', '│stic    │ │                           │'], \array_slice($this->render('.g { grid-template-columns: minmax(0, 1fr) 3fr }', '<p>Supercalifragilistic</p><p>b</p>'), 0, 4), 'Unless its minimum says otherwise.');
    }

    public function testJustifyContentPlacesTheColumnsOfTheGrid()
    {
        $items = '<p>a<br>b</p><p>x</p>';
        $this->assertSame(['              ┌───┐ ┌───┐', '              │a  │ │x  │', '              │b  │ │   │', '              └───┘ └───┘'], $this->render('.g { grid-template-columns: 5ch 5ch; justify-content: center }', $items), 'Its items stretched in their rows, not centered.');
        $this->assertSame(['                             ┌───┐ ┌───┐', '                             │a  │ │x  │', '                             │b  │ │   │', '                             └───┘ └───┘'], $this->render('.g { grid-template-columns: 5ch 5ch; justify-content: end }', $items));
        $this->assertSame(['┌───┐                              ┌───┐', '│a  │                              │x  │', '│b  │                              │   │', '└───┘                              └───┘'], $this->render('.g { grid-template-columns: 5ch 5ch; justify-content: space-between }', $items));
        $this->assertSame(['       ┌───┐       ┌───┐', '       │a  │       │x  │'], \array_slice($this->render('.g { grid-template-columns: 5ch 5ch; justify-content: space-evenly; column-gap: 0; width: 30 }', $items), 0, 2));
    }

    public function testALineBeforeTheFirstOneAddsColumnsBeforeTheExplicitOnes()
    {
        $this->assertSame(['┌───────────┐', '│wide       │', '└───────────┘', '┌─┐     ┌───┐', '│b│     │a  │', '└─┘     └───┘'], $this->render('.g { grid-template-columns: repeat(3, 5ch); grid-auto-columns: 3ch } .w { grid-column: -6 / 2 }', '<p class="w">wide</p><p style="grid-row: 2; grid-column: 1">a</p><p style="grid-row: 2">b</p>'), 'Two columns of 3ch before the explicit ones, where an item of a row goes first.');
    }

    public function testALineBeforeTheFirstOneAddsRowsBeforeTheExplicitOnes()
    {
        $this->assertSame(['┌────┐ x', '│tall│ r1', '└────┘ r2'], $this->render('.g { grid-template-columns: 6ch 2ch; grid-template-rows: auto auto } .g p { border: 0 } .g .t { grid-row: -4 / -1; grid-column: 1; border: 1px solid }', '<p class="t">tall</p><p style="grid-row: 1; grid-column: 2">r1</p><p style="grid-row: 2; grid-column: 2">r2</p><p>x</p>'), 'A row before the two explicit ones, where the next item goes first.');
    }

    public function testRightToLeftJustifyContentStartsOnTheRight()
    {
        $css = '.g { grid-template-columns: 5ch 5ch; direction: rtl; column-gap: 0 } .g p { border: 0 } ';
        $this->assertSame(['                                  2    1'], $this->render($css, '<p>1</p><p>2</p>'), 'Like start.');
        $this->assertSame(['    2    1'], $this->render($css.'.g { justify-content: end }', '<p>1</p><p>2</p>'));
        $this->assertSame(['    2                                  1'], $this->render($css.'.g { justify-content: space-between }', '<p>1</p><p>2</p>'), 'The first column on the right.');
        foreach (['start' => 19, 'end' => 10, 'center' => 14] as $justify => $column) {
            $terminal = new VirtualTerminal(20, 3);
            $tui = new Tui(null, $terminal);
            (new HtmlBuilder())->setDefaultStyles(false)->build('<div style="display: grid; grid-template-columns: 10ch; direction: rtl"><p style="justify-self: '.$justify.'; margin: 0">a</p></div>')->mount($tui);
            $tui->start();
            $tui->processRender();
            $buffer = new ScreenBuffer(20, 3);
            $buffer->write($terminal->getOutput());
            $tui->stop();
            $this->assertSame($column, strpos(explode("\n", $buffer->getScreen())[0], 'a'), 'justify-self: '.$justify.' in the track on the right.');
        }
    }

    public function testAlignItemsPlacesTheItemsInTheirRowsBesideJustifyContent()
    {
        $items = '<p>a<br>b<br>c</p><p>x</p>';
        $css = '.g { grid-template-columns: 5ch 5ch } .g p { border: 0 } ';
        $this->assertSame(['              a     x', '              b', '              c'], $this->render($css.'.g { justify-content: center; align-items: start }', $items));
        $this->assertSame(['                               a', '                               b     x', '                               c'], $this->render($css.'.g { place-content: end; place-items: center }', $items), 'Centered in their tracks too, by justify-items.');
    }

    #[DataProvider('provideItemAlignments')]
    public function testTheBoxOfAnItemIsWhereItsAlignmentDrawsIt(string $css, int $row, int $rows)
    {
        $terminal = new VirtualTerminal(20, 4);
        $tui = new Tui(null, $terminal);
        $view = (new HtmlBuilder())->setDefaultStyles(false)->build('<style>.g { display: grid; grid-template-columns: 5ch 5ch } '.$css.'</style><div class="g"><p>a<br>b<br>c</p><div id="x"><div id="in">x</div></div></div>');
        $view->mount($tui);
        $tui->start();
        $tui->processRender();
        $screen = new ScreenBuffer(20, 4);
        $screen->write($terminal->getOutput());
        $drawn = array_key_first(array_filter(explode("\n", $screen->getScreen()), static fn (string $line) => str_contains($line, 'x')));
        $this->assertSame($row, $drawn);
        $this->assertSame([$row, 5, $rows], [$tui->getWidgetRect($view->getById('x'))->row, $tui->getWidgetRect($view->getById('x'))->col, $tui->getWidgetRect($view->getById('x'))->rows]);
        $this->assertSame($row, $tui->getWidgetRect($view->getById('in'))->row, 'What the item holds moves with it.');
    }

    public static function provideItemAlignments(): iterable
    {
        yield 'stretched' => ['', 0, 3];
        yield 'start' => ['.g { align-items: start }', 0, 1];
        yield 'centered' => ['.g { align-items: center }', 1, 1];
        yield 'at the end' => ['#x { align-self: end }', 2, 1];
    }

    private function render(string $css, string $items = self::ITEMS): array
    {
        $terminal = new VirtualTerminal(40, 12);
        $tui = new Tui(null, $terminal);
        (new HtmlBuilder())->setDefaultStyles(false)->build('<style>.g { display: grid; column-gap: 1ch } .g p { border: 1px solid } '.$css.'</style><div class="g">'.$items.'</div>')->mount($tui);
        $tui->start();
        $tui->processRender();
        $buffer = new ScreenBuffer(40, 12);
        $buffer->write($terminal->getOutput());
        $tui->stop();

        return array_values(array_filter(array_map(rtrim(...), explode("\n", $buffer->getScreen())), static fn (string $line) => '' !== $line));
    }
}
