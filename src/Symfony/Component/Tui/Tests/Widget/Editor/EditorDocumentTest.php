<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Tui\Tests\Widget\Editor;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Tui\Widget\Editor\EditorDocument;

class EditorDocumentTest extends TestCase
{
    // --- Text Operations ---

    public function testSetTextReturnsWhetherStateChanged()
    {
        $doc = new EditorDocument();

        $this->assertTrue($doc->setText('hello'), 'setText should return true when text changes');
        $this->assertFalse($doc->setText('hello'), 'setText on identical text+cursor should return false');
    }

    public function testSetTextNormalizesLineEndings()
    {
        $doc = new EditorDocument();
        $doc->setText("a\r\nb\rc");

        $this->assertSame("a\nb\nc", $doc->getText());
    }

    public function testSetTextResetsCursorPosition()
    {
        $doc = new EditorDocument();
        $doc->setText("line 1\nline 2\nline 3");
        $doc->moveCursorDown();
        $doc->moveCursorDown();

        $doc->setText('new');

        $this->assertSame(0, $doc->getCursorLine());
        $this->assertSame(0, $doc->getCursorCol());
    }

    public function testSetTextClearsUndoRedoStacks()
    {
        $doc = new EditorDocument();
        $doc->insertText('A');
        $doc->insertText('B');
        $doc->undo();

        $doc->setText('new');

        $this->assertFalse($doc->undo(), 'undo stack should be cleared after setText');
        $this->assertFalse($doc->redo(), 'redo stack should be cleared after setText');
    }

    public function testInsertText()
    {
        $doc = new EditorDocument();
        $doc->insertText('Hello');

        $this->assertSame('Hello', $doc->getText());
        $this->assertSame(5, $doc->getCursorCol());
    }

    public function testInsertTextMultiline()
    {
        $doc = new EditorDocument();
        $doc->insertText("Hello\nWorld");

        $this->assertSame("Hello\nWorld", $doc->getText());
        $this->assertSame(1, $doc->getCursorLine());
        $this->assertSame(5, $doc->getCursorCol());
    }

    public function testInsertNewLine()
    {
        $doc = new EditorDocument();
        $doc->insertText('Hello');
        $doc->insertNewLine();
        $doc->insertText('World');

        $this->assertSame("Hello\nWorld", $doc->getText());
        $this->assertSame(1, $doc->getCursorLine());
    }

    public function testInsertNewLineSplitsAtCursor()
    {
        $doc = new EditorDocument();
        $doc->insertText('HelloWorld');
        // Move cursor to position 5 (after "Hello")
        $doc->moveToLineStart();
        for ($i = 0; $i < 5; ++$i) {
            $doc->moveCursorRight();
        }
        $doc->insertNewLine();

        $this->assertSame("Hello\nWorld", $doc->getText());
    }

    // --- Deletion ---

    public function testDeleteCharBackward()
    {
        $doc = new EditorDocument();
        $doc->insertText('Hello');

        $this->assertTrue($doc->deleteCharBackward());
        $this->assertSame('Hell', $doc->getText());
    }

    public function testDeleteCharBackwardAtStartOfLine()
    {
        $doc = new EditorDocument();
        $doc->setText("Hello\nWorld");
        $doc->moveCursorDown();
        $doc->moveToLineStart();

        $this->assertTrue($doc->deleteCharBackward());
        $this->assertSame('HelloWorld', $doc->getText());
    }

    public function testDeleteCharBackwardAtStartOfDocument()
    {
        $doc = new EditorDocument();
        $doc->setText('Hello');
        $doc->moveToLineStart();

        $this->assertFalse($doc->deleteCharBackward());
        $this->assertSame('Hello', $doc->getText());
    }

    public function testDeleteCharForward()
    {
        $doc = new EditorDocument();
        $doc->setText('Hello');
        $doc->moveToLineStart();

        $this->assertTrue($doc->deleteCharForward());
        $this->assertSame('ello', $doc->getText());
    }

    public function testDeleteCharForwardMergesLines()
    {
        $doc = new EditorDocument();
        $doc->setText("Hello\nWorld");
        $doc->moveToLineEnd();

        $this->assertTrue($doc->deleteCharForward());
        $this->assertSame('HelloWorld', $doc->getText());
    }

    public function testDeleteCharForwardAtEndOfDocument()
    {
        $doc = new EditorDocument();
        $doc->setText('Hello');
        $doc->moveToLineEnd();

        $this->assertFalse($doc->deleteCharForward());
    }

    public function testDeleteLine()
    {
        $doc = new EditorDocument();
        $doc->setText("Line 1\nLine 2\nLine 3");
        $doc->moveCursorDown();

        $this->assertTrue($doc->deleteLine());
        $this->assertSame("Line 1\nLine 3", $doc->getText());
    }

    public function testDeleteLineSingleEmptyLine()
    {
        $doc = new EditorDocument();

        $this->assertFalse($doc->deleteLine());
        $this->assertSame('', $doc->getText());
    }

    public function testDeleteToLineEnd()
    {
        $doc = new EditorDocument();
        $doc->setText('Hello World');
        $doc->moveToLineStart();
        for ($i = 0; $i < 5; ++$i) {
            $doc->moveCursorRight();
        }

        $this->assertTrue($doc->deleteToLineEnd());
        $this->assertSame('Hello', $doc->getText());
    }

    public function testDeleteToLineEndAtEnd()
    {
        $doc = new EditorDocument();
        $doc->setText('Hello');
        $doc->moveToLineEnd();

        $this->assertFalse($doc->deleteToLineEnd());
    }

    public function testDeleteToLineStart()
    {
        $doc = new EditorDocument();
        $doc->setText('Hello World');
        $doc->moveToLineEnd();

        $this->assertTrue($doc->deleteToLineStart());
        $this->assertSame('', $doc->getText());
    }

    public function testDeleteToLineStartAtStart()
    {
        $doc = new EditorDocument();
        $doc->setText('Hello');
        $doc->moveToLineStart();

        $this->assertFalse($doc->deleteToLineStart());
    }

    public function testDeleteWordBackward()
    {
        $doc = new EditorDocument();
        $doc->setText('hello world');
        $doc->moveToLineEnd();

        $this->assertTrue($doc->deleteWordBackward());
        $this->assertSame('hello ', $doc->getText());
    }

    public function testDeleteWordForward()
    {
        $doc = new EditorDocument();
        $doc->setText('hello world');
        $doc->moveToLineStart();

        $this->assertTrue($doc->deleteWordForward());
        $this->assertSame(' world', $doc->getText());
    }

    // --- Cursor Navigation ---

    public function testMoveCursorUpDown()
    {
        $doc = new EditorDocument();
        $doc->setText("Line 1\nLine 2\nLine 3");

        $this->assertTrue($doc->moveCursorDown());
        $this->assertSame(1, $doc->getCursorLine());

        $this->assertTrue($doc->moveCursorDown());
        $this->assertSame(2, $doc->getCursorLine());

        $this->assertFalse($doc->moveCursorDown());

        $this->assertTrue($doc->moveCursorUp());
        $this->assertSame(1, $doc->getCursorLine());

        $this->assertTrue($doc->moveCursorUp());
        $this->assertSame(0, $doc->getCursorLine());

        $this->assertFalse($doc->moveCursorUp());
    }

    public function testMoveCursorLeftRight()
    {
        $doc = new EditorDocument();
        $doc->setText('Hi');
        $doc->moveToLineStart();

        $this->assertTrue($doc->moveCursorRight());
        $this->assertSame(1, $doc->getCursorCol());

        $this->assertTrue($doc->moveCursorRight());
        $this->assertSame(2, $doc->getCursorCol());

        // At end of line but not last line; would cross if there were another line
        $this->assertFalse($doc->moveCursorRight());

        $this->assertTrue($doc->moveCursorLeft());
        $this->assertSame(1, $doc->getCursorCol());
    }

    public function testMoveCursorLeftCrossesLineBoundary()
    {
        $doc = new EditorDocument();
        $doc->setText("AB\nCD");
        $doc->moveCursorDown();
        $doc->moveToLineStart();

        $this->assertTrue($doc->moveCursorLeft());
        $this->assertSame(0, $doc->getCursorLine());
        $this->assertSame(2, $doc->getCursorCol());
    }

    public function testMoveCursorRightCrossesLineBoundary()
    {
        $doc = new EditorDocument();
        $doc->setText("AB\nCD");
        $doc->moveToLineEnd();

        $this->assertTrue($doc->moveCursorRight());
        $this->assertSame(1, $doc->getCursorLine());
        $this->assertSame(0, $doc->getCursorCol());
    }

    public function testMoveToLineStartEnd()
    {
        $doc = new EditorDocument();
        $doc->setText('Hello');
        $doc->moveToLineEnd();
        $this->assertSame(5, $doc->getCursorCol());

        $this->assertTrue($doc->moveToLineStart());
        $this->assertSame(0, $doc->getCursorCol());

        $this->assertFalse($doc->moveToLineStart());

        $this->assertTrue($doc->moveToLineEnd());
        $this->assertFalse($doc->moveToLineEnd());
    }

    public function testMoveWordBackwards()
    {
        $doc = new EditorDocument();
        $doc->setText('hello world');
        $doc->moveToLineEnd();

        $this->assertTrue($doc->moveWordBackwards());
        $this->assertSame(6, $doc->getCursorCol());
    }

    public function testMoveWordForwards()
    {
        $doc = new EditorDocument();
        $doc->setText('hello world');
        $doc->moveToLineStart();

        $this->assertTrue($doc->moveWordForwards());
        $this->assertSame(5, $doc->getCursorCol());
    }

    public function testIsOnFirstLastLine()
    {
        $doc = new EditorDocument();
        $doc->setText("A\nB\nC");

        $this->assertTrue($doc->isOnFirstLine());
        $this->assertFalse($doc->isOnLastLine());

        $doc->moveCursorDown();
        $this->assertFalse($doc->isOnFirstLine());
        $this->assertFalse($doc->isOnLastLine());

        $doc->moveCursorDown();
        $this->assertFalse($doc->isOnFirstLine());
        $this->assertTrue($doc->isOnLastLine());
    }

    // --- Jump To Character ---

    #[DataProvider('jumpToCharProvider')]
    public function testJumpToChar(string $text, string $char, string $direction, int $expectedLine, int $expectedCol, bool $expectedResult)
    {
        $doc = new EditorDocument();
        $doc->setText($text);
        $doc->moveToLineStart();

        $result = $doc->jumpToChar($char, $direction);

        $this->assertSame($expectedResult, $result);
        if ($expectedResult) {
            $this->assertSame($expectedLine, $doc->getCursorLine());
            $this->assertSame($expectedCol, $doc->getCursorCol());
        }
    }

    /**
     * @return iterable<string, array{string, string, string, int, int, bool}>
     */
    public static function jumpToCharProvider(): iterable
    {
        yield 'forward to char' => ['Hello World', 'W', 'forward', 0, 6, true];
        yield 'forward skips current position' => ['aabaa', 'a', 'forward', 0, 1, true];
        yield 'forward across lines' => ["Hello\nWorld", 'W', 'forward', 1, 0, true];
        yield 'no match' => ['Hello', 'Z', 'forward', 0, 0, false];
    }

    // --- Undo/Redo ---

    public function testUndoRedo()
    {
        $doc = new EditorDocument();
        $doc->insertText('A');
        $doc->insertText('B');
        $doc->insertText('C');

        $this->assertSame('ABC', $doc->getText());

        $this->assertTrue($doc->undo());
        $this->assertSame('AB', $doc->getText());

        $this->assertTrue($doc->undo());
        $this->assertSame('A', $doc->getText());

        $this->assertTrue($doc->redo());
        $this->assertSame('AB', $doc->getText());

        $this->assertTrue($doc->redo());
        $this->assertSame('ABC', $doc->getText());

        $this->assertFalse($doc->redo());
    }

    public function testUndoOnEmptyStack()
    {
        $doc = new EditorDocument();

        $this->assertFalse($doc->undo());
    }

    public function testNewEditClearsRedoStack()
    {
        $doc = new EditorDocument();
        $doc->insertText('A');
        $doc->insertText('B');
        $doc->undo();
        $doc->insertText('C');

        $this->assertSame('AC', $doc->getText());
        $this->assertFalse($doc->redo());
    }

    // --- Kill Ring ---

    public function testYankAndYankPop()
    {
        $doc = new EditorDocument();
        $doc->insertText('Hello World');

        // Delete to line end (from start)
        $doc->moveToLineStart();
        $doc->deleteToLineEnd();
        $this->assertSame('', $doc->getText());

        // Yank it back
        $this->assertTrue($doc->yank());
        $this->assertSame('Hello World', $doc->getText());
    }

    public function testYankWithEmptyKillRing()
    {
        $doc = new EditorDocument();

        $this->assertFalse($doc->yank());
    }

    public function testYankPopWithNoYank()
    {
        $doc = new EditorDocument();

        $this->assertFalse($doc->yankPop());
    }

    // --- Paste Handling ---

    public function testSmallPasteDoesNotCreateMarker()
    {
        $doc = new EditorDocument();
        $doc->handlePaste("line 1\nline 2\nline 3");

        $this->assertSame("line 1\nline 2\nline 3", $doc->getText());
        $this->assertSame([], $doc->getPasteMarkers());
    }

    public function testPasteStripsControlBytesToPreventTerminalInjection()
    {
        $doc = new EditorDocument();
        // Simulated hostile clipboard content: tries to set terminal title
        // (OSC 0), inject color (CSI), and emit BEL/DEL. The introducer ESC
        // (\x1b), BEL (\x07) and DEL (\x7f) are stripped so the terminal
        // can't interpret the sequences; remaining payload bytes appear as
        // visible literal text. TAB and LF must be preserved.
        $doc->handlePaste("hello\x1b]0;evil\x07\x1b[31mred\x1b[0m\tworld\x7f\nline2");

        $this->assertSame("hello]0;evil[31mred[0m\tworld\nline2", $doc->getText());
    }

    public function testPasteStripsUtf8EncodedC1Controls()
    {
        $doc = new EditorDocument();
        // U+009B (CSI in C1) encoded as UTF-8 is 0xc2 0x9b. Followed by [31m
        // it would be a CSI sequence on terminals that honour 8-bit C1.
        $doc->handlePaste("hello\xc2\x9b[31mworld");

        $this->assertSame('hello[31mworld', $doc->getText());
    }

    public function testLargePasteCreatesMarker()
    {
        $doc = new EditorDocument();
        $content = implode("\n", array_map(static fn ($i) => "line $i", range(1, 15)));
        $doc->handlePaste($content);

        $markers = $doc->getPasteMarkers();
        $this->assertCount(1, $markers);
        $this->assertSame($content, $markers[0]['content']);

        // getText() expands the marker
        $this->assertSame($content, $doc->getText());
    }

    public function testSetTextClearsPasteMarkers()
    {
        $doc = new EditorDocument();
        $content = implode("\n", array_map(static fn ($i) => "line $i", range(1, 15)));
        $doc->handlePaste($content);
        $this->assertNotSame([], $doc->getPasteMarkers());

        $doc->setText('new');
        $this->assertSame([], $doc->getPasteMarkers());
    }

    // --- UTF-8 ---

    #[DataProvider('utf8DeletionProvider')]
    public function testUtf8BackwardDeletion(string $input, string $expected)
    {
        $doc = new EditorDocument();
        $doc->setText($input);
        $doc->moveToLineEnd();
        $doc->deleteCharBackward();

        $this->assertSame($expected, $doc->getText());
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function utf8DeletionProvider(): iterable
    {
        yield 'Latin accent' => ['café', 'caf'];
        yield 'Emoji' => ['hello👋', 'hello'];
        yield 'Japanese' => ['日本', '日'];
        yield 'CJK' => ['中文', '中'];
    }
}
