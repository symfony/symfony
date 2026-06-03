<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Tui\Tests\Ansi;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Tui\Ansi\AnsiCodeTracker;

class AnsiCodeTrackerTest extends TestCase
{
    private AnsiCodeTracker $tracker;

    protected function setUp(): void
    {
        $this->tracker = new AnsiCodeTracker();
    }

    // --------------------------------------------------
    // Basic SGR attributes on/off
    // --------------------------------------------------

    /**
     * @return iterable<string, array{int, int}>
     */
    public static function sgrAttributeOnOffProvider(): iterable
    {
        yield 'bold' => [1, 22];
        yield 'dim' => [2, 22];
        yield 'italic' => [3, 23];
        yield 'underline' => [4, 24];
        yield 'blink' => [5, 25];
        yield 'inverse' => [7, 27];
        yield 'hidden' => [8, 28];
        yield 'strikethrough' => [9, 29];
    }

    #[DataProvider('sgrAttributeOnOffProvider')]
    public function testSgrAttributeOnAndOff(int $onCode, int $offCode)
    {
        $this->tracker->process("\x1b[{$onCode}m");
        $this->assertTrue($this->tracker->hasActiveCodes());
        $this->assertSame("\x1b[{$onCode}m", $this->tracker->getActiveCodes());

        $this->tracker->process("\x1b[{$offCode}m");
        $this->assertFalse($this->tracker->hasActiveCodes());
    }

    public function testBoldAndDimResetTogether()
    {
        $this->tracker->process("\x1b[1m");
        $this->tracker->process("\x1b[2m");
        $this->assertSame("\x1b[1;2m", $this->tracker->getActiveCodes());

        // SGR 22 resets both bold and dim
        $this->tracker->process("\x1b[22m");
        $this->assertFalse($this->tracker->hasActiveCodes());
    }

    public function testSgr21SetsDoubleUnderline()
    {
        $this->tracker->process("\x1b[21m");
        $this->assertTrue($this->tracker->hasActiveCodes());
        $this->assertSame("\x1b[21m", $this->tracker->getActiveCodes());
    }

    public function testSgr21DoesNotTurnOffBold()
    {
        $this->tracker->process("\x1b[1m");
        $this->assertTrue($this->tracker->hasActiveCodes());

        // SGR 21 is "doubly underlined" per ECMA-48, not bold-off.
        // SGR 22 is the correct code to turn off bold.
        $this->tracker->process("\x1b[21m");
        $this->assertTrue($this->tracker->hasActiveCodes());
        $this->assertSame("\x1b[1;21m", $this->tracker->getActiveCodes());
    }

    public function testSgr24ResetsDoubleUnderline()
    {
        $this->tracker->process("\x1b[21m");
        $this->assertTrue($this->tracker->hasActiveCodes());

        $this->tracker->process("\x1b[24m");
        $this->assertFalse($this->tracker->hasActiveCodes());
    }

    public function testSgr24ResetsBothUnderlineAndDoubleUnderline()
    {
        $this->tracker->process("\x1b[4m");
        $this->tracker->process("\x1b[21m");
        $this->assertSame("\x1b[4;21m", $this->tracker->getActiveCodes());

        $this->tracker->process("\x1b[24m");
        $this->assertFalse($this->tracker->hasActiveCodes());
    }

    public function testGetLineEndResetWithDoubleUnderline()
    {
        $this->tracker->process("\x1b[21m");
        $this->assertSame("\x1b[24m", $this->tracker->getLineEndReset());
    }

    // --------------------------------------------------
    // Color tracking
    // --------------------------------------------------

    /**
     * @param list<string> $sequences
     */
    #[DataProvider('colorTrackingProvider')]
    public function testColorTracking(array $sequences, string $expected)
    {
        foreach ($sequences as $seq) {
            $this->tracker->process($seq);
        }
        $this->assertSame($expected, $this->tracker->getActiveCodes());
    }

    /**
     * @return iterable<string, array{list<string>, string}>
     */
    public static function colorTrackingProvider(): iterable
    {
        // Standard foreground (30-37)
        yield 'standard fg replaces previous' => [["\x1b[31m", "\x1b[37m"], "\x1b[37m"];
        // Bright foreground (90-97)
        yield 'bright fg replaces previous' => [["\x1b[91m", "\x1b[97m"], "\x1b[97m"];
        // Standard background (40-47)
        yield 'standard bg replaces previous' => [["\x1b[41m", "\x1b[47m"], "\x1b[47m"];
        // Bright background (100-107)
        yield 'bright bg replaces previous' => [["\x1b[101m", "\x1b[107m"], "\x1b[107m"];
        // Foreground + background together
        yield 'fg and bg together' => [["\x1b[31m", "\x1b[42m"], "\x1b[31;42m"];
        // 256-color mode
        yield '256-color fg' => [["\x1b[38;5;196m"], "\x1b[38;5;196m"];
        yield '256-color bg' => [["\x1b[48;5;82m"], "\x1b[48;5;82m"];
        yield '256-color fg and bg' => [["\x1b[38;5;196m", "\x1b[48;5;82m"], "\x1b[38;5;196;48;5;82m"];
        // RGB color mode
        yield 'RGB fg' => [["\x1b[38;2;255;0;0m"], "\x1b[38;2;255;0;0m"];
        yield 'RGB bg' => [["\x1b[48;2;0;255;0m"], "\x1b[48;2;0;255;0m"];
        yield 'RGB fg and bg' => [["\x1b[38;2;255;0;0m", "\x1b[48;2;0;255;0m"], "\x1b[38;2;255;0;0;48;2;0;255;0m"];
    }

    // --------------------------------------------------
    // Reset behavior
    // --------------------------------------------------

    public function testSgr0ResetsEverything()
    {
        $this->tracker->process("\x1b[1m"); // bold
        $this->tracker->process("\x1b[3m"); // italic
        $this->tracker->process("\x1b[31m"); // red fg
        $this->tracker->process("\x1b[42m"); // green bg
        $this->assertTrue($this->tracker->hasActiveCodes());

        $this->tracker->process("\x1b[0m");
        $this->assertFalse($this->tracker->hasActiveCodes());
        $this->assertSame('', $this->tracker->getActiveCodes());
    }

    public function testSgr39ResetsForegroundOnly()
    {
        $this->tracker->process("\x1b[31m"); // red fg
        $this->tracker->process("\x1b[42m"); // green bg
        $this->assertSame("\x1b[31;42m", $this->tracker->getActiveCodes());

        $this->tracker->process("\x1b[39m"); // reset fg
        $this->assertSame("\x1b[42m", $this->tracker->getActiveCodes());
    }

    public function testSgr49ResetsBackgroundOnly()
    {
        $this->tracker->process("\x1b[31m"); // red fg
        $this->tracker->process("\x1b[42m"); // green bg
        $this->assertSame("\x1b[31;42m", $this->tracker->getActiveCodes());

        $this->tracker->process("\x1b[49m"); // reset bg
        $this->assertSame("\x1b[31m", $this->tracker->getActiveCodes());
    }

    // --------------------------------------------------
    // Combined codes
    // --------------------------------------------------

    /**
     * @param list<string> $sequences
     */
    #[DataProvider('combinedCodesProvider')]
    public function testCombinedCodes(array $sequences, string $expected)
    {
        foreach ($sequences as $seq) {
            $this->tracker->process($seq);
        }
        $this->assertSame($expected, $this->tracker->getActiveCodes());
    }

    /**
     * @return iterable<string, array{list<string>, string}>
     */
    public static function combinedCodesProvider(): iterable
    {
        yield 'bold + red fg' => [["\x1b[1;31m"], "\x1b[1;31m"];
        yield 'bold + italic + underline + red fg + green bg' => [["\x1b[1;3;4;31;42m"], "\x1b[1;3;4;31;42m"];
        yield 'reset then green' => [["\x1b[1;31m", "\x1b[0;32m"], "\x1b[32m"];
        yield 'bold + 256-color red' => [["\x1b[1;38;5;196m"], "\x1b[1;38;5;196m"];
        yield 'bold + RGB red' => [["\x1b[1;38;2;255;0;0m"], "\x1b[1;38;2;255;0;0m"];
    }

    // --------------------------------------------------
    // getActiveCodes()
    // --------------------------------------------------

    public function testGetActiveCodesPreservesOrder()
    {
        // Order should always be: bold, dim, italic, underline, blink, inverse, hidden, strikethrough, fg, bg
        $this->tracker->process("\x1b[42m"); // bg first
        $this->tracker->process("\x1b[1m");  // then bold
        $this->tracker->process("\x1b[31m"); // then fg
        $this->tracker->process("\x1b[4m");  // then underline

        // Output is always in canonical order regardless of input order
        $this->assertSame("\x1b[1;4;31;42m", $this->tracker->getActiveCodes());
    }

    // --------------------------------------------------
    // hasActiveCodes()
    // --------------------------------------------------

    /**
     * @return iterable<string, array{string}>
     */
    public static function hasActiveCodesProvider(): iterable
    {
        yield 'attribute (bold)' => ["\x1b[1m"];
        yield 'foreground color' => ["\x1b[31m"];
        yield 'background color' => ["\x1b[42m"];
    }

    #[DataProvider('hasActiveCodesProvider')]
    public function testHasActiveCodesReturnsTrueWithCode(string $code)
    {
        $this->tracker->process($code);
        $this->assertTrue($this->tracker->hasActiveCodes());
    }

    public function testHasActiveCodesReturnsFalseAfterReset()
    {
        $this->tracker->process("\x1b[1;31;42m");
        $this->assertTrue($this->tracker->hasActiveCodes());

        $this->tracker->process("\x1b[0m");
        $this->assertFalse($this->tracker->hasActiveCodes());
    }

    // --------------------------------------------------
    // getLineEndReset()
    // --------------------------------------------------

    public function testGetLineEndResetReturnsEmptyWhenNoUnderline()
    {
        $this->assertSame('', $this->tracker->getLineEndReset());

        $this->tracker->process("\x1b[1m"); // bold, not underline
        $this->assertSame('', $this->tracker->getLineEndReset());
    }

    public function testGetLineEndResetReturnsResetWhenUnderlineActive()
    {
        $this->tracker->process("\x1b[4m");
        $this->assertSame("\x1b[24m", $this->tracker->getLineEndReset());
    }

    public function testGetLineEndResetReturnsResetWhenUnderlineActiveWithOtherCodes()
    {
        $this->tracker->process("\x1b[1;4;31m"); // bold + underline + red
        $this->assertSame("\x1b[24m", $this->tracker->getLineEndReset());
    }

    public function testGetLineEndResetReturnsEmptyAfterUnderlineOff()
    {
        $this->tracker->process("\x1b[4m");
        $this->assertSame("\x1b[24m", $this->tracker->getLineEndReset());

        $this->tracker->process("\x1b[24m");
        $this->assertSame('', $this->tracker->getLineEndReset());
    }

    // --------------------------------------------------
    // processText()
    // --------------------------------------------------

    public function testProcessTextUpdatesStateFromAnsiCodes()
    {
        $this->tracker->processText("Hello \x1b[1;31mworld\x1b[0m");
        // After processing, the reset at the end clears everything
        $this->assertFalse($this->tracker->hasActiveCodes());
    }

    public function testProcessTextTracksActiveCodesAtEnd()
    {
        $this->tracker->processText("Hello \x1b[1;31mworld");
        // bold + red is still active (no reset at end)
        $this->assertTrue($this->tracker->hasActiveCodes());
        $this->assertSame("\x1b[1;31m", $this->tracker->getActiveCodes());
    }

    public function testProcessTextWithMultipleSequences()
    {
        $this->tracker->processText("\x1b[1mHello \x1b[31mworld \x1b[42mfoo");
        // bold + red fg + green bg
        $this->assertSame("\x1b[1;31;42m", $this->tracker->getActiveCodes());
    }

    public function testProcessTextWithNoAnsiCodes()
    {
        $this->tracker->processText('Hello world');
        $this->assertFalse($this->tracker->hasActiveCodes());
    }

    public function testProcessTextWithEmptyString()
    {
        $this->tracker->processText('');
        $this->assertFalse($this->tracker->hasActiveCodes());
    }

    // --------------------------------------------------
    // Edge cases
    // --------------------------------------------------

    public function testEmptyParamsActsAsReset()
    {
        $this->tracker->process("\x1b[1;31m"); // bold + red
        $this->assertTrue($this->tracker->hasActiveCodes());

        $this->tracker->process("\x1b[m"); // empty params = reset
        $this->assertFalse($this->tracker->hasActiveCodes());
    }

    public function testNonSgrSequencesAreIgnored()
    {
        $this->tracker->process("\x1b[2J"); // clear screen - not an SGR code
        $this->assertFalse($this->tracker->hasActiveCodes());
    }

    public function testCursorMovementSequencesAreIgnored()
    {
        $this->tracker->process("\x1b[10G"); // cursor to column 10
        $this->assertFalse($this->tracker->hasActiveCodes());

        $this->tracker->process("\x1b[5A"); // cursor up 5
        $this->assertFalse($this->tracker->hasActiveCodes());
    }

    public function testResetMethod()
    {
        $this->tracker->process("\x1b[1;3;4;31;42m");
        $this->assertTrue($this->tracker->hasActiveCodes());

        $this->tracker->reset();
        $this->assertFalse($this->tracker->hasActiveCodes());
        $this->assertSame('', $this->tracker->getActiveCodes());
    }

    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function colorReplacementProvider(): iterable
    {
        yield 'standard replaces standard' => ["\x1b[31m", "\x1b[32m", "\x1b[32m"];
        yield '256-color replaces standard' => ["\x1b[31m", "\x1b[38;5;82m", "\x1b[38;5;82m"];
        yield 'RGB replaces 256-color' => ["\x1b[38;5;82m", "\x1b[38;2;255;128;0m", "\x1b[38;2;255;128;0m"];
    }

    #[DataProvider('colorReplacementProvider')]
    public function testColorReplacementOverwritesPreviousColor(string $first, string $second, string $expected)
    {
        $this->tracker->process($first);
        $this->tracker->process($second);

        $this->assertSame($expected, $this->tracker->getActiveCodes());
    }

    public function testProcessTextIgnoresNonSgrSequencesInText()
    {
        $this->tracker->processText("\x1b[2JHello\x1b[1mworld");
        // Only bold should be active, \x1b[2J is not SGR
        $this->assertSame("\x1b[1m", $this->tracker->getActiveCodes());
    }

    // --------------------------------------------------
    // Malformed extended color sequences
    // --------------------------------------------------

    /**
     * @return iterable<string, array{string}>
     */
    public static function malformedExtendedColorProvider(): iterable
    {
        yield '256-color fg missing color number (38;5)' => ["\x1b[38;5m"];
        yield '256-color bg missing color number (48;5)' => ["\x1b[48;5m"];
        yield 'RGB fg missing component (38;2;255;0)' => ["\x1b[38;2;255;0m"];
        yield 'RGB bg missing components (48;2;255)' => ["\x1b[48;2;255m"];
        yield 'RGB fg no components (38;2)' => ["\x1b[38;2m"];
        yield '38 alone' => ["\x1b[38m"];
        yield '48 alone' => ["\x1b[48m"];
    }

    #[DataProvider('malformedExtendedColorProvider')]
    public function testMalformedExtendedColorIsIgnored(string $sequence)
    {
        $this->tracker->process($sequence);
        $this->assertFalse($this->tracker->hasActiveCodes());
    }

    public function testMalformed256ColorDoesNotAffectSubsequentCodes()
    {
        // Bold should still be set from a previous valid code
        $this->tracker->process("\x1b[1m");
        $this->tracker->process("\x1b[38;5m"); // malformed; should be ignored
        $this->assertTrue($this->tracker->hasActiveCodes());
        $this->assertSame("\x1b[1m", $this->tracker->getActiveCodes());
    }
}
