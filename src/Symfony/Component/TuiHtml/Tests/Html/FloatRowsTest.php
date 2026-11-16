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
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Tui\Ansi\AnsiCodeTracker;
use Symfony\Component\Tui\Ansi\AnsiUtils;
use Symfony\Component\Tui\Terminal\ScreenBuffer;
use Symfony\Component\Tui\Terminal\VirtualTerminal;
use Symfony\Component\Tui\Tui as NativeTui;
use Symfony\Component\TuiHtml\Html\HtmlBuilder;
use Symfony\Component\TuiHtml\Render\LayoutEngine;
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

    /**
     * @param list<string> $expected
     */
    #[DataProvider('alignedBesideFloats')]
    public function testEachLineBesideAFloatIsAlignedInTheRoomOfItsRow(string $side, string $align, array $expected, string $text = 'Yy aa bb cc dd ee ff gg hh ii jj kk ll mm')
    {
        $lines = $this->render('<style>.f { float: '.$side.'; width: 3ch; height: 2 } p { margin: 0; text-align: '.$align.' }</style><div class="f">F</div><p>'.$text.'</p>', 20);
        $this->assertSame($expected, array_map('rtrim', \array_slice($lines, 0, \count($expected))));
    }

    public static function alignedBesideFloats(): iterable
    {
        yield 'right, beside a right float' => ['right', 'right', ['  Yy aa bb cc dd F', '  ee ff gg hh ii', '         jj kk ll mm']];
        yield 'right, beside a left float' => ['left', 'right', ['F     Yy aa bb cc dd', '      ee ff gg hh ii', '         jj kk ll mm']];
        yield 'center, beside a right float' => ['right', 'center', [' Yy aa bb cc dd  F', ' ee ff gg hh ii', '    jj kk ll mm']];
        yield 'center, beside a left float' => ['left', 'center', ['F    Yy aa bb cc dd', '     ee ff gg hh ii', '    jj kk ll mm']];
        // Justified, each line in the room of its row, below the float in the whole width; the last line of each
        // paragraph, before a forced break and at the end, at the start
        $justified = 'aa bb cc dd ee ff gg hh ii jj kk ll mm nn oo pp q rr ss tt uu<br>vv ww';
        yield 'justified, beside a left float' => ['left', 'justify', ['F   aa  bb  cc dd ee', '    ff  gg  hh ii jj', 'kk  ll mm nn oo pp q', 'rr ss tt uu', 'vv ww'], $justified];
        yield 'justified, beside a right float' => ['right', 'justify', ['aa  bb  cc dd ee F', 'ff  gg  hh ii jj', 'kk  ll mm nn oo pp q', 'rr ss tt uu', 'vv ww'], $justified];
        yield 'a single line, right, beside a right float' => ['right', 'right', ['               Y F'], 'Y'];
        yield 'a single line, center, beside a right float' => ['right', 'center', ['       Y         F'], 'Y'];
    }

    /**
     * @param list<string> $expected
     */
    #[DataProvider('beforeBesideFloats')]
    public function testTheBeforeOfABlockIsBesideAFloatLikeItsLines(string $html, array $expected)
    {
        $lines = $this->render('<style>p { margin: 0 } .d::before { content: "BEF"; display: block } .w::before { content: "aaaa bbbb cccc dddd"; display: block } .i::before { content: "INL " } .a::after { content: "AFT"; display: block } .aw::after { content: "aaaa bbbb cccc dddd"; display: block }</style>'.$html, 20);
        $this->assertSame($expected, array_map('rtrim', \array_slice($lines, 0, \count($expected))));
    }

    public static function beforeBesideFloats(): iterable
    {
        yield 'a left float' => ['<div style="float: left; width: 1ch; height: 5">F</div><p>X</p><div class="d"><p>C</p></div><div class="i"><p>D</p></div>', ['F X', '  BEF', '  C', '  INL', '  D']];
        yield 'a left float ending beside it' => ['<div style="float: left; width: 1ch; height: 2">F</div><p>X</p><div class="d"><p>C</p><p>D</p></div>', ['F X', '  BEF', 'C', 'D']];
        yield 'a right float, its text wrapped beside it' => ['<div style="float: right; width: 3ch; height: 3">F</div><p>X</p><div class="w"><p>C</p></div>', ['X                F', 'aaaa bbbb cccc', 'dddd', 'C']];
        // Floats on both sides leaving no room: its text below them, like that of a paragraph
        yield 'no room between floats' => ['<div style="float: left; width: 9ch; height: 3">L</div><div style="float: right; width: 9ch; height: 3">R</div><div class="w"><p>C</p></div>', ['L          R', '', '', 'aaaa bbbb cccc dddd', 'C']];
        yield 'an ::after, a left float' => ['<div style="float: left; width: 1ch; height: 5">F</div><p>X</p><div class="a"><p>C</p></div><p>D</p>', ['F X', '  C', '  AFT', '  D']];
        yield 'an ::after, a left float ending before it' => ['<div style="float: left; width: 1ch; height: 2">F</div><p>X</p><div class="a"><p>C</p></div><p>D</p>', ['F X', '  C', 'AFT', 'D']];
        yield 'an ::after, a right float, its text wrapped beside it' => ['<div style="float: right; width: 3ch; height: 4">F</div><p>X</p><div class="aw"><p>C</p></div><p>D</p>', ['X                F', 'C', 'aaaa bbbb cccc', 'dddd', 'D']];
        // Below the rows of a ::before that clears, which the floats around are measured from
        yield 'an ::after after a ::before that clears' => ['<style>.c::before { content: ""; display: block; clear: left } .c::after { content: "aaaa bbbb cccc dddd eeee"; display: block }</style><div style="float: left; width: 1ch; height: 3">L</div><div style="float: right; width: 4ch; height: 6">R</div><div class="c"><p>C</p></div>', ['L               R', '', '', 'C', 'aaaa bbbb cccc', 'dddd eeee']];
        // Drawn over the last row of the content by the pull of the last child: beside the float on that row
        yield 'an ::after pulled up beside a float' => ['<div style="float: right; width: 3ch; height: 2">F</div><p>X</p><div class="aw"><p style="margin-bottom: -1lh">C</p></div><p>N</p>', ['X                F', 'aaaa bbbb cccc', 'dddd', 'N']];
        // Under a ::before that clears floats on its left, the content past the one on the right that ended
        yield 'a ::before that clears, a float ending beside it' => ['<style>.k::before { content: "BEF"; display: block; clear: left }</style><div style="float: left; width: 2ch; height: 2">L</div><div style="float: right; width: 3ch; height: 3">R</div><div class="k"><p>C aaaa bbbb cccc ddd</p><p>D</p></div>', ['L                R', '', 'BEF', 'C aaaa bbbb cccc ddd', 'D']];
        // Pulled by more rows than it has: from the row it starts at, the rows of the content under it after it as they were
        yield 'an ::after pulled by more rows than it has' => ['<div style="float: left; width: 2ch; height: 3">F</div><p>X</p><div class="a"><p>C</p><p>D</p><p style="margin-bottom: -2lh">E</p></div><p>N</p>', ['F  X', '   C', '   AFT', 'E', 'N']];
        // A block with a border beside floats: its border box the whole width, under the floats, its sides drawn where
        // they draw nothing, like CSS
        yield 'a block with a border' => ['<div style="float: left; width: 2ch; height: 2">L</div><div style="float: right; width: 3ch; height: 5">R</div><div style="border: 1px solid"><p>C</p><p>D</p></div>', ['L────────────────R─┐', '│  C               │', '│D                 │', '└──────────────────┘']];
        yield 'an inline one' => ['<div style="float: left; width: 1ch; height: 3">F</div><p>X</p><p class="i">G</p><div class="i"><p>D</p></div>', ['F X', '  INL G', '  INL', 'D']];
    }

    /**
     * @param list<string> $expected
     */
    #[DataProvider('justifiedBesideFloats')]
    public function testJustifiedLinesBesideAFloatAreWrappedAndAlignedLikeTheOthers(string $html, array $expected)
    {
        $lines = $this->render($html, 20);
        $this->assertSame($expected, array_map('rtrim', \array_slice($lines, 0, \count($expected))));
    }

    public static function justifiedBesideFloats(): iterable
    {
        $text = 'aa bb cc dd ee ff gg hh<br>ii jj kk ll mm nn oo pp<br>q r';
        // The gap after the marker outside the text not spread
        yield 'a list item' => ['<style>.f { float: left; width: 4ch; height: 6 } ul { margin: 0; padding: 0 0 0 2ch } li { text-align: justify }</style><div class="f">F</div><ul><li>'.$text.'</li></ul>', ['F    • aa  bb  cc dd', '       ee ff gg hh', '       ii  jj  kk ll', '       mm nn oo pp', '       q r']];
        // Wrapped with its hanging indent: the last line of each paragraph at the start
        yield 'a hanging indent' => ['<style>.f { float: left; width: 6ch; height: 6 } p { margin: 0; text-align: justify; text-indent: -3ch; padding-left: 3ch }</style><div class="f">F</div><p>'.$text.'</p>', ['F      aa  bb  cc dd', '          ee  ff  gg', '          hh', '          ii  jj  kk', '          ll  mm  nn', '          oo pp', '   q r']];
        $text = 'aa bb cc dd ee ff gg hh ii jj kk ll mm nn oo';
        yield 'text-align-last: right' => ['<style>.f { float: left; width: 3ch; height: 2 } p { margin: 0; text-align: justify; text-align-last: right }</style><div class="f">F</div><p>'.$text.'</p>', ['F   aa  bb  cc dd ee', '    ff  gg  hh ii jj', '      kk ll mm nn oo']];
        yield 'text-align-last: justify' => ['<style>.f { float: left; width: 3ch; height: 2 } p { margin: 0; text-align: justify; text-align-last: justify }</style><div class="f">F</div><p>'.$text.'</p>', ['F   aa  bb  cc dd ee', '    ff  gg  hh ii jj', 'kk   ll   mm  nn  oo']];
    }

    /**
     * @param list<string> $expected
     */
    #[DataProvider('rightToLeftBesideALeftFloat')]
    public function testALineRunningRightToLeftIsPastAFloatOnItsLeft(string $html, array $expected)
    {
        $lines = $this->render('<style>.f { float: left; width: 3ch; height: 3 } p, ul { margin: 0; direction: rtl }</style><div class="f">F</div>'.$html, 20);
        $this->assertSame($expected, array_map('rtrim', \array_slice($lines, 0, \count($expected))));
    }

    public static function rightToLeftBesideALeftFloat(): iterable
    {
        $text = 'aaa bbb ccc ddd eee fff ggg hhh iii jjj';
        yield 'at the left' => ['<p style="text-align: left">'.$text.'</p>', ['F   aaa bbb ccc ddd', '    eee fff ggg hhh', '    iii jjj']];
        // Its last line at its start, on the right
        yield 'justified' => ['<p style="text-align: justify">'.$text.'</p>', ['F   aaa  bbb ccc ddd', '    eee  fff ggg hhh', '             iii jjj']];
        yield 'a list item, its marker on its right' => ['<ul style="padding: 0 3ch 0 0"><li style="text-align: justify">'.$text.'</li></ul>', ['F   aaa bbb ccc •']];
    }

    /**
     * Reordered, which takes the intl extension.
     *
     * @param list<string> $expected
     */
    #[DataProvider('reorderedBesideALeftFloat')]
    #[RequiresPhpExtension('intl')]
    public function testALineReorderedIsPastAFloatOnItsLeft(string $html, array $expected)
    {
        $this->testALineRunningRightToLeftIsPastAFloatOnItsLeft($html, $expected);
    }

    public static function reorderedBesideALeftFloat(): iterable
    {
        // An embedding carried from the line before, isolated
        // Its own spaces at its start, those of pre-wrap, on its right, as when not beside the float, in a link too
        yield 'its own spaces kept' => ['<p style="white-space: pre-wrap"><b>  אבג abc דהו</b>'."\n".'<a href="http://x">  זחט def יכל</a></p>', ['F      והד abc גבא', '       לכי def טחז']];
        yield 'a run left to right wrapped' => ['<p style="text-align: left">aaa <span dir="ltr">bbb ccc ddd eee fff ggg</span> hhh</p>', ['F   bbb ccc ddd aaa', '    hhh eee fff ggg']];
    }

    #[RequiresPhpExtension('intl')]
    public function testTheLastLineOfAJustifiedParagraphIsAtTheStartOfItsDirection()
    {
        // Of its first strong character, like unicode-bidi: plaintext, left to right in a block right to left
        $lines = $this->render('<style>p { margin: 0; direction: rtl; unicode-bidi: plaintext; text-align: justify }</style><p>aaa bbb ccc ddd eee fff ggg hhh</p>', 20);
        $this->assertSame(['aaa  bbb ccc ddd eee', 'fff ggg hhh'], array_map('rtrim', \array_slice($lines, 0, 2)));
    }

    #[RequiresPhpExtension('intl')]
    public function testTextJustifyNoneKeepsTheLinesAtTheStartOfTheirOwnDirection()
    {
        $lines = $this->render('<style>p { margin: 0; direction: rtl; unicode-bidi: plaintext; text-align: justify; text-justify: none }</style><p>aaa bbb ccc ddd eee fff ggg hhh</p>', 20);
        $this->assertSame(['aaa bbb ccc ddd eee', 'fff ggg hhh'], array_map('rtrim', \array_slice($lines, 0, 2)));
    }

    public function testTheLastLineOfAJustifiedParagraphRunningRightToLeftIsOnItsRight()
    {
        $lines = $this->render('<style>p { margin: 0; direction: rtl; text-align: justify }</style><p>aaa bbb ccc ddd eee fff ggg hhh iii jjj kk</p>', 20);
        $this->assertSame(['aaa  bbb ccc ddd eee', 'fff  ggg hhh iii jjj', '                  kk'], array_map('rtrim', \array_slice($lines, 0, 3)));
    }

    /**
     * The sequences that are no cells, like the marker of the cursor and those of text drawn larger, kept in the lines
     * beside a float drawn over what flows under it, on a background, which is drawn under it.
     */
    #[DataProvider('backgrounds')]
    public function testTheSequencesOfALineBesideAFloatAreKept(string $background)
    {
        $tui = new Tui(null, new VirtualTerminal(30, 6));
        $view = (new HtmlBuilder())->setDefaultStyles(false)->build('<div style="'.$background.'"><div style="float: left; width: 3ch; height: 3">F</div><p id="p" style="margin: 0">see <a href="https://example.com">this</a> link</p></div>');
        $view->mount($tui);
        $tui->start();
        $tui->processRender();
        $tui->setFocus($view->getById('p'));
        $this->assertStringContainsString(AnsiUtils::CURSOR_MARKER_PREFIX, implode('', $this->frame($tui, 30, 6)), 'The cursor of the focused link.');
        $tui->stop();
        $tui = new Tui(null, new VirtualTerminal(30, 8));
        $tui->setMediaContext($tui->getMediaContext()->withTextSizing(true));
        (new HtmlBuilder())->setDefaultStyles(false)->build('<div style="'.$background.'"><div style="float: left; width: 3ch; height: 4">F</div><h1 style="margin: 0; font-size: 2em">Big</h1></div>')->mount($tui);
        $tui->start();
        $tui->processRender();
        $this->assertStringContainsString("\x1b_pi:s;", implode('', $this->frame($tui, 30, 8)), 'The heading drawn larger.');
        $tui->stop();
    }

    public static function backgrounds(): iterable
    {
        yield 'none' => [''];
        yield 'a background' => ['background: blue'];
    }

    public function testAFloatIsDrawnOverItsColumnsOnly()
    {
        $over = static fn (string $text, string $line, int $x, int $columns = 20): string => (new \ReflectionMethod(LayoutEngine::class, 'floatOver'))->invoke(null, $text, $line, $x, AnsiUtils::visibleWidth($line), $columns, false);
        $cursor = "\x1b_pi:c;0\x07";
        // A marker of the cursor before it once, not again after it
        $this->assertSame(1, substr_count($over('ab'.$cursor.'cdefghijklmnopqrst', 'F  ', 10), $cursor));
        // Nor does it hide what is under its blank cells there
        $this->assertSame('abcFefghijklmnopqrst', AnsiUtils::stripAnsiCodes($over('ab'.$cursor.'cdefghijklmnopqrst', 'F  ', 3)));
        // One under it kept, after it
        $this->assertSame(1, substr_count($over('abcd'.$cursor.'efghijklmnopqrst', 'F  ', 3), $cursor));
        // Not in the link under it, which goes on after it
        $drawn = $over("a\x1b]8;;http://x\x07bcdefgh\x1b]8;;\x07ij", 'F  ', 3, 10);
        $inLink = static function (string $line, string $character): bool {
            $tracker = new AnsiCodeTracker();
            $tracker->processText(substr($line, 0, (int) strpos($line, $character)));

            return $tracker->inLink();
        };
        $this->assertSame([true, false, false, true], [$inLink($drawn, 'c'), $inLink($drawn, 'F'), $inLink($drawn, 'e'), $inLink($drawn, 'g')]);
        // At its column where the float shows what is under it, on a row of text or on a blank one
        $column = static fn (string $drawn): int => AnsiUtils::visibleWidth(substr($drawn, 0, (int) strpos($drawn, $cursor)));
        $this->assertSame([2, 2], [$column($over('ab'.$cursor.'cdefghijklmnopqrst', 'F  ', 1)), $column($over('ab'.$cursor.'cdefghijklmnopqrst', '   ', 1))]);
        // After it where it covers it
        $this->assertSame(6, $column($over('abc'.$cursor.'   defghij', 'F  ', 3)));
        // The style under it and after it as it was, in any form, like a color with colons
        $drawn = $over("ab\x1b[48:2::255:0:0mcdefg\x1b[0mhij", 'F  ', 2);
        $this->assertStringContainsString("\x1b[48:2::255:0:0m", substr($drawn, (int) strpos($drawn, 'e')), 'The background of the cells after it.');
        // A tab as many columns as it is drawn in, the sequences of every form none
        $this->assertStringContainsString("a\tFcd", AnsiUtils::stripAnsiCodes($over("a\tbcdefghij", 'F  ', 4)));
        $this->assertSame('abcdefFhij', AnsiUtils::stripAnsiCodes($over("ab\x1b_pi:c;0\x1b\\cd\x1b[2 qefghij", 'F  ', 6)));
        // Past the edges of the row: cut to it
        $this->assertSame('abcdefghijklmnopqrFt', AnsiUtils::stripAnsiCodes($over('abcdefghijklmnopqrst', 'F  ', 18)));
        $this->assertSame('abcdefghijklmnopqrst', AnsiUtils::stripAnsiCodes($over('abcdefghijklmnopqrst', 'F  ', -1)));
    }

    public function testWhatPaintsCellsIsToldByTheirStyle()
    {
        $paints = static fn (string $line): bool => (new \ReflectionMethod(LayoutEngine::class, 'paints'))->invoke(null, $line, new AnsiCodeTracker());
        $this->assertSame([false, false, true, true, true, true], [$paints("\x1b[38;5;44mx"), $paints("\x1b[38;2;4;7;9mx"), $paints("\x1b[44mx"), $paints("\x1b[7mx"), $paints("\x1b[4mx"), $paints("\x1b[53mx")]);
    }

    public function testALinkFloatedOverIsNotFollowedOnTheFloat()
    {
        $tui = new Tui(null, new VirtualTerminal(20, 4));
        (new HtmlBuilder())->setDefaultStyles(false)->build('<div style="float: right; width: 3ch; height: 2">R</div><p style="margin: 0; white-space: nowrap"><a href="http://x">abcdefghijklmnopqrstuvwxyz</a></p>')->mount($tui);
        $tui->start();
        $tui->processRender();
        $line = $this->frame($tui, 20, 4)[0];
        $tracker = new AnsiCodeTracker();
        $tracker->processText(substr($line, 0, (int) strpos($line, 'R')));
        $this->assertFalse($tracker->inLink(), 'The float over the link.');
        $tui->stop();
    }

    #[RequiresPhpExtension('gd')]
    public function testAnImageFloatedHidesWhatFlowsUnderIt()
    {
        $png = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';
        $lines = $this->render('<style>p { margin: 0 }</style><div><div style="border: 1px solid"><img src="'.$png.'" style="float: left; width: 6ch; height: 4"><p>In a box,</p></div><p>after</p></div>', 20);
        $this->assertSame('└      ────────────┘', rtrim($lines[2]), 'A replaced element paints its box, like CSS: the border under it hidden.');
        $lines = $this->render('<style>p { margin: 0 }</style><div><div style="border: 1px solid"><span style="float: left; width: 6ch; height: 4">IMG</span><p>In a box,</p></div><p>after</p></div>', 20);
        $this->assertSame('└──────────────────┘', rtrim($lines[2]), 'An element of no background lets it show.');
        // Its margins letting it show
        $lines = $this->render('<style>p { margin: 0 }</style><div><div style="border: 1px solid"><img src="'.$png.'" style="float: left; width: 6ch; height: 4; margin-right: 2ch; margin-bottom: 1"><p>In a box,</p></div><p>after</p></div>', 20);
        $this->assertSame('└      ────────────┘', rtrim($lines[2]), 'Its right margin, under it.');
    }

    #[RequiresPhpExtension('gd')]
    public function testAFloatHoldingAnImageHidesWhatFlowsUnderItOnAllItsRows()
    {
        $png = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';
        // Its rows under the image too, its box painted like that of the image
        $lines = $this->render('<style>p { margin: 0 }</style><div><div style="border: 1px solid"><div style="float: left; width: 6ch; height: 4"><img src="'.$png.'" style="width: 2ch; height: 1"></div><p>In a box,</p></div><p>after</p></div>', 20);
        $this->assertSame('└      ────────────┘', rtrim($lines[2]), 'Its box painted.');
        // Its margins letting it show
        $lines = $this->render('<style>p { margin: 0 }</style><div><div style="border: 1px solid"><div style="float: left; width: 3ch; height: 4; margin-right: 3ch"><img src="'.$png.'" style="width: 2ch; height: 1"></div><p>In a box,</p></div><p>after</p></div>', 20);
        $this->assertSame('└   ───────────────┘', rtrim($lines[2]), 'Its right margin, under it.');
    }

    #[DataProvider('provideHiddenImages')]
    #[RequiresPhpExtension('gd')]
    public function testAFloatHoldingOnlyAnImageNotDrawnLetsWhatFlowsUnderItShow(string $image)
    {
        $png = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';
        $lines = $this->render('<style>p { margin: 0 }</style><div><div style="border: 1px solid"><div style="float: left; width: 6ch; height: 4">'.str_replace('PNG', $png, $image).'</div><p>In a box,</p></div><p>after</p></div>', 20);
        $this->assertSame('└──────────────────┘', rtrim($lines[2]));
    }

    public static function provideHiddenImages(): iterable
    {
        yield 'display: none' => ['<img src="PNG" style="display: none; width: 2ch; height: 1">'];
        yield 'hidden' => ['<img src="PNG" hidden style="width: 2ch; height: 1">'];
        yield 'visibility: hidden' => ['<img src="PNG" style="visibility: hidden; width: 2ch; height: 1">'];
        yield 'in a box not displayed' => ['<div style="display: none"><img src="PNG" style="width: 2ch; height: 1"></div>'];
        yield 'in a box hidden' => ['<div style="visibility: hidden"><svg width="16" height="16"><rect width="16" height="16"/></svg></div>'];
        yield 'transparent' => ['<img src="PNG" style="opacity: 0; width: 2ch; height: 1">'];
        yield 'in a box of its content hidden' => ['<div style="content-visibility: hidden"><img src="PNG" style="width: 2ch; height: 1"></div>'];
    }

    public function testTextOverflowingUnderAFloatShowsInItsBlankCells()
    {
        // Under the float, its glyphs over the text, the text in the cells it draws nothing in
        $this->assertSame('abcdefghijklmnopqRst', rtrim($this->render('<style>p { margin: 0; white-space: nowrap }</style><div style="float: right; width: 3ch; height: 2">R</div><p>abcdefghijklmnopqrstuvwxyz</p>', 20)[0]));
    }

    public function testALineAlignedBesideAFloatKeepsItsStyledCellsAtItsEnd()
    {
        $terminal = new VirtualTerminal(20, 4);
        $tui = new Tui(null, $terminal);
        (new HtmlBuilder())->setDefaultStyles(false)->build('<div style="float: right; width: 3ch; height: 2">F</div><p style="margin: 0; text-align: right">ab <span style="background: red">cd&nbsp;&nbsp;</span></p>')->mount($tui);
        $tui->start();
        $tui->processRender();
        $buffer = new ScreenBuffer(20, 4);
        $buffer->write($terminal->consumeOutput());
        $red = array_keys(array_filter($buffer->getCells()[0], static fn (array $cell): bool => str_contains($cell['style'], '41')));
        $this->assertSame([12, 13, 14, 15], $red, 'Its two no-break spaces in red, against the room beside the float.');
        $tui->stop();
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
        // Its line showing through the cells of the float that draw nothing, like CSS, which paints the float over it
        $this->assertSame('F───────────────────', rtrim($this->render(\sprintf($html, ''), 20)[0]), 'Its whole width, under the float.');
        $this->assertSame('F───────────────────', rtrim($this->render(\sprintf($html, 'overflow: clip'), 20)[0]), 'No formatting context of its own.');
        $this->assertSame('F───────────────────', rtrim($this->render('<style>hr::after { content: ""; display: table; clear: both }</style>'.\sprintf($html, ''), 20)[0]), 'Nor one cleared by its ::after.');
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

    /**
     * @return list<string>
     */
    private function frame(Tui $tui, int $columns, int $rows): array
    {
        $root = \Closure::bind(fn () => $this->root, $tui, NativeTui::class)();

        return $tui->getRenderer()->renderFrame($root, $columns, $rows)->toArray();
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
