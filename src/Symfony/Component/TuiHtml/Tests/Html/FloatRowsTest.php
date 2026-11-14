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
use Symfony\Component\TuiHtml\Render\RenderContext;
use Symfony\Component\TuiHtml\Tests\Fixtures\HtmlTui as Tui;
use Symfony\Component\TuiHtml\Widget\TextWidget;

/**
 * The lines of a text beside a float or a drop cap on the rows they are drawn on, below the rows of line-height and
 * of ruby.
 */
final class FloatRowsTest extends TestCase
{
    public function testAnInlineTableAfterAFloatKeepsItsOwnColumns()
    {
        $lines = $this->render('<style>.f { float: left; width: 5ch; height: 3 } table { display: inline-table; border-spacing: 0 } td { padding: 0 }</style><div class="f">F</div><table><tr><td>AB</td><td>CD</td></tr></table>', 20);
        $this->assertSame('F     ABCD', rtrim($lines[0]));
    }

    public function testTheBlankRowsOfLineHeightCountBesideAFloat()
    {
        $screen = $this->render('<style>.f { float: left; width: 4ch; height: 3; border: 1px solid } p { margin: 0; line-height: 2 }</style><div><div class="f">F</div><p>one two three four five six seven eight</p></div>', 20);
        $this->assertSame(['┌──┐ one two three', '│F │', '└──┘ four five six', '', 'seven eight'], array_map('rtrim', $screen), 'The line on the row below the float takes the whole width.');
    }

    public function testTheRowsOfRubyAnnotationsCountBesideAFloat()
    {
        $screen = $this->render('<style>.f { float: left; width: 4ch; height: 2; border: 1px solid } p { margin: 0 }</style><div><div class="f">F</div><p>one <ruby>東<rt>tō</rt></ruby> two three four</p></div>', 20);
        $this->assertSame(['┌──┐     tō', '└──┘ one 東 two', 'three four'], array_map('rtrim', \array_slice($screen, 0, 3)), 'The line below the float takes the whole width.');
    }

    public function testTheRowsOfADropCapAreBesideTheAnnotationsOfRuby()
    {
        $screen = $this->render('<style>p { margin: 0 } p::first-letter { initial-letter: 4 }</style><p>Ruby: <ruby>東<rt>tō</rt></ruby> beside <ruby>京<rt>kyō</rt></ruby> a drop cap.</p>', 24);
        $this->assertSame(['  ___       tō', ' | _ \ uby: 東 beside', ' |   / kyō', ' |_|_\ 京  a drop cap.'], array_map('rtrim', \array_slice($screen, 0, 4)), 'The rows of the letter are not cut by those of the annotations.');
    }

    public function testTextGoesBelowAFloatAsWideAsTheLineHoweverTall()
    {
        // 600 rows of a float leaving no room: the text after them, not a column of it every few hundred rows
        $tui = new Tui(null, new VirtualTerminal(20, 8));
        $view = (new HtmlBuilder())->setDefaultStyles(false)->build('<div style="float: left; width: 20ch; height: 600">x</div><p>after the float</p>')->mount($tui);
        $tui->start();
        $tui->processRender();
        $p = $view->querySelector('p');
        $this->assertSame([0, 601], [$tui->getWidgetRect($p)?->row, $tui->getWidgetRect($p)?->rows]);
    }

    public function testTextWithoutRoomEvenPastItsInsetsIsNotPushedDown()
    {
        // Insets leaving no column on any row, like those of a drop cap in a box a column wide: no row to wait for
        $text = new TextWidget('hello world');
        $text->setLineInsets([[2, 0]]);
        $start = microtime(true);
        $lines = $text->render(new RenderContext(1, 8));
        $this->assertLessThan(1, microtime(true) - $start);
        $this->assertCount(10, $lines, 'A character a row, one after the other.');
    }

    public function testARuleOfAFormattingContextOfItsOwnIsBesideAFloatNotUnderIt()
    {
        $html = '<div style="float: left; width: 6ch; height: 2">F</div><hr style="border-top: 1px solid; %s"><p>after</p>';
        $this->assertSame('F     ──────────────', rtrim($this->render(\sprintf($html, ''), 20)[0]), 'Its whole width, under the float.');
        $this->assertSame('F     ──────────────', rtrim($this->render(\sprintf($html, 'overflow: clip'), 20)[0]), 'No formatting context of its own.');
        $this->assertSame('F     ──────────────', rtrim($this->render('<style>hr::after { content: ""; display: table; clear: both }</style>'.\sprintf($html, ''), 20)[0]), 'Nor one cleared by its ::after.');
        foreach (['overflow: hidden', 'display: flow-root'] as $declaration) {
            $this->assertMatchesRegularExpression('/^F {6}─+$/u', rtrim($this->render(\sprintf($html, $declaration), 20)[0]), $declaration);
        }
    }

    public function testTheTextOfABlockClearedByItsBeforeStaysUnderTheFloatItsMarginsCollapsedWithItsParent()
    {
        $lines = $this->render('<style>.c::before { content: ""; display: block; clear: both } p { margin: 0 }</style><p>x</p><div style="float: left; width: 5ch; height: 4">F</div><section style="margin-top: 1"><div class="c" style="margin-top: 1"><p>cleared</p></div></section><p>end</p>', 20);
        $this->assertSame(['x', 'F', '', '', '', 'cleared', 'end'], array_map(rtrim(...), \array_slice($lines, 0, 7)));
    }

    public function testAFloatAfterAParagraphGoesUnderItNotBesideItsLastLine()
    {
        $this->assertSame(['x', 'F     after'], array_map(rtrim(...), \array_slice($this->render('<p>x</p><div style="float: left; width: 5ch">F</div><p>after</p>', 20), 0, 2)));
        $this->assertSame(['Inbox             14'], array_map(rtrim(...), \array_slice($this->render('<div>Inbox <span style="float: right">14</span></div>', 20), 0, 1)), 'Beside a run of text.');
    }

    public function testTheBorderOfAClearedBlockIsUnderTheFloatsItsMarginTakenInTheClearance()
    {
        $this->assertSame(['F', '', '', 'cleared'], array_map(rtrim(...), \array_slice($this->render('<div style="float: left; width: 5ch; height: 3">F</div><div style="clear: both; margin-top: 2">cleared</div>', 20), 0, 4)), 'Right under the float, like browsers.');
        $this->assertSame(['F     x', '', '', '', 'cleared'], array_map(rtrim(...), \array_slice($this->render('<div style="float: left; width: 5ch; height: 2">F</div><p style="margin-bottom: 3">x</p><div style="clear: both; margin-top: 3">cleared</div>', 20), 0, 5)), 'Its margin collapsing with the one above, under the float already.');
    }

    public function testAClearedRowIsAsWideAsTheBlockUnderTheFloatsItClears()
    {
        $this->assertSame(['F', '', '', 'ab'], array_map(rtrim(...), \array_slice($this->render('<div style="float: left; width: 5ch; height: 3">F</div><div style="clear: both; margin-top: 2; display: flex"><span>a</span><span>b</span></div>', 20), 0, 4)));
    }

    public function testTheFloatsAClearedBlockClearsAreDrawnOverTheRowsOfItsMargin()
    {
        $this->assertSame(['               R', '               R', '               R', 'ab'], array_map(rtrim(...), \array_slice($this->render('<div style="float: right; width: 5ch; height: 3">R<br>R<br>R</div><div style="clear: both; margin-top: 2; display: flex"><span>a</span><span>b</span></div>', 20), 0, 4)));
        $this->assertSame(['               R', '               R', 'ab             R'], array_map(rtrim(...), \array_slice($this->render('<div style="float: right; width: 5ch; height: 3">R<br>R<br>R</div><div style="clear: left; margin-top: 2; display: flex"><span>a</span><span>b</span></div>', 20), 0, 3)), 'Nothing cleared: beside the float.');
    }

    public function testABlockWhoseMarginClearsTheFloatsIsAsWideAsTheBlockUnderThem()
    {
        $this->assertSame(['F     x', '', '', '', 'long text that is', 'quite long'], array_map(rtrim(...), \array_slice($this->render('<div style="float: left; width: 5ch; height: 2">F</div><p>x</p><div style="clear: left; margin-top: 3">long text that is quite long</div>', 20), 0, 6)));
    }

    public function testAClearedFloatGoesBelowTheFloatsItClearsTheFlowWhereItIs()
    {
        $this->assertSame(['A     text', '', 'B'], array_map(rtrim(...), \array_slice($this->render('<div style="float: left; width: 5ch; height: 2">A</div><div style="float: left; clear: left; width: 5ch">B</div><p>text</p>', 20), 0, 3)));
    }

    public function testAFloatGoingOnPastItsBlockIsDrawnWithTheFilterOfTheBlock()
    {
        $terminal = new VirtualTerminal(20, 8);
        $tui = new Tui(null, $terminal);
        (new HtmlBuilder())->setDefaultStyles(false)->build('<div style="filter: grayscale(1)"><div style="float: left; width: 6ch; height: 3; color: red">RED</div><p>in</p></div><p>after</p>')->mount($tui);
        $tui->start();
        $tui->processRender();
        $buffer = new ScreenBuffer(20, 8);
        $buffer->write($terminal->consumeOutput());
        $this->assertStringNotContainsString("\x1b[31m", $buffer->getStyledScreen(), 'Gray, like the rest of the block.');
    }

    public function testTheBeforeOfABlockClearsFromUnderTheMarginsAboveItNotThatOfItsFirstChild()
    {
        $style = '<style>.c::before { content: ""; display: block; clear: both } p { margin: 0 }</style>';
        $row = fn (string $html): int|false => array_search('cleared', array_map(rtrim(...), $this->render($style.$html, 20, 14)), true);
        $this->assertSame(4, $row('<div style="float: left; width: 5ch; height: 2">F</div><p style="margin-bottom: 3">x</p><div class="c"><p style="margin-top: 3">cleared</p></div>'), 'Under the float past the margin above: nothing cleared, the margins collapsed.');
        $this->assertSame(4, $row('<div style="float: left; width: 5ch; height: 2">F</div><p style="margin-bottom: 3">x</p><section><div class="c"><p style="margin-top: 3">cleared</p></div></section>'), 'Nor in a block around it.');
        $this->assertSame(' cleared', rtrim($this->render($style.'<div style="float: left; width: 5ch; height: 2">F</div><p style="margin-bottom: 3">x</p><section style="padding-left: 1ch"><div class="c"><p style="margin-top: 3">cleared</p></div></section>', 20, 14)[4]), 'Nor in one with padding at its side.');
        $this->assertSame(3, $row('<div><span style="float: left; height: 1">[logo]</span></div><div class="c"><p style="margin-top: 2">cleared</p></div>'), 'Beside the float: the margin of its first child under it.');
        $this->assertSame(11, $row('<div style="float: left; width: 5ch; height: 8">F</div><p style="margin-bottom: 3">x</p><div class="c"><p style="margin-top: 3">cleared</p></div>'), 'Beside the float past the margin above: the margin of its first child under it.');
    }

    private function render(string $html, int $columns, int $rows = 8): array
    {
        $terminal = new VirtualTerminal($columns, $rows);
        $tui = new Tui(null, $terminal);
        (new HtmlBuilder())->setDefaultStyles(false)->build($html)->mount($tui);
        $tui->start();
        $tui->processRender();
        $buffer = new ScreenBuffer($columns, $rows);
        $buffer->write($terminal->consumeOutput());

        return explode("\n", $buffer->getScreen());
    }
}
