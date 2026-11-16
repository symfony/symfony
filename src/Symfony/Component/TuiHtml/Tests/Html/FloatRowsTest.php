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
use Symfony\Component\TuiHtml\Tests\Fixtures\CpuTime;
use Symfony\Component\TuiHtml\Tests\Fixtures\HtmlTui as Tui;
use Symfony\Component\TuiHtml\Widget\TextWidget;

/**
 * The lines of a text beside a float or a drop cap on the rows they are drawn on, below the rows of line-height and
 * of ruby.
 */
final class FloatRowsTest extends TestCase
{
    use CpuTime;

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
        // Its F, past its two rows of borders, over its bottom border, like CSS overflow: visible
        $this->assertSame(['┌──┐     tō', '└F─┘ one 東 two', 'three four'], array_map('rtrim', \array_slice($screen, 0, 3)), 'The line below the float takes the whole width.');
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
        $start = self::cpuNow();
        $lines = $text->render(new RenderContext(1, 8));
        $this->assertLessThan(1, self::cpuNow() - $start);
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

    public function testAFormattingContextOfItsOwnBesideAFloatIsNarrowedNotFlowedAroundIt()
    {
        $html = '<style>p { margin: 0 }</style><div style="float: left; width: 3ch; height: 2">F</div><div style="%s">%s</div>';
        foreach (['overflow: hidden', 'display: flow-root'] as $declaration) {
            $this->assertSame(['F   one two three', '    four five six', '    seven eight nine', '    ten'], array_map(rtrim(...), \array_slice($this->render(\sprintf($html, $declaration, 'one two three four five six seven eight nine ten'), 20), 0, 4)), $declaration.': its lines beside the float past its end.');
        }
        $this->assertSame(['F   ┌──────────────┐', '    │one two       │', '    └──────────────┘'], array_map(rtrim(...), \array_slice($this->render(\sprintf($html, 'overflow: hidden; border: 1px solid', 'one two'), 20), 0, 3)), 'Its border beside the float.');
        $this->assertSame('F   B', rtrim($this->render(\sprintf($html, 'display: flow-root', '<p style="clear: both">B</p>'), 20)[0]), 'What clears in it clears its own floats only.');
    }

    public function testTheMarginsOfAFloatOfAWidthAreBesideItsBox()
    {
        $lines = $this->render('<div style="float: left; width: 4ch; margin: 0 2ch; border: 1px solid">A</div><p style="margin: 0">text</p>', 20);
        $this->assertSame(['  ┌──┐   text', '  │A │', '  └──┘'], array_map(rtrim(...), \array_slice($lines, 0, 3)), 'Its box as wide as its width, the text past its right margin.');
        $lines = $this->render('<div style="float: right; width: 4ch; margin-right: 3ch; border: 1px solid">A</div><p style="margin: 0">text</p>', 20);
        $this->assertSame('text         ┌──┐', rtrim($lines[0]));
    }

    public function testAFloatOfAMaxWidthIsAsWideAsItsContentUpToIt()
    {
        $html = '<div style="float: left; %s; border: 1px solid">%s</div><p style="margin: 0">text</p>';
        $this->assertSame('┌─┐ text', rtrim($this->render(\sprintf($html, 'max-width: 10ch', 'A'), 20)[0]), 'Shrunk to its content.');
        $this->assertSame('┌────────┐ text', rtrim($this->render(\sprintf($html, 'max-width: 10ch', 'one two three four'), 20)[0]), 'Its content wider: all of it, its lines wrapped in it.');
        $this->assertSame('  ┌────────┐   text', rtrim($this->render(\sprintf($html, 'max-width: 10ch; margin: 0 2ch', 'one two three four'), 30)[0]), 'Its margins beside it.');
        $this->assertSame('  ┌──────┐   text', rtrim($this->render(\sprintf($html, 'max-width: 10ch; margin: 0 2ch', 'abcdef'), 30)[0]), 'Narrower than it by less than its margins: its content still.');
        $this->assertSame('┌────────┐ text', rtrim($this->render(\sprintf($html, 'width: 10ch', 'A'), 20)[0]), 'A width: that width.');
        $this->assertSame('┌────┐ text', rtrim($this->render(\sprintf($html, 'width: 10ch; max-width: 6ch', 'A'), 20)[0]), 'A width bound by its max-width.');
    }

    public function testThePercentageMarginsOfAFloatAreSharesOfTheBlockItIsIn()
    {
        foreach (['width: 4ch; ', ''] as $width) {
            $lines = $this->render('<div style="float: left; '.$width.'margin-left: 10%; border: 1px solid">AB</div><p style="margin: 0">text</p>', 20);
            $this->assertSame(['  ┌──┐ text', '  │AB│'], array_map(rtrim(...), \array_slice($lines, 0, 2)), $width ?: 'Sized by its content.');
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

    public function testAFloatedLegendIsInTheFieldsetNotInItsBorder()
    {
        $html = '<style>fieldset { border: 1px solid } p { margin: 0 } .float { float: left; width: 100%% } .float + * { clear: left }</style><fieldset><legend class="%s">Title</legend><p>body</p></fieldset>';
        $screen = fn (string $class) => array_map(rtrim(...), \array_slice($this->render(\sprintf($html, $class), 20), 0, 4));
        $this->assertSame(['┌─ Title ──────────┐', '│ body             │', '└──────────────────┘'], $screen(''), 'In the top border.');
        $this->assertSame(['┌──────────────────┐', '│  Title           │', '│ body             │', '└──────────────────┘'], $screen('float'), 'Floated, like the legend of Bootstrap: a block of the fieldset, like browsers.');
    }

    public function testALegendIsPlacedInTheBorderLikeABlock()
    {
        $html = '<style>fieldset { border: 1px solid } p { margin: 0 }</style><fieldset %s><legend style="%s">Title</legend><p>body</p></fieldset>';
        $top = fn (string $legend, string $fieldset = '') => rtrim($this->render(\sprintf($html, $fieldset, $legend), 20)[0]);
        $this->assertSame('┌─ Title ──────────┐', $top(''), 'At the start, inside the padding.');
        $this->assertSame('┌────────── Title ─┐', $top('', 'dir="rtl"'), 'Right to left, at the start on the right, like browsers.');
        $this->assertSame('┌────────── Title ─┐', $top('margin-left: auto'), 'Pushed to the end by an auto margin.');
        $this->assertSame('┌──── Title ───────┐', $top('margin: 0 auto 0 3ch'), 'Its margins over the border, which shows under them.');
        $this->assertSame('┌───── Title ──────┐', $top('margin: 0 auto'), 'Centered by both.');
        $this->assertSame('┌───── Title ──────┐', $top('justify-self: center'), 'Or by justify-self.');
    }

    public function testJustifySelfLeftAndRightAreTheSameRightToLeft()
    {
        $legend = '<style>fieldset { border: 1px solid } p { margin: 0 }</style><fieldset dir="rtl"><legend style="justify-self: %s">Title</legend><p>body</p></fieldset>';
        $grid = '<div style="display: grid; grid-template-columns: 10ch; direction: rtl"><p style="justify-self: %s; margin: 0">a</p></div>';
        $first = fn (string $html, string $value) => rtrim($this->render(\sprintf($html, $value), 20)[0]);
        $this->assertSame('┌─ Title ──────────┐', $first($legend, 'left'), 'Physical sides, like CSS.');
        $this->assertSame('┌────────── Title ─┐', $first($legend, 'start'), 'The start on the right.');
        // The grid placed from the right, its track at columns 10 to 19
        $this->assertSame(str_repeat(' ', 10).'a', $first($grid, 'left'));
        $this->assertSame(str_repeat(' ', 19).'a', $first($grid, 'right'));
        $this->assertSame(str_repeat(' ', 10).'a', $first($grid, 'end'), 'The end on the left.');
    }

    public function testTheAlignOfALegendPlacesItInTheBorder()
    {
        $html = '<style>fieldset { border: 1px solid } p { margin: 0 }</style><fieldset><legend align="%s">Title</legend><p>body</p></fieldset>';
        $top = fn (string $align) => rtrim($this->render(\sprintf($html, $align), 20)[0]);
        $this->assertSame('┌─ Title ──────────┐', $top('left'));
        $this->assertSame('┌───── Title ──────┐', $top('center'), 'Its presentational hint, justify-self, like the HTML Standard.');
        $this->assertSame('┌────────── Title ─┐', $top('RIGHT'));
    }

    public function testTheMarkerOfAListItemIsOnItsFirstLineBelowTheRowsTooNarrowBesideAFloat()
    {
        $lines = array_map(rtrim(...), $this->render('<div style="float: left; width: 30ch; height: 2">F</div><p>x</p><ul style="padding-left: 4ch; list-style: disc"><li>URL dispatcher</li></ul>', 40, 3));
        $this->assertSame(['F                              x', '', '    • URL dispatcher'], $lines, 'Not alone beside the float, its text below it.');
    }

    public function testTheLinesBesideAFloatAreCutToTheRoomOfEachFrame()
    {
        // The same lines of a box between floats, cut to the room they leave at each width of the terminal, and to the
        // same one again: the float after it where the room ends
        $html = '<div style="float: left; width: 3ch">L</div><div style="float: right; width: 3ch">R</div><div style="overflow: hidden; width: 6ch; border: 1px solid">mid</div><p>after</p>';
        $terminal = new VirtualTerminal(20, 8);
        $tui = new Tui(null, $terminal);
        (new HtmlBuilder())->setDefaultStyles(false)->build($html)->mount($tui);
        $tui->start();
        foreach ([20, 30, 20, 12] as $columns) {
            $terminal->simulateResize($columns, 8);
            $terminal->clearOutput();
            $tui->requestRender(true);
            $tui->processRender();
            $buffer = new ScreenBuffer($columns, 8);
            $buffer->write($terminal->getOutput());
            $this->assertSame($this->render($html, $columns), explode("\n", $buffer->getScreen()), 'At '.$columns.' columns.');
        }
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
