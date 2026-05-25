<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Tui\Tests\Render;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Tui\Exception\InvalidArgumentException;
use Symfony\Component\Tui\Render\CellBuffer;

class CellBufferTest extends TestCase
{
    /**
     * @return iterable<string, array{int, int, string}>
     */
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
            $this->assertSame('          ', $line); // 10 spaces
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

    /**
     * @param list<string> $expectedSubstrings
     */
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

    /**
     * @return iterable<string, array{string, int, list<string>}>
     */
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
        $buf->writeAnsiLines([
            'Background content  ',
            'Line 2 of base      ',
            'Line 3 of base      ',
            'Line 4 of base      ',
        ]);

        // Write overlay on top at position (1, 5)
        $buf->writeAnsiLines([
            'OVERLAY',
            'DATA   ',
        ], 1, 5);

        $lines = $buf->toLines();
        $plain = array_map(static fn ($l) => preg_replace('/\x1b\[[0-9;]*m/', '', $l), $lines);

        $this->assertSame('Background content  ', $plain[0]);
        $this->assertSame('Line OVERLAYse      ', $plain[1]); // Overlay overwrites cols 5-11
        $this->assertSame('Line DATA   se      ', $plain[2]); // "DATA   " overwrites cols 5-11
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
        $this->assertSame(7, $cursor['col']); // 3 (startCol) + 4 ("Test")
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

    public function testOscHyperlinkStripped()
    {
        $buf = new CellBuffer(20, 1);
        // OSC 8 hyperlink should be consumed (not rendered as visible chars)
        $buf->writeAnsiLines(["\x1b]8;;https://example.com\x07Link\x1b]8;;\x07 Text"]);

        $lines = $buf->toLines();
        $plain = preg_replace('/\x1b\[[0-9;]*m/', '', $lines[0]);
        // Only "Link Text" should be visible
        $this->assertStringStartsWith('Link Text', $plain);
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

    /**
     * @return iterable<string, array{string, int, string, 3?: string}>
     */
    public static function negativeStartColProvider(): iterable
    {
        yield 'plain text' => ['Hello', -3, 'Hello     '];
        yield 'with ANSI styles' => ["\x1b[31mRed\x1b[0m", -5, 'Red       ', '31'];
        yield 'with tab' => ["A\tB", -2, 'A   B     '];
    }

    public function testNegativeStartColDoesNotCorruptBuffer()
    {
        $buf = new CellBuffer(10, 2);
        $buf->writeAnsiLines(['ABCDEFGHIJ']); // fill row 0
        $buf->writeAnsiLines(['XY'], 0, -1); // negative startCol, should clamp to 0

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
}
