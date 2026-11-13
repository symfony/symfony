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
use Symfony\Component\Tui\Terminal\ScreenBuffer;
use Symfony\Component\Tui\Terminal\VirtualTerminal;
use Symfony\Component\Tui\Tui;

final class BlockGeneratedContentTest extends TestCase
{
    public function testABlockDrawsItsBeforeAndAfterOnLinesOfTheirOwn()
    {
        $terminal = new VirtualTerminal(30, 10);
        $tui = new Tui(terminal: $terminal);
        $view = (new HtmlBuilder())->setDefaultStyles(false)->build('<style>section::before { content: "Before " attr(data-x) } section::after { content: "After" } .row { display: flex; gap: 1ch } .row::before { content: "item" }</style><section data-x="1"><p>One</p><button id="b">Go</button></section><div class="row"><p>Row</p></div>')->mount($tui);
        $tui->start();
        $tui->processRender();
        $buffer = new ScreenBuffer(30, 10);
        $buffer->write($terminal->consumeOutput());

        $this->assertSame(['Before 1', 'One', '[ Go ]', 'After', 'item Row'], \array_slice(array_map('rtrim', explode("\n", $buffer->getScreen())), 0, 5));
        $this->assertSame(2, $tui->getWidgetRect($view->getById('b'))?->row, 'The children are tracked below the ::before line.');
        $tui->stop();
    }

    public function testTheTextOfABlockCarriesItsBeforeAndAfterInline()
    {
        $terminal = new VirtualTerminal(30, 10);
        $tui = new Tui(terminal: $terminal);
        (new HtmlBuilder())->setDefaultStyles(false)->build('<style>p { margin: 0 } .x::before { content: "["; color: red } .x::after { content: "]" } .table::before { content: "-"; display: table }</style><div class="x">text <b>bold</b></div><div class="x">start<p>para</p>end</div><div class="x"><p>blocks</p></div><div class="table">tabled</div>')->mount($tui);
        $tui->start();
        $tui->processRender();
        $output = $terminal->consumeOutput();
        $buffer = new ScreenBuffer(30, 10);
        $buffer->write($output);

        $this->assertSame(['[text bold]', '[start', 'para', 'end]', '[', 'blocks', ']', '-', 'tabled'], \array_slice(array_map('rtrim', explode("\n", $buffer->getScreen())), 0, 9), 'In its text when it starts or ends with it, like CSS; lines of their own beside blocks, or when they are blocks.');
        $this->assertStringContainsString("\e[31m[\e[39mtext", $output, 'In the style of the ::before of the block.');
        $tui->stop();
    }

    public function testAHiddenBeforeKeepsItsRoomInTheLineAndABlockOneNone()
    {
        $terminal = new VirtualTerminal(30, 10);
        $tui = new Tui(terminal: $terminal);
        (new HtmlBuilder())->setDefaultStyles(false)->build('<style>p { margin: 0 } .tab [data-content]::before { content: attr(data-content); display: block; height: 0; visibility: hidden; font-weight: 600 } .gap::before { content: "AB"; visibility: hidden }</style><p class="tab"><a href="#"><span data-content="Code">Code</span></a> <span data-content="Issues">Issues</span></p><p><span class="gap">x</span></p><p class="gap">y</p>')->mount($tui);
        $tui->start();
        $tui->processRender();
        $buffer = new ScreenBuffer(30, 10);
        $buffer->write($terminal->consumeOutput());

        $this->assertSame(['Code Issues', '  x', '  y'], \array_slice(array_map('rtrim', explode("\n", $buffer->getScreen())), 0, 3), 'Drawn as spaces, as wide; not at all when a block, which the line does not hold, like the bold copy of a label of a tab of GitHub.');
        $tui->stop();

        $terminal = new VirtualTerminal(30, 10);
        $tui = new Tui(terminal: $terminal);
        (new HtmlBuilder())->setDefaultStyles(false)->build('<style>ul { list-style-position: inside; margin: 0; padding: 0 } .a::before { content: "x"; visibility: hidden } .b::before { content: "x"; visibility: hidden; display: block }</style><ul><li class="a">one</li><li class="b">two</li></ul>')->mount($tui);
        $tui->start();
        $tui->processRender();
        $buffer = new ScreenBuffer(30, 10);
        $buffer->write($terminal->consumeOutput());

        $this->assertSame(['•  one', '• two'], \array_slice(array_map('rtrim', explode("\n", $buffer->getScreen())), 0, 2), 'The marker inside is drawn still.');
        $tui->stop();
    }

    public function testInAFlexRowTheyAreItems()
    {
        $terminal = new VirtualTerminal(30, 10);
        $tui = new Tui(terminal: $terminal);
        (new HtmlBuilder())->setDefaultStyles(false)->build('<style>.row { display: flex; gap: 1ch } .row::before { content: "→"; color: red } .row::after { content: "←" } .end { display: flex; justify-content: space-between; width: 20ch } .end::after { content: "▾" }</style><div class="row"><span>a</span><span>b</span></div><div class="end"><span>Menu</span></div>')->mount($tui);
        $tui->start();
        $tui->processRender();
        $output = $terminal->consumeOutput();
        $buffer = new ScreenBuffer(30, 10);
        $buffer->write($output);

        $this->assertSame(['→ a b ←', 'Menu               ▾'], \array_slice(array_map('rtrim', explode("\n", $buffer->getScreen())), 0, 2), 'Spaced like the others, pushed to the end by space-between.');
        $this->assertStringContainsString("\e[31m→", $output, 'In their style.');
        $tui->stop();
    }

    public function testInAGridTheyAreItemsPlacedLikeTheOthers()
    {
        $terminal = new VirtualTerminal(30, 10);
        $tui = new Tui(terminal: $terminal);
        (new HtmlBuilder())->setDefaultStyles(false)->build('<style>.g { display: grid; grid-template-columns: 6ch 6ch; gap: 0 1ch } .g::before { content: "head"; grid-column: 1 / -1 } .g::after { content: "end" }</style><div class="g"><span>a</span><span>b</span><span>c</span></div>')->mount($tui);
        $tui->start();
        $tui->processRender();
        $buffer = new ScreenBuffer(30, 10);
        $buffer->write($terminal->consumeOutput());

        $this->assertSame(['head', 'a      b', 'c      end'], \array_slice(array_map('rtrim', explode("\n", $buffer->getScreen())), 0, 3), 'The first across both columns, the last after the children.');
        $tui->stop();
    }

    public function testEmptyAndHiddenOnesDrawNothingLikeAClearfix()
    {
        $terminal = new VirtualTerminal(30, 10);
        $tui = new Tui(terminal: $terminal);
        (new HtmlBuilder())->setDefaultStyles(false)->build('<style>.row::before, .row::after { content: " "; display: table } .old::after { content: "."; visibility: hidden; height: 0 }</style><div class="row"><p>One</p></div><div class="old"><p>Two</p></div><p>Three</p>')->mount($tui);
        $tui->start();
        $tui->processRender();
        $buffer = new ScreenBuffer(30, 10);
        $buffer->write($terminal->consumeOutput());

        $this->assertSame(['One', 'Two', 'Three'], \array_slice(array_map('rtrim', explode("\n", $buffer->getScreen())), 0, 3));
        $tui->stop();
    }

    public function testTheContentHasNoControlBytes()
    {
        $view = (new HtmlBuilder())->setDefaultStyles(false)->build("<style>section::before { content: attr(data-x) }</style><section id=\"s\" data-x=\"\e]0;pwned\x07x\"><p>One</p></section>")->computeStyles();

        $this->assertSame([']0;pwnedx', null], $view->getById('s')->getGeneratedContent());
    }
}
