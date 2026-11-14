<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\TuiHtml\Tests\Render;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Tui\Ansi\AnsiUtils;
use Symfony\Component\Tui\Exception\InvalidArgumentException;
use Symfony\Component\TuiHtml\Internal\TextMarkers;
use Symfony\Component\TuiHtml\Render\CellBuffer;

class CellBufferTest extends TestCase
{
    public static function invalidDimensionsProvider(): iterable
    {
        yield 'zero width' => [0, 5, 'CellBuffer dimensions must be at least 1x1, got 0x5'];
        yield 'zero height' => [10, 0, 'CellBuffer dimensions must be at least 1x1, got 10x0'];
        yield 'negative width' => [-1, 5, 'CellBuffer dimensions must be at least 1x1, got -1x5'];
        yield 'negative height' => [10, -3, 'CellBuffer dimensions must be at least 1x1, got 10x-3'];
        yield 'both zero' => [0, 0, 'CellBuffer dimensions must be at least 1x1, got 0x0'];
    }

    #[DataProvider('invalidDimensionsProvider')]
    public function testConstructorRejectsInvalidDimensions(int $width, int $height, string $expectedMessage)
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage($expectedMessage);
        new CellBuffer($width, $height);
    }

    public function testMinimalValidBuffer()
    {
        $buffer = new CellBuffer(1, 1);
        $this->assertSame(1, $buffer->getWidth());
        $this->assertSame(1, $buffer->getHeight());
        $lines = $buffer->toLines();
        $this->assertCount(1, $lines);
        $this->assertSame(' ', $lines[0]);
    }

    public function testEmptyBuffer()
    {
        $buf = new CellBuffer(10, 3);
        $lines = $buf->toLines();
        $this->assertCount(3, $lines);
        foreach ($lines as $line) {
            $this->assertSame('          ', $line);
            // 10 spaces
        }
    }

    public function testWritePlainText()
    {
        $buf = new CellBuffer(20, 3);
        $buf->writeAnsiLines(['Hello, World!']);
        $lines = $buf->toLines();
        $this->assertSame('Hello, World!       ', $lines[0]);
        $this->assertSame('                    ', $lines[1]);
    }

    public function testWriteMultipleLines()
    {
        $buf = new CellBuffer(20, 5);
        $buf->writeAnsiLines(['Line 1', 'Line 2', 'Line 3']);
        $lines = $buf->toLines();
        $this->assertStringStartsWith('Line 1', $lines[0]);
        $this->assertStringStartsWith('Line 2', $lines[1]);
        $this->assertStringStartsWith('Line 3', $lines[2]);
    }

    public function testWriteAtOffset()
    {
        $buf = new CellBuffer(20, 5);
        $buf->writeAnsiLines(['Offset'], 2, 5);
        $lines = $buf->toLines();
        // Row 0 and 1 should be all spaces
        $this->assertSame('                    ', $lines[0]);
        $this->assertSame('                    ', $lines[1]);
        // Row 2 should have 5 spaces then "Offset"
        $this->assertSame('     Offset         ', $lines[2]);
    }

    #[DataProvider('ansiStylePreservationProvider')]
    public function testAnsiStylePreserved(string $input, int $width, array $expectedSubstrings)
    {
        $buf = new CellBuffer($width, 1);
        $buf->writeAnsiLines([$input]);
        $lines = $buf->toLines();
        foreach ($expectedSubstrings as $expected) {
            $this->assertStringContainsString($expected, $lines[0]);
        }
    }

    public static function ansiStylePreservationProvider(): iterable
    {
        yield 'red foreground' => ["\x1b[31mRed\x1b[0m Normal", 20, ['31', 'Red', 'Normal']];
        yield 'bold attribute' => ["\x1b[1mBold\x1b[0m", 10, ["\x1b[", '1', 'Bold']];
        yield 'true color foreground' => ["\x1b[38;2;255;128;0mHi\x1b[0m", 10, ['38;2;255;128;0', 'Hi']];
        yield 'true color background' => ["\x1b[48;2;30;30;46mBg\x1b[0m", 10, ['48;2;30;30;46', 'Bg']];
        yield '256 color foreground' => ["\x1b[38;5;196mHi\x1b[0m", 10, ['38;5;196']];
    }

    public function testOverlayCompositing()
    {
        $buf = new CellBuffer(20, 5);
        // Write base content
        $buf->writeAnsiLines(['Background content  ', 'Line 2 of base      ', 'Line 3 of base      ', 'Line 4 of base      ']);
        // Write overlay on top at position (1, 5)
        $buf->writeAnsiLines(['OVERLAY', 'DATA   '], 1, 5);
        $lines = $buf->toLines();
        $plain = array_map(static fn ($l) => preg_replace('/\x1b\[[0-9;]*m/', '', $l), $lines);
        $this->assertSame('Background content  ', $plain[0]);
        $this->assertSame('Line OVERLAYse      ', $plain[1]);
        // Overlay overwrites cols 5-11
        $this->assertSame('Line DATA   se      ', $plain[2]);
        // "DATA   " overwrites cols 5-11
        $this->assertSame('Line 4 of base      ', $plain[3]);
    }

    public function testStyleOnlyChangesOnDifference()
    {
        $buf = new CellBuffer(10, 1);
        // All same color: should produce one SGR, not 5
        $buf->writeAnsiLines(["\x1b[31mAAAAA\x1b[0m"]);
        $lines = $buf->toLines();
        // Count SGR sequences: should be exactly 2 (set + reset at end)
        preg_match_all('/\x1b\[[0-9;]*m/', $lines[0], $matches);
        $this->assertCount(2, $matches[0]);
    }

    public function testCursorMarkerDetected()
    {
        $buf = new CellBuffer(20, 3);
        $buf->writeAnsiLines(["Hello\x1b_pi:c\x07 World"]);
        $cursor = $buf->getCursorPosition();
        $this->assertSame(0, $cursor['row']);
        $this->assertSame(5, $cursor['col']);
        // Cursor marker should NOT appear in toLines, position is handled separately
        $lines = $buf->toLines();
        $this->assertStringNotContainsString("\x1b_pi:c\x07", $lines[0]);
    }

    public function testCursorMarkerWithOffset()
    {
        $buf = new CellBuffer(20, 5);
        $buf->writeAnsiLines(["Test\x1b_pi:c\x07!"], 2, 3);
        $cursor = $buf->getCursorPosition();
        $this->assertSame(2, $cursor['row']);
        $this->assertSame(7, $cursor['col']);
        // 3 (startCol) + 4 ("Test")
    }

    public function testLineBeyondBufferIgnored()
    {
        $buf = new CellBuffer(10, 2);
        // Writing 5 lines into a 2-row buffer: only first 2 should be written
        $buf->writeAnsiLines(['Line 1', 'Line 2', 'Line 3', 'Line 4', 'Line 5']);
        $lines = $buf->toLines();
        $this->assertCount(2, $lines);
    }

    public function testTextTruncatedAtWidth()
    {
        $buf = new CellBuffer(5, 1);
        $buf->writeAnsiLines(['Hello World']);
        $lines = $buf->toLines();
        $plain = preg_replace('/\x1b\[[0-9;]*m/', '', $lines[0]);
        $this->assertSame('Hello', $plain);
    }

    public function testResetBetweenDifferentStyles()
    {
        $buf = new CellBuffer(10, 1);
        $buf->writeAnsiLines(["\x1b[31mR\x1b[32mG\x1b[0m"]);
        $lines = $buf->toLines();
        // Should have style change between R and G
        $this->assertStringContainsString('R', $lines[0]);
        $this->assertStringContainsString('G', $lines[0]);
        // Should have at least 3 SGR sequences (red, green, reset)
        preg_match_all('/\x1b\[[0-9;]*m/', $lines[0], $matches);
        $this->assertGreaterThanOrEqual(3, \count($matches[0]));
    }

    public function testTabExpansion()
    {
        $buf = new CellBuffer(20, 1);
        $buf->writeAnsiLines(["A\tB"]);
        $lines = $buf->toLines();
        $plain = preg_replace('/\x1b\[[0-9;]*m/', '', $lines[0]);
        // Tab should expand to 3 spaces
        $this->assertSame('A   B               ', $plain);
    }

    public function testMultipleStyleAttributes()
    {
        $buf = new CellBuffer(10, 1);
        // Bold + italic + red
        $buf->writeAnsiLines(["\x1b[1;3;31mHi\x1b[0m"]);
        $lines = $buf->toLines();
        // The output SGR should contain bold (1), italic (3), and red (31)
        $this->assertStringContainsString('1', $lines[0]);
        $this->assertStringContainsString('3', $lines[0]);
        $this->assertStringContainsString('31', $lines[0]);
    }

    public function testStyleResetsAtEachLine()
    {
        $buf = new CellBuffer(10, 2);
        // Set red on first line, don't reset; but each line starts fresh
        $buf->writeAnsiLines(["\x1b[31mLine1", 'Line2']);
        $lines = $buf->toLines();
        // Line 2 should NOT have red foreground: state resets per line
        $this->assertStringNotContainsString('31', $lines[1]);
    }

    public function testOverlayDoesNotAffectSurroundingCells()
    {
        $buf = new CellBuffer(10, 1);
        $buf->writeAnsiLines(['ABCDEFGHIJ']);
        $buf->writeAnsiLines(['XY'], 0, 3);
        $lines = $buf->toLines();
        $plain = preg_replace('/\x1b\[[0-9;]*m/', '', $lines[0]);
        $this->assertSame('ABCXYFGHIJ', $plain);
    }

    public function testTransparentSpacesWithAForegroundColorDrawNothing()
    {
        $buf = new CellBuffer(6, 1);
        $buf->writeAnsiLines(['ABCDEF']);
        $buf->writeAnsiLines(["\x1b[31m  X \x1b[39m\x1b[41m \x1b[49m"], 0, 0, true);
        $this->assertSame('ABXD F', preg_replace('/\x1b\[[0-9;]*m/', '', $buf->toLines()[0]), 'A space with a background covers what is below.');
    }

    public function testTintKeepsTheCharacters()
    {
        $buf = new CellBuffer(4, 1);
        $buf->writeAnsiLines(["\x1b[31mABCD\x1b[39m"]);
        $buf->tint(0, 1, 2, '48;2;1;2;3');
        $buf->tint(5, 0, 1, '41');
        $this->assertSame("\x1b[0;31mA\x1b[0;31;48;2;1;2;3mBC\x1b[0;31mD\x1b[0m", $buf->toLines()[0]);
    }

    public function testRoundtripPlainText()
    {
        // Write plain lines, read back; should match (with padding)
        $buf = new CellBuffer(10, 3);
        $buf->writeAnsiLines(['Hello', 'World', '!']);
        $lines = $buf->toLines();
        $plain = array_map(static fn ($l) => preg_replace('/\x1b\[[0-9;]*m/', '', $l), $lines);
        $this->assertSame('Hello     ', $plain[0]);
        $this->assertSame('World     ', $plain[1]);
        $this->assertSame('!         ', $plain[2]);
    }

    public function testOscHyperlinksAreKept()
    {
        $buf = new CellBuffer(12, 1);
        $buf->writeAnsiLines(["\x1b]8;;https://example.com\x07Link\x1b]8;;\x07 Text"]);
        $this->assertSame("\x1b]8;;https://example.com\x07Link\x1b]8;;\x07 Text   ", $buf->toLines()[0]);
        // Written over, transparent: the cells kept keep their link, those written over take the new one
        $buf->writeAnsiLines(["  \x1b]8;id=1;https://other.test\x07X\x1b]8;;\x07"], 0, 0, true);
        $this->assertSame("\x1b]8;;https://example.com\x07Li\x1b]8;id=1;https://other.test\x07X\x1b]8;;https://example.com\x07k\x1b]8;;\x07 Text   ", $buf->toLines()[0]);
    }

    public function testBrightForegroundColors()
    {
        $buf = new CellBuffer(10, 1);
        $buf->writeAnsiLines(["\x1b[91mHi\x1b[0m"]);
        $lines = $buf->toLines();
        $this->assertStringContainsString('91', $lines[0]);
    }

    public function testClearCursorPosition()
    {
        $buf = new CellBuffer(10, 1);
        $buf->writeAnsiLines(["A\x1b_pi:c\x07B"]);
        $cursor = $buf->getCursorPosition();
        $this->assertSame(0, $cursor['row']);
        $this->assertSame(1, $cursor['col']);
        $buf->clearCursorPosition();
        $this->assertNull($buf->getCursorPosition());
    }

    #[DataProvider('negativeStartColProvider')]
    public function testNegativeStartColIsClamped(string $input, int $startCol, string $expectedPlain, ?string $expectedSubstring = null)
    {
        $buf = new CellBuffer(10, 1);
        $buf->writeAnsiLines([$input], 0, $startCol);
        $lines = $buf->toLines();
        $plain = preg_replace('/\x1b\[[0-9;]*m/', '', $lines[0]);
        $this->assertSame($expectedPlain, $plain);
        if (null !== $expectedSubstring) {
            $this->assertStringContainsString($expectedSubstring, $lines[0]);
        }
    }

    public static function negativeStartColProvider(): iterable
    {
        yield 'plain text' => ['Hello', -3, 'Hello     '];
        yield 'with ANSI styles' => ["\x1b[31mRed\x1b[0m", -5, 'Red       ', '31'];
        yield 'with tab' => ["A\tB", -2, 'A   B     '];
    }

    public function testNegativeStartColDoesNotCorruptBuffer()
    {
        $buf = new CellBuffer(10, 2);
        $buf->writeAnsiLines(['ABCDEFGHIJ']);
        // fill row 0
        $buf->writeAnsiLines(['XY'], 0, -1);
        // negative startCol, should clamp to 0
        $lines = $buf->toLines();
        $plain = preg_replace('/\x1b\[[0-9;]*m/', '', $lines[0]);
        $this->assertSame('XYCDEFGHIJ', $plain);
        // Row 1 should be untouched
        $plain1 = preg_replace('/\x1b\[[0-9;]*m/', '', $lines[1]);
        $this->assertSame('          ', $plain1);
    }

    public function testUnicodeGraphemesWrittenCorrectly()
    {
        $buf = new CellBuffer(10, 1);
        $buf->writeAnsiLines(['café']);
        $lines = $buf->toLines();
        $plain = preg_replace('/\x1b\[[0-9;]*m/', '', $lines[0]);
        $this->assertSame('café      ', $plain);
    }

    public function testWideCharactersTakesTwoCells()
    {
        $buf = new CellBuffer(10, 1);
        $buf->writeAnsiLines(['A漢B']);
        $lines = $buf->toLines();
        $plain = preg_replace('/\x1b\[[0-9;]*m/', '', $lines[0]);
        // '漢' is 2 cells wide, so: A(1) + 漢(2) + B(1) = 4 cells, plus 6 spaces
        $this->assertSame('A漢B      ', $plain);
    }

    public function testUnicodeAtOffset()
    {
        $buf = new CellBuffer(10, 1);
        $buf->writeAnsiLines(['café'], 0, 3);
        $lines = $buf->toLines();
        $plain = preg_replace('/\x1b\[[0-9;]*m/', '', $lines[0]);
        $this->assertSame('   café   ', $plain);
    }

    public function testWideCharTruncatedAtBoundary()
    {
        // Buffer width 5, write a wide char that would start at col 4
        // (needs 2 cells but only 1 available), should be replaced with a space
        $buf = new CellBuffer(5, 1);
        $buf->writeAnsiLines(['ABCD漢']);
        $lines = $buf->toLines();
        $plain = preg_replace('/\x1b\[[0-9;]*m/', '', $lines[0]);
        $this->assertSame('ABCD ', $plain);
    }

    public function testACombiningMarkOnATransparentSpaceStillDrawsItsInk()
    {
        $buffer = new CellBuffer(3, 1);
        $buffer->writeAnsiLines(['abc']);
        $buffer->writeAnsiLines([' ́ '], 0, 0, true);
        $this->assertSame(' ́bc', $buffer->toLines()[0]);
    }

    public function testCombiningMarkStaysInTheCellOfItsBaseCharacter()
    {
        $buf = new CellBuffer(4, 1);
        $buf->writeAnsiLines(['éx']);
        $this->assertSame('éx  ', $buf->toLines()[0]);
    }

    public function testZeroWidthCharacterGoesInTheCellBeforeIt()
    {
        $buf = new CellBuffer(6, 1);
        $buf->writeAnsiLines(['......']);
        $buf->writeAnsiLines(['a⁧漢⁩b']);
        $this->assertSame('a⁧漢⁩b..', $buf->toLines()[0]);
        $buf->writeAnsiLines(['cd⁩']);
        $this->assertSame('cd⁩ b..', $buf->toLines()[0]);
    }

    public function testCombiningMarkSplitFromItsBaseCharacterStaysInTheCellOfIt()
    {
        $buf = new CellBuffer(4, 1);
        $buf->writeAnsiLines(["e\x1b[1ḿ\x1b[0mx"]);
        $this->assertSame('éx  ', $buf->toLines()[0]);
    }

    public function testZeroWidthCharacterAtTheStartOfARowIsDropped()
    {
        $buf = new CellBuffer(4, 1);
        $buf->writeAnsiLines(['⁨ab⁩']);
        $this->assertSame('ab⁩  ', $buf->toLines()[0]);
    }

    public function testZeroWidthCharacterAtTheStartOfALineWrittenOverOthersIsDropped()
    {
        // Like a slice of a line cut after a character, the zero-width one after it going with the slice
        $buf = new CellBuffer(6, 1);
        $buf->writeAnsiLines(["a\u{200b}b\u{200b}c"]);
        $buf->writeAnsiLines(["\u{200b}b\u{200b}c"], 0, 1, true);
        $this->assertSame("a\u{200b}b\u{200b}c   ", $buf->toLines()[0]);
    }

    public function testColorsGivenWithColonsAreReadLikeThoseWithSemicolons()
    {
        $buf = new CellBuffer(4, 1);
        $buf->writeAnsiLines(["\x1b[38:2::255:0:0ma\x1b[48:5:21mb\x1b[38:2:0:255:0:0:0:0mc\x1b[0md"]);
        $other = new CellBuffer(4, 1);
        $other->writeAnsiLines(["\x1b[38;2;255;0;0ma\x1b[48;5;21mb\x1b[38;2;255;0;0mc\x1b[0md"]);
        $this->assertSame($other->toLines()[0], $buf->toLines()[0]);
    }

    public function testZeroWidthCharacterAfterACellSkippedAsSeeThroughIsDropped()
    {
        $buf = new CellBuffer(10, 1);
        $buf->writeAnsiLines(['abcdefghij']);
        $buf->writeAnsiLines(["\t\u{200b}x"], 0, 0, true);
        $this->assertSame('abcxefghij', $buf->toLines()[0]);
    }

    public function testATabOfAForegroundAloneIsSeeThroughLikeASpace()
    {
        $buf = new CellBuffer(6, 1);
        $buf->writeAnsiLines(['abcdef']);
        $buf->writeAnsiLines(["\x1b[31m\tX"], 0, 0, true);
        $this->assertSame('abc', substr(preg_replace('/\e\[[0-9;]*m/', '', $buf->toLines()[0]), 0, 3));
    }

    public function testSubParametersOfAnSgrSequenceAreNotReadAsCodes()
    {
        $buf = new CellBuffer(6, 1);
        // A dotted underline, then a curly one in a color, then none
        $buf->writeAnsiLines(["\x1b[4:4mab\x1b[24m\x1b[4:3;58:2::255:0:0mc\x1b[4:0md\x1b[58;2;0;0;255mef"]);
        $this->assertSame("\x1b[0;4mabc\x1b[0mdef", $buf->toLines()[0], 'Not a blue background nor dim cells.');
    }

    public function testCursorMarkerAfterZeroWidthCharacters()
    {
        $buf = new CellBuffer(10, 1);
        $buf->writeAnsiLines(["⁨ab⁩\u{200b}".TextMarkers::cursor().'c']);
        $this->assertSame(['row' => 0, 'col' => 2], $buf->getCursorPosition());
    }

    public function testEmojiSequenceTakesTwoCells()
    {
        $family = '👨‍👩‍👧';
        $buf = new CellBuffer(5, 1);
        $buf->writeAnsiLines([$family.'xyz']);
        $this->assertSame($family.'xyz', $buf->toLines()[0]);
    }

    public function testWritingOverTheSecondCellOfAWideCharKeepsTheLineWidth()
    {
        $buf = new CellBuffer(4, 1);
        $buf->writeAnsiLines(['漢字']);
        $buf->writeAnsiLines(['x'], 0, 1);
        $this->assertSame(' x字', $buf->toLines()[0]);
    }

    public function testWritingOverTheFirstCellOfAWideCharKeepsTheLineWidth()
    {
        $buf = new CellBuffer(4, 1);
        $buf->writeAnsiLines(['漢字']);
        $buf->writeAnsiLines(['x'], 0, 0);
        $this->assertSame('x 字', $buf->toLines()[0]);
    }

    public function testTabTakesTheSameWidthAsInAnsiUtils()
    {
        $buf = new CellBuffer(10, 1);
        $buf->writeAnsiLines(["a\tb"]);
        $this->assertSame(AnsiUtils::visibleWidth("a\tb"), AnsiUtils::visibleWidth(rtrim($buf->toLines()[0])));
    }

    public function testWritingAWideCharAcrossTwoWideCharsKeepsTheLineWidth()
    {
        $buf = new CellBuffer(4, 1);
        $buf->writeAnsiLines(['漢字']);
        $buf->writeAnsiLines(['漢'], 0, 1);
        $this->assertSame(' 漢 ', $buf->toLines()[0]);
    }

    public function testTranspose()
    {
        $buf = new CellBuffer(6, 3);
        $buf->writeAnsiLines(["\x1b[31m縦書\x1b[0m", 'ab', 'c']);
        // Each row a column, from the right, each as wide as its widest character, its colors kept
        $turned = $buf->transpose()->toLines();
        $this->assertSame(['ca縦', ' b書'], array_map(AnsiUtils::stripAnsiCodes(...), $turned));
        $this->assertStringContainsString('31m縦', $turned[0]);
        $this->assertSame(['縦ac', '書b '], array_map(AnsiUtils::stripAnsiCodes(...), $buf->transpose(false)->toLines()), 'From the left.');
    }

    public function testJoinBoxRowJoinsTheLinesOfARowWithThoseUnderItAndDropsTheHiddenOnes()
    {
        $gray = "\x1b[38;2;170;170;170m";
        $white = "\x1b[38;2;255;255;255m";
        $buffer = new CellBuffer(8, 2);
        $buffer->writeAnsiLines([$gray.'│  │'."\x1b[0m", $gray.'────────'."\x1b[0m"]);
        // The bottom of a tab in the color of the page, over the line under the tabs
        $buffer->writeAnsiLines([$white.'└──┘'."\x1b[0m"], 1, 0, true);
        $buffer->joinBoxRow(1, array_fill(0, 8, '─'), '48;2;255;255;255');
        $this->assertSame('│  └────', AnsiUtils::stripAnsiCodes($buffer->toLines()[1]), 'Its corners in the color of its sides, its hidden bottom blank, the line joined to its right side.');
    }
}
