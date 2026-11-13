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

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Tui\Ansi\AnsiUtils;
use Symfony\Component\Tui\Html\HtmlBuilder;
use Symfony\Component\Tui\Terminal\ScreenBuffer;
use Symfony\Component\Tui\Terminal\VirtualTerminal;
use Symfony\Component\Tui\Tui;

/**
 * Pages found by fuzzing the fixtures: each one used to stop the program.
 */
final class RobustnessTest extends TestCase
{
    public function testAWideSubgridClipsItsFarItemsAndTheirPositions()
    {
        $terminal = new VirtualTerminal(40, 8);
        $tui = new Tui(null, $terminal);
        $view = (new HtmlBuilder())->setDefaultStyles(false)->build('<style>.g { display: grid; grid-template-columns: 2500ch 4ch; overflow-x: auto } .sub { display: grid; grid-template-columns: subgrid; grid-column: span 2 } p { margin: 0 }</style><div class="g"><div class="sub"><p>near</p><p id="far" style="background: red">far</p></div></div>');
        $view->mount($tui);
        $tui->start();
        $tui->processRender();
        $buffer = new ScreenBuffer(40, 8);
        $buffer->write($terminal->getOutput());
        $this->assertStringContainsString('near', $buffer->getScreen());
        $this->assertStringNotContainsString('far', $buffer->getScreen());
        $this->assertNull($tui->getWidgetRect($view->getById('far')), json_encode($tui->getWidgetRect($view->getById('far'))));
        $tui->stop();
    }

    public function testTheColorsOfAGradientOverATallBoxAreNotAllKept()
    {
        $terminal = new VirtualTerminal(120, 5);
        $tui = new Tui(terminal: $terminal);
        (new HtmlBuilder())->build('<div style="padding: 2000 0; background: linear-gradient(to right, red, blue)">x</div>')->mount($tui);
        $tui->start();
        memory_reset_peak_usage();
        $before = memory_get_usage();
        $tui->processRender();

        // 4000 rows of 120 cells: about 25 MB drawn, 75 MB when every color was kept
        $this->assertLessThan(50_000_000, memory_get_peak_usage() - $before);
    }

    public function testTheRowsAlikeOfTallMarginsInBoxesShareTheirStrings()
    {
        $terminal = new VirtualTerminal(40, 5);
        $tui = new Tui(terminal: $terminal);
        (new HtmlBuilder())->build(str_repeat('<div style="border: 1px solid; padding: 0 1ch">', 3).str_repeat('<p style="margin: 0 0 1e9px">a</p>', 10).str_repeat('</div>', 3))->mount($tui);
        $tui->start();
        memory_reset_peak_usage();
        $before = memory_get_usage();
        $tui->processRender();

        // 100,000 rows in three boxes: about 45 MB, 105 MB when each box made each row again
        $this->assertLessThan(70_000_000, memory_get_peak_usage() - $before);
    }

    public function testBoxesInBoxesOfHugeMarginsDrawABoundedNumberOfLines()
    {
        $terminal = new VirtualTerminal(10, 5);
        $tui = new Tui(terminal: $terminal);
        $box = static fn (string $inner = ''): string => '<div class="m">Round, bevel, square, notch'.$inner.'</div>';
        (new HtmlBuilder())->build('<style>div[class] { border: 1px solid; max-width: 26ch; margin-bottom: 1e9px }</style>'.$box($box($box($box().$box($box()).$box($box().$box().$box().$box($box($box($box().$box($box())).$box().$box()).$box().$box().$box()))))))->mount($tui);
        $tui->start();
        memory_reset_peak_usage();
        $before = memory_get_usage();
        $tui->processRender();

        // Margins of 10,000 rows adding up in boxes in boxes: no more lines than the bound, about 57 MB; 102 MB unbounded,
        // and gigabytes as the boxes go deeper
        $this->assertLessThan(80_000_000, memory_get_peak_usage() - $before);
    }

    public function testAGridOfHugeRowsDrawsABoundedNumberOfLines()
    {
        $tui = new Tui(terminal: new VirtualTerminal(20, 5));
        (new HtmlBuilder())->build('<style>.g { display: grid } .g > p { height: 99999 }</style><div class="g">'.str_repeat('<p>Item</p>', 300).'</div>')->mount($tui);
        $tui->start();
        memory_reset_peak_usage();
        $before = memory_get_usage();
        $tui->processRender();

        // Rows of 10,000 lines each, 3 million in all: drawn up to the bound, about 92 MB; 156 MB all drawn
        $this->assertLessThan(120_000_000, memory_get_peak_usage() - $before);
    }

    public function testABoxPulledUpThousandsOfRowsIsDrawnOverThemInBlocks()
    {
        $tui = new Tui(terminal: new VirtualTerminal(20, 5));
        (new HtmlBuilder())->build('<div style="height: 9999">Tall</div><div style="margin-top: -9999">Pulled up over it</div>')->mount($tui);
        $tui->start();
        memory_reset_peak_usage();
        $before = memory_get_usage();
        $tui->processRender();

        // The cells of the rows it is over composited a block at a time, about 7 MB; 29 MB at once, and gigabytes for a
        // pull of millions of rows of boxes in boxes
        $this->assertLessThan(15_000_000, memory_get_peak_usage() - $before);
    }

    #[DataProvider('provideDirections')]
    public function testAGridOfHugeGapsAcrossManyColumnsIsNotDrawnPastTheColumnsDrawn(string $direction)
    {
        $tui = new Tui(terminal: new VirtualTerminal(80, 5));
        (new HtmlBuilder())->build('<style>.g { display: grid; gap: 1e9px; direction: '.$direction.' } .c { grid-template-rows: span 1000; grid-auto-flow: column; grid-auto-columns: 6ch 12ch }</style><div class="g c"><p>a</p><p>b</p><p>c</p><p>d</p><div class="g"><p>e</p><p>f</p></div></div>')->mount($tui);
        memory_reset_peak_usage();
        $before = memory_get_usage();
        $tui->start();
        $tui->processRender();

        // Its items past the columns drawn left out: a gigabyte else
        $this->assertLessThan(100_000_000, memory_get_peak_usage() - $before);
    }

    public static function provideDirections(): iterable
    {
        yield 'from the left' => ['ltr'];
        yield 'from the right' => ['rtl'];
    }

    public function testAnImageIsMeasuredWithoutBeingDrawn()
    {
        $terminal = new VirtualTerminal(20, 5);
        $tui = new Tui(terminal: $terminal);
        (new HtmlBuilder())->build('<style>.thumb { width: 20ch; aspect-ratio: clamp(1e9px, 0, -1e9px); border: 100vw } .thumb img { width: 100%; height: auto }</style><div class="thumb"><img src="data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAgAAAAICAYAAADED76LAAAACXBIWXMAAA7EAAAOxAGVKw4bAAAALElEQVQYlYXNsQ0AIBDDwAti/5WfghYUt7EcCoFhPmNWK1Rh39TzAemFKlQORnQDD+BTFtsAAAAASUVORK5CYII=" width="32" height="32" alt="a"> text</div>')->mount($tui);
        $tui->start();
        memory_reset_peak_usage();
        $before = memory_get_usage();
        $tui->processRender();

        // A box of an odd aspect ratio: 2 GB when the image was drawn thousands of columns wide to be measured
        $this->assertLessThan(50_000_000, memory_get_peak_usage() - $before);
    }

    public function testAnSvgIsMeasuredWithoutBeingDrawn()
    {
        $terminal = new VirtualTerminal(40, 5);
        $tui = new Tui(terminal: $terminal);
        (new HtmlBuilder())->build('<div style="display: grid; grid-template-columns: repeat(12, 1fr); overflow: hidden"><ul style="grid-column: span 3; display: flex"><li style="width: 10ch"><a style="display: flex; aspect-ratio: 80/23; width: 10ch" href="/"><svg viewBox="0 0 1280 314" style="width: 100%; height: 100%"><path d="M0 0h1280v314z"/></svg></a></li></ul></div>')->mount($tui);
        $tui->start();
        memory_reset_peak_usage();
        $before = memory_get_usage();
        $tui->processRender();

        // As wide as the room it is measured in, like the logo of Laravel: 36 MB here, hundreds there, when it was drawn
        // thousands of columns wide to be measured
        $this->assertLessThan(20_000_000, memory_get_peak_usage() - $before);
    }

    #[DataProvider('provideTallGridsAndTables')]
    public function testTheRowsPastThoseDrawnAreNotLaidOut(string $html)
    {
        $terminal = new VirtualTerminal(40, 5);
        $tui = new Tui(terminal: $terminal);
        (new HtmlBuilder())->build($html)->mount($tui);
        $tui->start();
        memory_reset_peak_usage();
        $before = memory_get_usage();
        $tui->processRender();

        // Rows of cells of a huge padding: those past the rows drawn not laid out, 100 to 200 MB here when they were,
        // more than a page holds for a table of a thousand rows
        $this->assertLessThan(60_000_000, memory_get_peak_usage() - $before);
    }

    public static function provideTallGridsAndTables(): iterable
    {
        yield 'A grid' => ['<div style="display: grid; grid-template-columns: 1fr 1fr">'.str_repeat('<p style="padding: 10000 0; margin: 0">a</p>', 120).'</div>'];
        yield 'A collapsed table' => ['<table style="border-collapse: collapse">'.str_repeat('<tr><td style="padding: 10000 0">a</td><td>b</td></tr>', 60).'</table>'];
    }

    #[DataProvider('provideOddPages')]
    public function testOddPagesAreDrawn(string $html, int $columns, string $expected)
    {
        $terminal = new VirtualTerminal($columns, 5);
        $tui = new Tui(terminal: $terminal);
        (new HtmlBuilder())->build($html)->mount($tui);
        $tui->start();
        $tui->processRender();
        $buffer = new ScreenBuffer($columns, 5);
        $buffer->write($terminal->consumeOutput());

        $this->assertStringContainsString($expected, AnsiUtils::stripAnsiCodes($buffer->getScreen()));
    }

    public static function provideOddPages(): iterable
    {
        yield 'A background of an undefined custom property, like a missing theme variable' => ['<div style="background: var(--nope)"><p>text</p></div>', 20, 'text'];
        yield 'A grid of more columns and gaps than the box it is in has' => ['<div style="display: grid; grid-template-columns: 4ch 1fr"><div style="display: grid; grid-template-columns: repeat(3, auto); gap: 0 1ch"><p>a</p><p>b</p><p>c</p></div><p>next</p></div>', 20, 'next'];
        yield 'A CJK character in a box of one column' => ['<p style="width: 1ch">一二</p><p>after</p>', 20, 'after'];
        yield 'A CJK character written vertically in a column of one' => ['<div style="display: grid; grid-template-columns: 1ch 1fr"><p style="writing-mode: vertical-rl">一二</p><p>beside</p></div>', 20, 'beside'];
        yield 'Text written vertically in a track of a grid squeezed to no columns' => ['<div style="writing-mode: vertical-rl; display: grid; grid-template-columns: 1fr 2fr; row-gap: 1; border: 1px solid"><status-dot><template shadowrootmode="open"><span>online</span></p>', 20, '│'];
        yield 'A picture whose image has an attribute name the DOM rejects' => ['<picture><source srcset="data:,x"><img alt="pic" a"b="c"></picture>', 20, 'pic'];
        yield 'Grid areas of more columns than grid-template-columns lists' => ['<style>.page { display: grid; grid-template-areas: "head head" "side main"; grid-template-columns: 50% } header { grid-area: head } aside { grid-area: side } main { grid-area: main }</style><div class="page"><header>Header</header><aside>Side</aside><main>Main</main></div>', 40, 'Main'];
        yield 'An absolute box of no height whose text goes on past it' => ['<div style="position: relative; height: 3"><div style="position: absolute; top: 0; height: 0">one two</div></div>', 20, 'one two'];
        yield 'A float as wide as the text beside it' => ['<div style="width: 9ch"><div style="float: left; width: 8ch; height: 2; background: gray"></div><p style="padding-left: 1ch; margin: 0">Text flows under it.</p></div>', 13, 'Text'];
        yield 'A string left open in a style attribute, like an escaped quote' => ['<div style="padding: &quot;\\&quot;"><p>text</p></div>', 20, 'text'];
        yield 'A string left open in a style sheet' => ['<style>p { margin: "x }</style><p>text</p>', 20, 'text'];
        yield 'A @keyframes block left open at the end of the style sheet' => ['<style>@keyframes pulse { from { color: red } to { color: blue</style><p>text</p>', 20, 'text'];
        yield 'A progress of a max below 0 and huge' => ['<progress min="1e308" max="-1e308" value="0.6"></progress><progress value="50" max="1e308"></progress>', 40, '60%'];
        yield 'Beside a float, a block narrower than its words even past it' => ['<div><section style="float: left; width: 70%">Two floats</section><aside style="width: 25%">The aside</aside></div><p>after</p>', 10, 'after'];
        yield 'A collapsed table of more columns than the terminal has' => ['<table style="border-collapse: collapse; border: 1px solid"><tr><td style="border: 1px solid">a</td><td style="border: 1px solid">b</td><td style="border: 1px solid">c</td></tr></table>', 1, '┌'];
    }
}
