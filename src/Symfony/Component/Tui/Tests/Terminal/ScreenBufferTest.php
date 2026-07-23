<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Tui\Tests\Terminal;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Tui\Ansi\ScreenBufferHtmlRenderer;
use Symfony\Component\Tui\Terminal\ScreenBuffer;

class ScreenBufferTest extends TestCase
{
    #[DataProvider('writeAndGetScreenProvider')]
    public function testWriteAndGetScreen(int $width, int $height, string $input, string $expected)
    {
        $screen = new ScreenBuffer($width, $height);
        $screen->write($input);

        $this->assertSame($expected, $screen->getScreen());
    }

    /**
     * @return iterable<string, array{int, int, string, string}>
     */
    public static function writeAndGetScreenProvider(): iterable
    {
        yield 'simple text' => [40, 10, 'Hello, World!', 'Hello, World!'];
        yield 'newlines' => [40, 10, "Line 1\nLine 2\nLine 3", "Line 1\nLine 2\nLine 3"];
        yield 'carriage return' => [40, 10, "First\rSecond", 'Second'];
        yield 'clear screen' => [40, 10, "Old content\x1b[2J\x1b[HNew content", 'New content'];
        yield 'UTF-8 characters' => [40, 10, "→ Option 1\n  Option 2\n✓ Selected", "→ Option 1\n  Option 2\n✓ Selected"];
        yield 'scroll up' => [20, 3, "Line 1\nLine 2\nLine 3\nLine 4", "Line 2\nLine 3\nLine 4"];
        yield 'scroll up multiple' => [20, 3, "A\nB\nC\nD\nE\nF", "D\nE\nF"];
        yield 'wide character plain text' => [40, 10, '你好世界', '你好世界'];
        yield 'wide character multiline' => [40, 10, "你好\n世界", "你好\n世界"];
    }

    public function testCursorMovement()
    {
        $screen = new ScreenBuffer(40, 10);
        // Write "Hello", move cursor back 3, overwrite with "XYZ"
        $screen->write("Hello\x1b[3DXYZ");

        $this->assertSame('HeXYZ', $screen->getScreen());
    }

    public function testClearLine()
    {
        $screen = new ScreenBuffer(40, 10);
        $screen->write("Line 1\nLine 2\nLine 3");
        // Move up one line (from line 3 to line 2) and clear it
        $screen->write("\x1b[A\x1b[2K");

        // Line 2 is now cleared (empty), cursor stays on line 2
        // getScreen trims trailing whitespace, so empty line 2 becomes ""
        $lines = $screen->getLines();
        $this->assertSame('Line 1', rtrim($lines[0]));
        $this->assertSame('', rtrim($lines[1]));
        $this->assertSame('Line 3', rtrim($lines[2]));
    }

    public function testCursorPositioning()
    {
        $screen = new ScreenBuffer(40, 10);
        // Move cursor to row 3, column 5 (1-indexed) and write
        $screen->write("\x1b[3;5HHello");

        $lines = $screen->getLines();
        $this->assertSame('', $lines[0]);
        $this->assertSame('', $lines[1]);
        $this->assertSame('    Hello', $lines[2]);
    }

    public function testDifferentialRenderingSimulation()
    {
        $screen = new ScreenBuffer(20, 5);

        // Simulate what TUI does: full render first, then differential updates
        // First render - after this, cursor is at row 3, col 10 (after "  Option 3")
        $screen->write("Menu:\n  Option 1\n  Option 2\n  Option 3");

        // Move cursor up 2 lines (from row 3 to row 1), go to start of line, clear, and write
        // Row 1 is "  Option 1", so we're updating that line
        $screen->write("\x1b[2A\r\x1b[2K> Option 1 (selected)");

        $lines = $screen->getLines();
        $this->assertSame('Menu:', rtrim($lines[0]));
        $this->assertSame('> Option 1 (selected)', rtrim($lines[1]));
        $this->assertSame('  Option 2', rtrim($lines[2]));
        $this->assertSame('  Option 3', rtrim($lines[3]));
    }

    public function testSgrCodesInPlainText()
    {
        $screen = new ScreenBuffer(40, 10);
        // SGR codes (colors, bold, etc.) should be stripped in plain text output
        $screen->write("\x1b[1m\x1b[32mGreen Bold\x1b[0m Normal");

        $this->assertSame('Green Bold Normal', $screen->getScreen());
    }

    public function testSgrCodesPreservedInStyledOutput()
    {
        $screen = new ScreenBuffer(40, 10);
        $screen->write("\x1b[1;32mGreen Bold\x1b[0m Normal");

        $styled = $screen->getStyledScreen();
        $this->assertStringContainsString("\x1b[1;32m", $styled);
        $this->assertStringContainsString("\x1b[0m", $styled);
        $this->assertStringContainsString('Green Bold', $styled);
    }

    public function testHtmlConversion()
    {
        $screen = new ScreenBuffer(40, 10);
        $screen->write("\x1b[1;32mGreen\x1b[0m \x1b[41mRed BG\x1b[0m");

        $html = new ScreenBufferHtmlRenderer()->convert($screen);
        $this->assertStringContainsString('<span style="', $html);
        $this->assertStringContainsString('Green', $html);
        $this->assertStringContainsString('Red BG', $html);
    }

    public function testEraseFromCursorToEndOfLine()
    {
        $screen = new ScreenBuffer(40, 10);
        $screen->write('Hello World');
        $screen->write("\x1b[6G\x1b[0K"); // Move to column 6, erase to end of line

        $this->assertSame('Hello', $screen->getScreen());
    }

    public function testBackspace()
    {
        $screen = new ScreenBuffer(40, 10);
        $screen->write("Hello\x08"); // Backspace moves cursor back but doesn't delete

        // Backspace only moves cursor, so if we write something it overwrites
        $screen->write('!');

        $this->assertSame('Hell!', $screen->getScreen());
    }

    public function testDelCharacter()
    {
        $screen = new ScreenBuffer(40, 10);
        $screen->write("Hello\x7f\x7f"); // DEL deletes previous characters

        $this->assertSame('Hel', $screen->getScreen());
    }

    public function testDelWithCursor()
    {
        $screen = new ScreenBuffer(40, 10);
        // Simulate typing, then deleting, then showing cursor
        $screen->write("Content: First\x7f\x7f\x7f\x7f\x7f▌");

        $this->assertSame('Content: ▌', $screen->getScreen());
    }

    public function testReverseVideo()
    {
        $screen = new ScreenBuffer(40, 10);
        // Reverse video is used for cursor display
        $screen->write("Hello \x1b[7mW\x1b[27morld");

        // Plain text should show the character normally
        $this->assertSame('Hello World', $screen->getScreen());

        // HTML should show reversed colors
        $html = new ScreenBufferHtmlRenderer()->convert($screen);
        $this->assertStringContainsString('background-color:', $html);
        $this->assertStringContainsString('>W</span>', $html);
    }

    public function testApcSequenceSkipped()
    {
        $screen = new ScreenBuffer(40, 10);
        // APC sequence (used for cursor marker) should be skipped
        $cursorMarker = "\x1b_pi:c\x07";
        $screen->write("Hello{$cursorMarker}World");

        $this->assertSame('HelloWorld', $screen->getScreen());
    }

    public function testCursorWithMarkerAndReverseVideo()
    {
        $screen = new ScreenBuffer(40, 10);
        // This is how the Editor component renders a cursor
        $cursorMarker = "\x1b_pi:c\x07";
        $screen->write("Hello, {$cursorMarker}\x1b[7mW\x1b[27morld!");

        // Plain text shows the character
        $this->assertSame('Hello, World!', $screen->getScreen());

        // HTML shows the cursor with reversed colors
        $html = new ScreenBufferHtmlRenderer()->convert($screen);
        $this->assertStringContainsString('background-color:', $html);
    }

    public function testEraseFromStartOfLineToCursor()
    {
        $screen = new ScreenBuffer(40, 10);
        $screen->write('Hello World');
        $screen->write("\x1b[6G\x1b[1K"); // Move to column 6, erase from start to cursor

        $this->assertSame('      World', $screen->getScreen());
    }

    public function testEraseInLineModes0And1ProduceSameInternalState()
    {
        // Mode 0 (erase cursor to end) + mode 1 (erase start to cursor) should leave an empty line
        $screen = new ScreenBuffer(40, 10);
        $screen->write('Hello World');
        $screen->write("\x1b[6G"); // Move to column 6
        $screen->write("\x1b[0K"); // Erase from cursor to end
        $screen->write("\x1b[1K"); // Erase from start to cursor

        $this->assertSame('', $screen->getScreen());
    }

    public function testEraseInLineMode1DoesNotExtendLine()
    {
        // After erasing from start to cursor, the erased cells should not
        // make the line appear longer than the remaining content
        $screen = new ScreenBuffer(40, 10);
        $screen->write('ABCDE');
        $screen->write("\x1b[3G"); // Move to column 3 (0-indexed: col 2)
        $screen->write("\x1b[1K"); // Erase from start to cursor (cols 0-2)

        // Cells 0-2 are erased, cells 3-4 remain ('D', 'E')
        // The line should show spaces for erased positions then DE
        $this->assertSame('   DE', $screen->getScreen());

        // Verify that the erased cells are truly removed (not space-filled)
        $cells = $screen->getCells();
        $this->assertArrayNotHasKey(0, $cells[0]);
        $this->assertArrayNotHasKey(1, $cells[0]);
        $this->assertArrayNotHasKey(2, $cells[0]);
        $this->assertSame('D', $cells[0][3]['char']);
        $this->assertSame('E', $cells[0][4]['char']);
    }

    public function testGetCellsAndHeight()
    {
        $screen = new ScreenBuffer(40, 10);
        $screen->write("\x1b[32mHello\x1b[0m");

        $cells = $screen->getCells();
        $this->assertSame(10, $screen->getHeight());
        $this->assertSame('H', $cells[0][0]['char']);
        $this->assertSame("\x1b[32m", $cells[0][0]['style']);
    }

    public function testScrollUpPreservesRowKeys()
    {
        $screen = new ScreenBuffer(20, 3);
        $screen->write("Line 1\nLine 2\nLine 3\nLine 4");

        $cells = $screen->getCells();
        $this->assertSame([0, 1, 2], array_keys($cells));
    }

    public function testScrollUpWithStyledContent()
    {
        $screen = new ScreenBuffer(20, 3);
        $screen->write("\x1b[31mRed\x1b[0m\n\x1b[32mGreen\x1b[0m\nPlain\n\x1b[34mBlue\x1b[0m");

        // Red line scrolled off, Green, Plain, Blue remain
        $cells = $screen->getCells();
        $this->assertSame("\x1b[32m", $cells[0][0]['style']);
        $this->assertSame('G', $cells[0][0]['char']);
        $this->assertSame('', $cells[1][0]['style']);
        $this->assertSame('P', $cells[1][0]['char']);
        $this->assertSame("\x1b[34m", $cells[2][0]['style']);
        $this->assertSame('B', $cells[2][0]['char']);
    }

    public function testScrollUpStyleStatePersists()
    {
        $screen = new ScreenBuffer(20, 3);
        // Start green style, write 3 lines to fill screen, then scroll and write on new line
        $screen->write("\x1b[32m");
        $screen->write("Line 1\nLine 2\nLine 3\nNew");

        // "New" should still have the green style since it was never reset
        $cells = $screen->getCells();
        $this->assertSame("\x1b[32m", $cells[2][0]['style']);
        $this->assertSame('N', $cells[2][0]['char']);
    }

    public function testScrollUpNewRowIsEmpty()
    {
        $screen = new ScreenBuffer(20, 3);
        $screen->write("Line 1\nLine 2\nLine 3\n");

        // After scrolling, the last row should be empty
        $cells = $screen->getCells();
        $this->assertSame([], $cells[2]);
    }

    public function testWideCharacterOccupiesTwoCells()
    {
        $screen = new ScreenBuffer(40, 10);
        $screen->write('你好');

        $cells = $screen->getCells();
        // Column 0: wide character '你'
        $this->assertSame('你', $cells[0][0]['char']);
        // Column 1: placeholder for the second half of '你'
        $this->assertSame('', $cells[0][1]['char']);
        // Column 2: wide character '好'
        $this->assertSame('好', $cells[0][2]['char']);
        // Column 3: placeholder for the second half of '好'
        $this->assertSame('', $cells[0][3]['char']);
    }

    public function testWideCharacterCursorAdvancesByTwo()
    {
        $screen = new ScreenBuffer(40, 10);
        // Write one wide char then an ASCII char
        $screen->write('你A');

        $cells = $screen->getCells();
        $this->assertSame('你', $cells[0][0]['char']);
        $this->assertSame('', $cells[0][1]['char']);
        $this->assertSame('A', $cells[0][2]['char']);
    }

    public function testWideCharacterMixedWithAscii()
    {
        $screen = new ScreenBuffer(40, 10);
        $screen->write('Hi你好OK');

        $cells = $screen->getCells();
        // H(0) i(1) 你(2,3) 好(4,5) O(6) K(7)
        $this->assertSame('H', $cells[0][0]['char']);
        $this->assertSame('i', $cells[0][1]['char']);
        $this->assertSame('你', $cells[0][2]['char']);
        $this->assertSame('', $cells[0][3]['char']);
        $this->assertSame('好', $cells[0][4]['char']);
        $this->assertSame('', $cells[0][5]['char']);
        $this->assertSame('O', $cells[0][6]['char']);
        $this->assertSame('K', $cells[0][7]['char']);

        $this->assertSame('Hi你好OK', $screen->getScreen());
    }

    public function testWideCharacterWithStyle()
    {
        $screen = new ScreenBuffer(40, 10);
        $screen->write("\x1b[31m你好\x1b[0m");

        $styled = $screen->getStyledScreen();
        $this->assertStringContainsString('你好', $styled);
        $this->assertStringContainsString("\x1b[31m", $styled);
    }

    public function testWideCharacterWithCursorPositioning()
    {
        $screen = new ScreenBuffer(40, 10);
        // Position cursor at column 4 and write a wide character
        $screen->write("\x1b[1;5H你");

        $cells = $screen->getCells();
        $this->assertSame('你', $cells[0][4]['char']);
        $this->assertSame('', $cells[0][5]['char']);
    }

    public function testWideCharacterOverwrittenByAscii()
    {
        $screen = new ScreenBuffer(40, 10);
        $screen->write('你');
        // Move back to column 0 and overwrite with ASCII
        $screen->write("\r".'AB');

        $cells = $screen->getCells();
        $this->assertSame('A', $cells[0][0]['char']);
        $this->assertSame('B', $cells[0][1]['char']);
    }

    public function testFullwidthForms()
    {
        $screen = new ScreenBuffer(40, 10);
        // Fullwidth Latin A (U+FF21)
        $screen->write('Ａ');

        $cells = $screen->getCells();
        $this->assertSame('Ａ', $cells[0][0]['char']);
        $this->assertSame('', $cells[0][1]['char']);
    }

    public function testOverwriteContinuationCellClearsWideChar()
    {
        $screen = new ScreenBuffer(40, 10);
        $screen->write('你');
        // Move cursor to column 1 (the continuation cell) and overwrite
        $screen->write("\x1b[1;2HA");

        $cells = $screen->getCells();
        // The wide char at column 0 must be replaced with a space
        $this->assertSame(' ', $cells[0][0]['char']);
        $this->assertSame('A', $cells[0][1]['char']);
    }

    public function testOverwriteMainCellClearsContinuation()
    {
        $screen = new ScreenBuffer(40, 10);
        $screen->write('你');
        // Move cursor back to column 0 and overwrite with a narrow char
        $screen->write("\x1b[1;1HA");

        $cells = $screen->getCells();
        $this->assertSame('A', $cells[0][0]['char']);
        // The orphaned continuation cell must be replaced with a space
        $this->assertSame(' ', $cells[0][1]['char']);
    }

    public function testWideCharAtScreenEdgeIsSkipped()
    {
        $screen = new ScreenBuffer(5, 2);
        $screen->write('ABCD');
        // Cursor is now at column 4 (last column), wide char needs 2 cells
        $screen->write('你');

        $cells = $screen->getCells();
        // The wide char should NOT be placed, it doesn't fit
        $this->assertSame('D', $cells[0][3]['char']);
        $this->assertArrayNotHasKey(4, $cells[0]);
    }

    public function testEraseFromCursorToEndClearsWideCharOnBoundary()
    {
        $screen = new ScreenBuffer(40, 10);
        $screen->write('你好');
        // Move cursor to column 1 (continuation cell of 你)
        $screen->write("\x1b[1;2H");
        // Erase from cursor to end of line
        $screen->write("\x1b[0K");

        $cells = $screen->getCells();
        // The wide char 你 at col 0 must also be erased since its continuation was erased
        $this->assertArrayNotHasKey(0, $cells[0]);
        $this->assertArrayNotHasKey(1, $cells[0]);
        $this->assertArrayNotHasKey(2, $cells[0]);
        $this->assertArrayNotHasKey(3, $cells[0]);
    }

    public function testEraseFromStartToCursorClearsWideCharOnBoundary()
    {
        $screen = new ScreenBuffer(40, 10);
        $screen->write('AB你好CD');
        // Move cursor to column 2 (main cell of 你)
        $screen->write("\x1b[1;3H");
        // Erase from start of line to cursor
        $screen->write("\x1b[1K");

        $cells = $screen->getCells();
        // Cols 0-2 erased, and col 3 (continuation of 你) must also be erased
        $this->assertArrayNotHasKey(0, $cells[0]);
        $this->assertArrayNotHasKey(1, $cells[0]);
        $this->assertArrayNotHasKey(2, $cells[0]);
        $this->assertArrayNotHasKey(3, $cells[0]);
        // 好 and CD remain
        $this->assertSame('好', $cells[0][4]['char']);
        $this->assertSame('', $cells[0][5]['char']);
        $this->assertSame('C', $cells[0][6]['char']);
        $this->assertSame('D', $cells[0][7]['char']);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function underlineColorPreservedProvider(): iterable
    {
        yield '256-color index' => ["\x1b[4;58;5;196mHello\x1b[0m", '58;5;196'];
        yield 'true-color RGB' => ["\x1b[4;58;2;255;0;0mHello\x1b[0m", '58;2;255;0;0'];
    }

    #[DataProvider('underlineColorPreservedProvider')]
    public function testUnderlineColorPreservedInStyledOutput(string $input, string $expectedCode)
    {
        $screen = new ScreenBuffer(40, 10);
        $screen->write($input);

        $styled = $screen->getStyledScreen();
        $this->assertStringContainsString($expectedCode, $styled);
        $this->assertStringContainsString('Hello', $styled);
    }

    public function testUnderlineColorDoesNotCorruptFollowingCodes()
    {
        $screen = new ScreenBuffer(40, 10);
        // This was the original bug: 58;2;R;G;B sub-parameters were
        // misinterpreted as separate SGR codes (2 → dim, 0 → reset)
        $screen->write("\x1b[58;2;255;0;0mHello\x1b[0m World");

        // "Hello" must NOT have dim set (code 2 was a sub-parameter, not SGR 2)
        $cells = $screen->getCells();
        $style = $cells[0][0]['style'];
        $this->assertStringNotContainsString("\x1b[2", $style);

        // "World" must be rendered (code 0 was a sub-parameter, not SGR 0 reset)
        $this->assertSame('Hello World', $screen->getScreen());
    }

    public function testDefaultUnderlineColorResets()
    {
        $screen = new ScreenBuffer(40, 10);
        // Set underline color, then reset it with SGR 59
        $screen->write("\x1b[4;58;5;196mRed\x1b[59m Normal\x1b[0m");

        $cells = $screen->getCells();
        // 'R' should have underline color
        $this->assertStringContainsString('58;5;196', $cells[0][0]['style']);
        // ' ' (space before "Normal") should NOT have underline color
        $this->assertStringNotContainsString('58;', $cells[0][3]['style']);
    }

    public function testGetScreenAndGetStyledScreenTrimConsistently()
    {
        $screen = new ScreenBuffer(40, 5);
        $screen->write("Line 1\n\x1b[32mLine 3\x1b[0m");

        $plainLines = explode("\n", $screen->getScreen());
        $styledLines = explode("\n", $screen->getStyledScreen());

        $this->assertCount(\count($plainLines), $styledLines);
    }

    public function testEmptyScreenReturnsEmptyString()
    {
        $screen = new ScreenBuffer(40, 5);

        $this->assertSame('', $screen->getScreen());
        $this->assertSame('', $screen->getStyledScreen());
    }

    public function testClearAndSgrResetProduceSameStyleState()
    {
        // Write styled text, then SGR reset, then a character
        $screenA = new ScreenBuffer(40, 10);
        $screenA->write("\x1b[1;3;4;31;42mStyled\x1b[0mA");

        // Write styled text, then clear(), then a character
        $screenB = new ScreenBuffer(40, 10);
        $screenB->write("\x1b[1;3;4;31;42mStyled");
        $screenB->clear();
        $screenB->write('A');

        $cellsA = $screenA->getCells();
        $cellsB = $screenB->getCells();

        // Both 'A' characters should have the same (empty) style
        $this->assertSame($cellsB[0][0]['style'], $cellsA[0][6]['style']);
        $this->assertSame('', $cellsA[0][6]['style']);
    }

    #[DataProvider('ignoredSequenceProvider')]
    public function testEscapeSequenceIsIgnored(string $input, string $expectedScreen)
    {
        $screen = new ScreenBuffer(40, 5);
        $screen->write($input);

        $this->assertSame($expectedScreen, $screen->getScreen());
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function ignoredSequenceProvider(): iterable
    {
        yield 'DCS' => ["AB\x1bPq;sixeldata\x1b\\CD", 'ABCD'];
        yield 'PM' => ["AB\x1b^private\x1b\\CD", 'ABCD'];
        yield 'SOS' => ["AB\x1bXstring\x1b\\CD", 'ABCD'];
        yield 'Fe (IND + RI)' => ["\x1bDAB\x1bMCD", 'ABCD'];
        yield 'Fp (DECSC + DECRC)' => ["\x1b7AB\x1b8CD", 'ABCD'];
        yield 'nF charset designation' => ["\x1b(0AB\x1b(BCD", 'ABCD'];
        yield 'private mode set/reset' => ["Hello\x1b[?25h\x1b[?25l World", 'Hello World'];
        yield 'standard mode set/reset' => ["Hello\x1b[25h\x1b[25l World", 'Hello World'];
    }
}
