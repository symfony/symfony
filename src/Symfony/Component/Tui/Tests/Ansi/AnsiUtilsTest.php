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
}
