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
use Symfony\Component\Tui\Ansi\AnsiUtils;
use Symfony\Component\Tui\Ansi\TextWrapper;

class AnsiUtilsTest extends TestCase
{
    /**
     * @return iterable<string, array{string, int}>
     */
    public static function visibleWidthSimpleProvider(): iterable
    {
        yield 'simple word' => ['Hello', 5];
        yield 'two words' => ['Hello World', 11];
        yield 'empty string' => ['', 0];
    }

    #[DataProvider('visibleWidthSimpleProvider')]
    public function testVisibleWidthSimple(string $input, int $expected)
    {
        $this->assertSame($expected, AnsiUtils::visibleWidth($input));
    }

    public function testVisibleWidthWithAnsiCodes()
    {
        // Red "Hello" with reset
        $this->assertSame(5, AnsiUtils::visibleWidth("\x1b[31mHello\x1b[0m"));

        // Bold + colors
        $this->assertSame(5, AnsiUtils::visibleWidth("\x1b[1;31mHello\x1b[0m"));
    }

    public function testVisibleWidthWithEmoji()
    {
        // Most emojis are 2 columns wide
        $this->assertSame(2, AnsiUtils::visibleWidth('😀'));
        $this->assertSame(4, AnsiUtils::visibleWidth('😀😀'));
    }

    public function testVisibleWidthWithWideChars()
    {
        // CJK characters are 2 columns wide
        $this->assertSame(2, AnsiUtils::visibleWidth('日'));
        $this->assertSame(4, AnsiUtils::visibleWidth('日本'));
    }

    public function testVisibleWidthConsistencyBetweenSlowPathAndGraphemeWidth()
    {
        // Ensure visibleWidth (which uses mb_strwidth directly) and graphemeWidth
        // produce consistent results for single graphemes
        $graphemes = ['A', '日', '本', '😀'];

        foreach ($graphemes as $grapheme) {
            $this->assertSame(
                AnsiUtils::graphemeWidth($grapheme),
                AnsiUtils::visibleWidth($grapheme),
                \sprintf('Width mismatch for grapheme "%s"', $grapheme),
            );
        }
    }

    public function testStripAnsiCodes()
    {
        $this->assertSame('Hello', AnsiUtils::stripAnsiCodes("\x1b[31mHello\x1b[0m"));
        $this->assertSame('Hello', AnsiUtils::stripAnsiCodes('Hello'));
        $this->assertSame('', AnsiUtils::stripAnsiCodes("\x1b[0m"));
    }

    public function testStripAnsiCodesWithHyperlinks()
    {
        $hyperlink = "\x1b]8;;https://example.com\x07Click\x1b]8;;\x07";
        $this->assertSame('Click', AnsiUtils::stripAnsiCodes($hyperlink));
    }

    #[DataProvider('extractAnsiCodeProvider')]
    public function testExtractAnsiCode(string $input, string $expectedCode, int $expectedLength)
    {
        $result = AnsiUtils::extractAnsiCode($input, 0);

        $this->assertSame($expectedCode, $result['code']);
        $this->assertSame($expectedLength, $result['length']);
    }

    /**
     * @return iterable<string, array{string, string, int}>
     */
    public static function extractAnsiCodeProvider(): iterable
    {
        yield 'CSI SGR' => ["\x1b[31mHello", "\x1b[31m", 5];
        yield 'OSC with BEL' => ["\x1b]8;;url\x07text", "\x1b]8;;url\x07", 9];
        yield 'OSC with ST' => ["\x1b]8;;url\x1b\\text", "\x1b]8;;url\x1b\\", 10];
        yield 'APC with BEL' => ["\x1b_pi:c\x07rest", "\x1b_pi:c\x07", 7];
        yield 'APC with ST' => ["\x1b_Ga=T,f=100;AAAA\x1b\\rest", "\x1b_Ga=T,f=100;AAAA\x1b\\", 19];
        yield 'DCS with ST' => ["\x1bPq;data\x1b\\rest", "\x1bPq;data\x1b\\", 10];
        yield 'DCS with BEL' => ["\x1bPdata\x07rest", "\x1bPdata\x07", 7];
        yield 'PM with ST' => ["\x1b^message\x1b\\rest", "\x1b^message\x1b\\", 11];
        yield 'PM with BEL' => ["\x1b^message\x07rest", "\x1b^message\x07", 10];
        yield 'SOS with ST' => ["\x1bXstring\x1b\\rest", "\x1bXstring\x1b\\", 10];
        yield 'Cursor Up' => ["\x1b[5A", "\x1b[5A", 4];
        yield 'Cursor Down' => ["\x1b[3B", "\x1b[3B", 4];
        yield 'Cursor Forward' => ["\x1b[2C", "\x1b[2C", 4];
        yield 'Cursor Back' => ["\x1b[1D", "\x1b[1D", 4];
        yield 'Fe IND' => ["\x1bDrest", "\x1bD", 2];
        yield 'Fe RI' => ["\x1bMrest", "\x1bM", 2];
        yield 'Fe NEL' => ["\x1bErest", "\x1bE", 2];
        yield 'Fe HTS' => ["\x1bHrest", "\x1bH", 2];
        yield 'Fe SS2' => ["\x1bNrest", "\x1bN", 2];
        yield 'Fe SS3' => ["\x1bOrest", "\x1bO", 2];
        yield 'Fp DECSC' => ["\x1b7rest", "\x1b7", 2];
        yield 'Fp DECRC' => ["\x1b8rest", "\x1b8", 2];
        yield 'Fs RIS' => ["\x1bcrest", "\x1bc", 2];
        yield 'nF G0 US ASCII' => ["\x1b(Brest", "\x1b(B", 3];
        yield 'nF G0 DEC Graphics' => ["\x1b(0rest", "\x1b(0", 3];
        yield 'nF G1 charset' => ["\x1b)Brest", "\x1b)B", 3];
        yield 'nF 7-bit C1 mode' => ["\x1b Frest", "\x1b F", 3];
    }

    #[DataProvider('extractAnsiCodeReturnsNullProvider')]
    public function testExtractAnsiCodeReturnsNull(string $input)
    {
        $this->assertNull(AnsiUtils::extractAnsiCode($input, 0));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function extractAnsiCodeReturnsNullProvider(): iterable
    {
        yield 'non-escape' => ['Hello'];
        yield 'OSC unterminated' => ["\x1b]8;;url"];
        yield 'APC unterminated' => ["\x1b_data"];
        yield 'DCS unterminated' => ["\x1bPdata"];
        yield 'nF unterminated' => ["\x1b("];
        yield 'lone ESC' => ["\x1b"];
        yield 'ESC + control' => ["\x1b\x01"];
    }

    public function testTruncateToWidthNoTruncation()
    {
        $this->assertSame('Hello', AnsiUtils::truncateToWidth('Hello', 10));
        $this->assertSame('Hello', AnsiUtils::truncateToWidth('Hello', 5));
    }

    public function testTruncateToWidthWithTruncation()
    {
        $result = AnsiUtils::truncateToWidth('Hello World', 8);
        $this->assertSame(8, AnsiUtils::visibleWidth($result));
        $this->assertStringEndsWith('...', $result);
    }

    public function testTruncateToWidthPreservesAnsi()
    {
        $styled = "\x1b[31mHello World\x1b[0m";
        $result = AnsiUtils::truncateToWidth($styled, 8);

        // Should contain the red escape code
        $this->assertStringContainsString("\x1b[31m", $result);
        // And be truncated
        $this->assertSame(8, AnsiUtils::visibleWidth($result));
    }

    public function testTruncateToWidthWithPadding()
    {
        $result = AnsiUtils::truncateToWidth('Hi', 10, '...', true);
        $this->assertSame(10, AnsiUtils::visibleWidth($result));
    }

    #[DataProvider('unicodeEllipsisProvider')]
    public function testTruncateToWidthDoesNotSplitUnicodeEllipsis(string $ellipsis, int $maxWidth, string $expectedText, int $expectedWidth)
    {
        $result = AnsiUtils::truncateToWidth('Hello', $maxWidth, $ellipsis);

        $this->assertTrue(mb_check_encoding($result, 'UTF-8'));
        $this->assertSame($expectedWidth, AnsiUtils::visibleWidth($result));
        $this->assertSame($expectedText, AnsiUtils::stripAnsiCodes($result));
    }

    /**
     * @return iterable<string, array{string, int, string, int}>
     */
    public static function unicodeEllipsisProvider(): iterable
    {
        yield 'unicode ellipsis' => ['…', 1, '…', 1];
        yield 'emoji ellipsis' => ['😀', 2, '😀', 2];
        yield 'emoji ellipsis too wide' => ['😀', 1, '', 0];
    }

    public function testSliceByColumn()
    {
        $this->assertSame('llo', AnsiUtils::sliceByColumn('Hello', 2, 3));
        $this->assertSame('He', AnsiUtils::sliceByColumn('Hello', 0, 2));
    }

    public function testSliceByColumnWithAnsi()
    {
        $styled = "\x1b[31mHello\x1b[0m";
        $result = AnsiUtils::sliceByColumn($styled, 0, 3);

        // Should contain "Hel" with ANSI codes
        $this->assertSame(3, AnsiUtils::visibleWidth($result));
    }

    public function testSliceByColumnPreservesCodeOrderWhenAColorChangeLandsOnStartColumn()
    {
        // "AA" red, "BB" blue: slicing from column 1 crosses into the pending-before-start red
        // code (column 0) and the in-range blue code (starts exactly at column 1). The pending
        // code must stay ordered before the in-range one, or the wrong color ends up active.
        $line = "\x1b[31mA\x1b[34mABB\x1b[0m";

        $result = AnsiUtils::sliceByColumn($line, 1, 1);

        $redPos = strpos($result, "\x1b[31m");
        $bluePos = strpos($result, "\x1b[34m");

        $this->assertNotFalse($redPos);
        $this->assertNotFalse($bluePos);
        $this->assertLessThan($bluePos, $redPos, 'The pending (earlier) color code must appear before the in-range (later) one, so blue is the last -- and therefore active -- code.');
        $this->assertSame('A', AnsiUtils::stripAnsiCodes($result));
    }

    /**
     * @return iterable<string, array{string, bool}>
     */
    public static function isWhitespaceProvider(): iterable
    {
        yield 'space' => [' ', true];
        yield 'tab' => ["\t", true];
        yield 'newline' => ["\n", true];
        yield 'letter' => ['a', false];
    }

    #[DataProvider('isWhitespaceProvider')]
    public function testIsWhitespace(string $char, bool $expected)
    {
        $this->assertSame($expected, AnsiUtils::isWhitespace($char));
    }

    /**
     * @return iterable<string, array{string, bool}>
     */
    public static function isPunctuationProvider(): iterable
    {
        yield 'period' => ['.', true];
        yield 'comma' => [',', true];
        yield 'exclamation' => ['!', true];
        yield 'letter' => ['a', false];
        yield 'space' => [' ', false];
    }

    #[DataProvider('isPunctuationProvider')]
    public function testIsPunctuation(string $char, bool $expected)
    {
        $this->assertSame($expected, AnsiUtils::isPunctuation($char));
    }

    /**
     * @return iterable<string, array{string, int}>
     */
    public static function visibleWidthWithCursorMovementProvider(): iterable
    {
        yield 'cursor up' => ["\x1b[5A", 0];
        yield 'cursor down' => ["\x1b[3B", 0];
        yield 'cursor forward' => ["\x1b[2C", 0];
        yield 'cursor back' => ["\x1b[1D", 0];
        yield 'cursor up + text' => ["\x1b[5AHello", 5];
        yield 'text + cursor down' => ["Hello\x1b[3B", 5];
    }

    #[DataProvider('visibleWidthWithCursorMovementProvider')]
    public function testVisibleWidthWithCursorMovement(string $input, int $expected)
    {
        $this->assertSame($expected, AnsiUtils::visibleWidth($input));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function stripAnsiCodesWithCursorMovementProvider(): iterable
    {
        yield 'cursor up only' => ["\x1b[5A", ''];
        yield 'cursor up + text' => ["\x1b[5AHello", 'Hello'];
        yield 'text + cursor down' => ["Hello\x1b[3B", 'Hello'];
    }

    #[DataProvider('stripAnsiCodesWithCursorMovementProvider')]
    public function testStripAnsiCodesWithCursorMovement(string $input, string $expected)
    {
        $this->assertSame($expected, AnsiUtils::stripAnsiCodes($input));
    }

    public function testVisibleWidthWithKittyImageAndCursorUp()
    {
        // Simulates Kitty converter output: cursor-up + Kitty graphics protocol
        $moveUp = "\x1b[5A";
        $kittyPayload = "\x1b_Ga=T,f=100,q=2,c=80,r=6,i=12345,m=0;AAAA\x1b\\";
        $this->assertSame(0, AnsiUtils::visibleWidth($moveUp.$kittyPayload));
    }

    public function testContainsImage()
    {
        $this->assertFalse(AnsiUtils::containsImage('Hello'));
        $this->assertTrue(AnsiUtils::containsImage("\x1b_Gdata\x1b\\"));
        $this->assertTrue(AnsiUtils::containsImage("\x1b]1337;File=inline=1:data\x07"));
    }

    public function testStripAnsiCodesWithMixedSequenceTypes()
    {
        // SGR + OSC hyperlink + APC cursor marker all in one string
        $str = "\x1b[1;31m\x1b]8;;https://example.com\x07Click\x1b]8;;\x07\x1b[0m\x1b_pi:c\x07";
        $this->assertSame('Click', AnsiUtils::stripAnsiCodes($str));
    }

    public function testStripAnsiCodesWithOnlyEscapeSequences()
    {
        // String with only ANSI sequences, no visible text
        $str = "\x1b[31m\x1b]8;;url\x07\x1b]8;;\x07\x1b[0m\x1b_marker\x07";
        $this->assertSame('', AnsiUtils::stripAnsiCodes($str));
    }

    public function testVisibleWidthWithMixedAnsiAndUnicode()
    {
        // CJK text with SGR, hyperlink, and APC sequences: forces slow path
        $str = "\x1b[1;31m\x1b]8;;https://example.com\x07日本\x1b]8;;\x07\x1b[0m\x1b_pi:c\x07";
        $this->assertSame(4, AnsiUtils::visibleWidth($str));
    }

    public function testVisibleWidthWithOnlyAnsiSequences()
    {
        // Only escape sequences, no visible content: slow path returns 0
        $str = "\x1b[31m\x1b]8;;url\x07\x1b]8;;\x07\x1b[0m\x1b_data\x07";
        $this->assertSame(0, AnsiUtils::visibleWidth($str));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function stripAnsiCodesSequenceTypeProvider(): iterable
    {
        yield 'DCS' => ["Hello\x1bPq;sixeldata\x1b\\World", 'HelloWorld'];
        yield 'PM' => ["Hello\x1b^private\x1b\\World", 'HelloWorld'];
        yield 'SOS' => ["Hello\x1bXstring\x1b\\World", 'HelloWorld'];
        yield 'Fe IND+RI' => ["\x1bDHello\x1bM", 'Hello'];
        yield 'Fe RIS' => ["\x1bcHello", 'Hello'];
        yield 'Fp DECSC+DECRC' => ["\x1b7Hello\x1b8", 'Hello'];
        yield 'nF G0 charset' => ["\x1b(0Hello\x1b(B", 'Hello'];
        yield 'all ECMA-48 types' => [
            "\x1b[31m\x1b]8;;url\x07\x1b_pi:c\x07\x1bPdata\x1b\\\x1b^private\x1b\\\x1bXstring\x1b\\\x1bD\x1b7\x1b(BHello\x1b[0m",
            'Hello',
        ];
    }

    #[DataProvider('stripAnsiCodesSequenceTypeProvider')]
    public function testStripAnsiCodesWithSequenceTypes(string $input, string $expected)
    {
        $this->assertSame($expected, AnsiUtils::stripAnsiCodes($input));
    }

    /**
     * @return iterable<string, array{string, int}>
     */
    public static function visibleWidthSequenceTypeProvider(): iterable
    {
        yield 'DCS' => ["\x1bPq;sixeldata\x1b\\Hello", 5];
        yield 'Fe IND only' => ["\x1bD", 0];
        yield 'Fe RI only' => ["\x1bM", 0];
        yield 'Fe IND+RI wrapping text' => ["\x1bDHello\x1bM", 5];
        yield 'Fp DECSC+DECRC' => ["\x1b7Hello\x1b8", 5];
        yield 'nF G0 charset' => ["\x1b(0Hello\x1b(B", 5];
        yield 'all ECMA-48 types' => [
            "\x1b[31m\x1b]8;;url\x07\x1b_pi:c\x07\x1bPdata\x1b\\\x1b^pm\x1b\\\x1bXsos\x1b\\\x1bD\x1b7\x1b(BHello\x1b[0m",
            5,
        ];
    }

    #[DataProvider('visibleWidthSequenceTypeProvider')]
    public function testVisibleWidthWithSequenceTypes(string $input, int $expected)
    {
        $this->assertSame($expected, AnsiUtils::visibleWidth($input));
    }

    public function testSliceByColumnWithDcsAndFeTwoByteSequences()
    {
        // DCS + Fe sequences should be skipped as zero-width in sliceByColumn
        $str = "\x1b7\x1bPdata\x1b\\Hello\x1bM";
        $result = AnsiUtils::sliceByColumn($str, 0, 3);
        $this->assertSame(3, AnsiUtils::visibleWidth($result));
    }

    /**
     * Data provider for accented character width tests.
     *
     * Regression test for: ctype_print() bug with UTF-8 characters.
     * ctype_print() accepts non-ASCII UTF-8 characters (e.g., 'é'),
     * causing the fast path to incorrectly return strlen() instead of mb_strwidth().
     *
     * @return iterable<string, array{string, int}>
     */
    public static function accentedCharacterWidthProvider(): iterable
    {
        // Text with accented characters where strlen != mb_strwidth
        // This triggered the ctype_print() bug which caused footer flickering
        yield 'Gérard (7 bytes, 6 columns)' => ['Gérard', 6];
        yield 'Café (5 bytes, 4 columns)' => ['Café', 4];
        yield 'Naïveté (8 bytes, 7 columns)' => ['Naïveté', 7];
        yield 'Müller (7 bytes, 6 columns)' => ['Müller', 6];
    }

    #[DataProvider('accentedCharacterWidthProvider')]
    public function testVisibleWidthWithAccentedCharacters(string $text, int $expectedWidth)
    {
        $this->assertSame($expectedWidth, AnsiUtils::visibleWidth($text));
    }

    /**
     * Regression test: width calculation must be consistent for accented text.
     *
     * Multiple calls to visibleWidth() must return the same value.
     * This tests the caching mechanism with accented characters.
     */
    public function testVisibleWidthConsistencyWithAccentedCharacters()
    {
        $text = 'Gérard';
        $widths = [];

        for ($i = 0; $i < 10; ++$i) {
            $widths[] = AnsiUtils::visibleWidth($text);
        }

        $uniqueWidths = array_unique($widths);
        $this->assertCount(1, $uniqueWidths, 'visibleWidth() should return consistent results');
        $this->assertSame(6, $widths[0]);
    }

    /**
     * Test footer scenario: path with accent + agent name.
     *
     * Simulates the footer rendering in Gérard agent:
     * "/path/to/project • Gérard"
     */
    public function testVisibleWidthFooterScenario()
    {
        // Typical footer right-side text with accented agent name
        $rightText = '/Users/fabien/Code/test • Gérard';
        $expectedWidth = 32; // Correct mb_strwidth result

        $this->assertSame($expectedWidth, AnsiUtils::visibleWidth($rightText));

        // With branch name (also common in footer)
        $rightWithBranch = '/Users/fabien/Code/test (main) • Gérard';
        $this->assertSame(39, AnsiUtils::visibleWidth($rightWithBranch));
    }

    /**
     * Test footer with ANSI styling.
     *
     * The footer applies styling (colors) to the text.
     * Width must be the same whether styled or plain.
     */
    public function testVisibleWidthFooterScenarioWithAnsiStyling()
    {
        $pwd = '/Users/fabien/Code/test';
        $agentName = 'Gérard';
        $rightPlain = $pwd.' • '.$agentName;

        // Simulate footer styling: muted for pwd, colored for agent name
        $muted = "\x1b[90m";
        $agentColor = "\x1b[38;5;33m";
        $reset = "\x1b[0m";
        $rightStyled = $muted.$pwd.' • '.$reset.$agentColor.$agentName.$reset;

        $plainWidth = AnsiUtils::visibleWidth($rightPlain);
        $styledWidth = AnsiUtils::visibleWidth($rightStyled);

        $this->assertSame($plainWidth, $styledWidth, 'Styled and plain text should have same visible width');
        $this->assertSame(32, $plainWidth);
    }

    /**
     * Data provider for truncation tests with accented characters.
     *
     * @return iterable<string, array{string, int, string, string}>
     */
    public static function truncateAccentedCharacterProvider(): iterable
    {
        yield 'simple accented text' => ['Hello Gérard, welcome!', 15, '...', '...'];
        yield 'accented agent name' => ['Gérard is testing truncation', 10, '…', '…'];
        yield 'long path with accent' => ['/Users/fabien/Code/Gérard/project', 25, '...', '...'];
    }

    /**
     * @param non-empty-string $expectedSuffix
     */
    #[DataProvider('truncateAccentedCharacterProvider')]
    public function testTruncateToWidthWithAccentedCharacters(string $text, int $maxWidth, string $ellipsis, string $expectedSuffix)
    {
        $result = AnsiUtils::truncateToWidth($text, $maxWidth, $ellipsis);
        $resultWidth = AnsiUtils::visibleWidth($result);

        // Result should fit within max width
        $this->assertLessThanOrEqual($maxWidth, $resultWidth);
        // Should end with the specified ellipsis
        $this->assertStringEndsWith($expectedSuffix, $result);
    }

    /**
     * Data provider for slicing tests with accented characters.
     *
     * @return iterable<string, array{string, int, int, int}>
     */
    public static function sliceAccentedCharacterProvider(): iterable
    {
        // text, startCol, length, expectedWidth
        yield 'Gérard slice first 3 cols' => ['Gérard', 0, 3, 3];
        yield 'Gérard slice from col 2' => ['Gérard', 2, 4, 4];
        yield 'Café full' => ['Café', 0, 10, 4];
    }

    #[DataProvider('sliceAccentedCharacterProvider')]
    public function testSliceByColumnWithAccentedCharacters(string $text, int $startCol, int $length, int $expectedWidth)
    {
        $result = AnsiUtils::sliceByColumn($text, $startCol, $length);
        $this->assertSame($expectedWidth, AnsiUtils::visibleWidth($result));
    }

    /**
     * Test that mixed ASCII and accented text is handled correctly.
     */
    public function testVisibleWidthMixedAsciiAndAccented()
    {
        $text = 'Hello Gérard from Café';
        // "Hello " (6) + "Gérard" (6) + " from " (6) + "Café" (4) = 22
        $this->assertSame(22, AnsiUtils::visibleWidth($text));
    }

    public function testVisibleWidthCountsACombiningMarkWithItsBaseCharacter()
    {
        $this->assertSame(1, AnsiUtils::visibleWidth("e\u{0301}"));
        $this->assertSame(4, AnsiUtils::visibleWidth("cafe\u{0301}"));
        $this->assertSame(2, AnsiUtils::visibleWidth("a\u{0301}\u{0308}b"));
        $this->assertSame(4, AnsiUtils::visibleWidth("cafe\u{0301}\x1b[0m"));
    }

    public function testSliceByColumnGivesTabsTheSameWidthAsVisibleWidth()
    {
        $this->assertSame('a', AnsiUtils::sliceByColumn("a\tb", 0, 2));
        $this->assertSame("a\t", AnsiUtils::sliceByColumn("a\tb", 0, 4));
        $this->assertSame("a\tb", AnsiUtils::sliceByColumn("a\tb", 0, 5));
    }

    public function testTruncateToWidthNeverExceedsTheWidthWithTabs()
    {
        $this->assertSame(3, AnsiUtils::visibleWidth(AnsiUtils::truncateToWidth("\t\t\t", 3, '')));
        $this->assertSame(4, AnsiUtils::visibleWidth(AnsiUtils::truncateToWidth("a\tb\tc", 4, '')));
        // A tab is never split, so the result stops below the limit here.
        $this->assertSame(4, AnsiUtils::visibleWidth(AnsiUtils::truncateToWidth("\tx\t\t", 6, '')));
    }

    public function testSliceByColumnStopsAtAWideCharacterInsteadOfTakingLaterColumns()
    {
        // "東" occupies columns 1-2, "京" columns 3-4, the space column 5.
        // Neither wide character can be split, so the slice stops before it
        // instead of skipping it and pulling in a character further right.
        $line = "\x1b[31m東京\x1b[0m OK";

        $this->assertSame("\x1b[31m", AnsiUtils::sliceByColumn($line, 0, 1));
        $this->assertSame("\x1b[31m東", AnsiUtils::sliceByColumn($line, 0, 2));
        $this->assertSame("\x1b[31m東", AnsiUtils::sliceByColumn($line, 0, 3));
        $this->assertSame("\x1b[31m東京", AnsiUtils::sliceByColumn($line, 0, 4));
        $this->assertSame("\x1b[31m東京\x1b[0m ", AnsiUtils::sliceByColumn($line, 0, 5));
        $this->assertSame("\x1b[31m東京\x1b[0m O", AnsiUtils::sliceByColumn($line, 0, 6));
    }

    public function testTruncateToWidthDoesNotPullInCharactersPastAWideCharacter()
    {
        // Truncating "東京 OK" to 4 columns keeps "東" and drops the rest;
        // the space at column 5 must not surface where "京" was dropped.
        $this->assertSame("\x1b[31m東\x1b[0m…", AnsiUtils::truncateToWidth("\x1b[31m東京\x1b[0m OK", 4, '…'));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function walkCellsLosslessProvider(): iterable
    {
        yield 'plain ascii' => ['hello world'];
        yield 'empty string' => [''];
        yield 'csi colors' => ["\x1b[1;31mred\x1b[0m plain"];
        yield 'wide characters' => ['日本語テキスト'];
        yield 'mixed widths' => ["a\x1b[42m日b本\x1b[49mc"];
        yield 'combining mark' => ["e\u{0301}cole"];
        yield 'osc 8 hyperlink' => ["\x1b]8;;https://symfony.com\x07link\x1b]8;;\x07 after"];
        yield 'apc cursor marker' => [AnsiUtils::cursorMarker().'ab'];
        yield 'tab' => ["a\tb"];
        yield 'emoji' => ['😀x'];
        yield 'trailing escape' => ["ab\x1b[0m"];
        yield 'lone trailing esc byte' => ["ab\x1b"];
        yield 'charset designation' => ["\x1b(Bab"];
    }

    #[DataProvider('walkCellsLosslessProvider')]
    public function testWalkCellsRebuildsTheLine(string $line)
    {
        $rebuilt = '';
        foreach (AnsiUtils::walkCells($line) as $token) {
            $rebuilt .= $token['text'];
        }

        $this->assertSame($line, $rebuilt);
    }

    public function testWalkCellsReportsColumnsAndWidths()
    {
        $tokens = iterator_to_array(AnsiUtils::walkCells('a日b'), false);

        $this->assertSame([
            ['text' => 'a', 'col' => 0, 'width' => 1, 'bg' => false],
            ['text' => '日', 'col' => 1, 'width' => 2, 'bg' => false],
            ['text' => 'b', 'col' => 3, 'width' => 1, 'bg' => false],
        ], $tokens);
    }

    public function testWalkCellsKeepsACombiningMarkWithItsBase()
    {
        $tokens = iterator_to_array(AnsiUtils::walkCells("e\u{0301}c"), false);

        $this->assertSame([
            ['text' => "e\u{0301}", 'col' => 0, 'width' => 1, 'bg' => false],
            ['text' => 'c', 'col' => 1, 'width' => 1, 'bg' => false],
        ], $tokens);
    }

    public function testWalkCellsYieldsEscapeSequencesAsZeroWidthTokens()
    {
        $line = "\x1b]8;;https://symfony.com\x07li\x1b]8;;\x07".AnsiUtils::cursorMarker().'nk';
        $cells = [];
        $escapes = 0;
        foreach (AnsiUtils::walkCells($line) as $token) {
            if (0 === $token['width']) {
                ++$escapes;
                continue;
            }
            $cells[] = [$token['text'], $token['col']];
        }

        $this->assertSame(3, $escapes);
        $this->assertSame([['l', 0], ['i', 1], ['n', 2], ['k', 3]], $cells);
    }

    public function testWalkCellsCountsATabAsTabWidthColumns()
    {
        $tokens = iterator_to_array(AnsiUtils::walkCells("a\tb"), false);

        $this->assertSame([
            ['text' => 'a', 'col' => 0, 'width' => 1, 'bg' => false],
            ['text' => "\t", 'col' => 1, 'width' => AnsiUtils::TAB_WIDTH, 'bg' => false],
            ['text' => 'b', 'col' => 1 + AnsiUtils::TAB_WIDTH, 'width' => 1, 'bg' => false],
        ], $tokens);
    }

    public function testWalkCellsTracksTheActiveBackground()
    {
        $line = "a\x1b[41mb\x1b[49mc\x1b[48;2;10;20;30md\x1b[0me\x1b[38;2;0;0;0mf\x1b[107mg\x1b[mh";
        $bgByCell = [];
        foreach (AnsiUtils::walkCells($line) as $token) {
            if (0 !== $token['width']) {
                $bgByCell[$token['text']] = $token['bg'];
            }
        }

        $this->assertSame([
            'a' => false, // nothing set yet
            'b' => true,  // standard bg color
            'c' => false, // SGR 49 resets the background
            'd' => true,  // truecolor bg
            'e' => false, // SGR 0 full reset
            'f' => false, // truecolor fg: the 0 channels are payload, not SGR 0
            'g' => true,  // bright bg color
            'h' => false, // bare ESC[m is SGR 0
        ], $bgByCell);
    }

    public function testWalkCellsLeavesTheBackgroundUntouchedForNonSgrSequences()
    {
        // ESC[2K and ESC[0K are erase sequences, not SGR: their parameters
        // must not be read as color codes even though 0 would mean a reset
        $line = "\x1b[41ma\x1b[2Kb\x1b[0Kc\x1b]8;;x\x07d";
        $bgByCell = [];
        foreach (AnsiUtils::walkCells($line) as $token) {
            if (0 !== $token['width']) {
                $bgByCell[$token['text']] = $token['bg'];
            }
        }

        $this->assertSame(['a' => true, 'b' => true, 'c' => true, 'd' => true], $bgByCell);
    }

    /**
     * @return iterable<string, array{string, int, int, string}>
     */
    public static function sliceToWidthProvider(): iterable
    {
        yield 'ascii inner range' => ['abcdef', 1, 3, 'bcd'];
        yield 'ascii full line' => ['abc', 0, 3, 'abc'];
        yield 'short line is padded' => ['ab', 0, 5, 'ab   '];
        yield 'range past the end is spaces' => ['ab', 4, 3, '   '];
        yield 'zero length' => ['abc', 0, 0, ''];
        yield 'wide char fully inside' => ['a日b', 1, 2, '日'];
        yield 'wide char cut at the right edge' => ['a日b', 0, 2, 'a '];
        yield 'wide char cut at the left edge' => ['a日b', 2, 2, ' b'];
        yield 'wide char cut on both sides' => ['日本語', 1, 2, '  '];
        yield 'ansi codes before the range still apply' => ["\x1b[31mabc\x1b[0mdef", 1, 2, "\x1b[31mbc"];
        yield 'ansi codes inside the range are kept' => ["ab\x1b[31mcd", 1, 2, "b\x1b[31mc"];
        yield 'tab cut at the right edge' => ["\tx", 0, 2, '  '];
    }

    #[DataProvider('sliceToWidthProvider')]
    public function testSliceToWidth(string $line, int $startCol, int $length, string $expected)
    {
        $this->assertSame($expected, AnsiUtils::sliceToWidth($line, $startCol, $length));
    }

    public function testSliceToWidthAlwaysReturnsTheRequestedWidth()
    {
        $lines = [
            '日本語テキスト',
            'a日b本c語d',
            "\x1b[31m日本\x1b[42m語テ\x1b[0mキ",
            '😀😀😀',
            "e\u{0301}日e\u{0301}本",
            "ab\tcd日",
        ];

        foreach ($lines as $line) {
            $lineWidth = AnsiUtils::visibleWidth($line);
            for ($startCol = 0; $startCol <= $lineWidth + 2; ++$startCol) {
                for ($length = 1; $length <= 6; ++$length) {
                    $slice = AnsiUtils::sliceToWidth($line, $startCol, $length);
                    $this->assertSame($length, AnsiUtils::visibleWidth($slice), \sprintf('sliceToWidth(%s, %d, %d) returned %s', json_encode($line), $startCol, $length, json_encode($slice)));
                }
            }
        }
    }

    public function testSlicesToWidthComposeIntoTheFullLine()
    {
        // Cutting a line into adjacent fixed-width slices must cover every
        // column exactly once: this is what a transition or a split-screen
        // widget relies on to keep neighbouring regions aligned.
        $line = 'a日b本c';
        foreach ([1, 2, 3, 4] as $split) {
            $left = AnsiUtils::sliceToWidth($line, 0, $split);
            $right = AnsiUtils::sliceToWidth($line, $split, 7 - $split);
            $this->assertSame(7, AnsiUtils::visibleWidth($left.$right), \sprintf('split at %d', $split));
        }
    }

    /**
     * The wrapper breaks lines by graphemeWidth() and every consumer measures
     * the result with visibleWidth(); if the two disagree, a chunk comes back
     * wider than the width it was wrapped to.
     */
    public function testVisibleWidthOfAJoinedSequenceMatchesItsGraphemeWidth()
    {
        $family = "\u{1F468}\u{200D}\u{1F469}\u{200D}\u{1F467}\u{200D}\u{1F466}";

        $this->assertSame(AnsiUtils::graphemeWidth($family), AnsiUtils::visibleWidth($family));
    }

    public function testVisibleWidthIsAdditiveOverGraphemes()
    {
        $line = "a\u{1F44B}b\u{1F468}\u{200D}\u{1F469}\u{200D}\u{1F467}\u{200D}\u{1F466}c";

        $sum = 0;
        foreach (grapheme_str_split($line) as $grapheme) {
            $sum += AnsiUtils::visibleWidth($grapheme);
        }

        $this->assertSame($sum, AnsiUtils::visibleWidth($line));
    }

    public function testWrappedChunksNeverExceedTheRequestedWidth()
    {
        $line = "a\u{1F44B}b\u{1F468}\u{200D}\u{1F469}\u{200D}\u{1F467}\u{200D}\u{1F466}c";

        foreach (TextWrapper::wrapTextWithAnsi($line, 8) as $chunk) {
            $this->assertLessThanOrEqual(8, AnsiUtils::visibleWidth($chunk));
        }
    }
}
