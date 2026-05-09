<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Tui\Tests\Widget;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Tui\Ansi\AnsiUtils;
use Symfony\Component\Tui\Event\ChangeEvent;
use Symfony\Component\Tui\Render\RenderContext;
use Symfony\Component\Tui\Render\Renderer;
use Symfony\Component\Tui\Style\Style;
use Symfony\Component\Tui\Style\StyleSheet;
use Symfony\Component\Tui\Terminal\VirtualTerminal;
use Symfony\Component\Tui\Tui;
use Symfony\Component\Tui\Widget\AbstractWidget;
use Symfony\Component\Tui\Widget\ContainerWidget;
use Symfony\Component\Tui\Widget\EditorWidget;

class EditorTest extends TestCase
{
    public function testRenderEmpty()
    {
        $editor = new EditorWidget();
        $lines = $editor->render(new RenderContext(40, 24));

        // An empty editor renders a border + the cursor line (top border + content + bottom border)
        $this->assertCount(3, $lines);
    }

    public function testTypeCharacter()
    {
        $editor = new EditorWidget();
        $editor->handleInput('H');
        $editor->handleInput('i');

        $this->assertSame('Hi', $editor->getText());
    }

    public function testBackspace()
    {
        $editor = new EditorWidget();
        $editor->setText('Hello');

        // Move cursor to end
        $editor->handleInput("\x1b[F"); // End key
        $editor->handleInput("\x7f"); // Backspace

        $this->assertSame('Hell', $editor->getText());
    }

    #[DataProvider('deleteWordBackwardProvider')]
    public function testDeleteWordBackward(string $keySequence, string $description)
    {
        $editor = new EditorWidget();
        $editor->setText('hello world');

        $editor->handleInput("\x1b[F"); // End key
        $editor->handleInput($keySequence);

        $this->assertSame('hello ', $editor->getText());
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function deleteWordBackwardProvider(): iterable
    {
        yield 'Alt+Backspace (legacy: ESC + DEL)' => ["\x1b\x7f", 'Alt+Backspace'];
        yield 'Ctrl+W' => ["\x17", 'Ctrl+W'];
        yield 'Alt+Backspace (Kitty protocol)' => ["\x1b[127;3u", 'Kitty Alt+Backspace'];
    }

    public function testEnterCreatesNewLine()
    {
        $editor = new EditorWidget();
        $editor->setText('Hello');
        // Move cursor to end using Ctrl+E
        $editor->handleInput("\x05");
        // Shift+Enter for new line (using Kitty protocol sequence)
        $editor->handleInput("\x1b[13;2u");

        $this->assertStringContainsString("\n", $editor->getText());
    }

    public function testOnChangeCallback()
    {
        [$editor, $tui] = $this->createEditorWithTui();

        $changedText = null;
        $tui->addListener(static function (ChangeEvent $e) use (&$changedText) {
            $changedText = $e->getValue();
        });

        $editor->handleInput('X');

        $this->assertSame('X', $changedText);
    }

    public function testFocusable()
    {
        $editor = new EditorWidget();

        $this->assertFalse($editor->isFocused());

        $editor->setFocused(true);
        $this->assertTrue($editor->isFocused());

        $editor->setFocused(false);
        $this->assertFalse($editor->isFocused());
    }

    public function testRenderWithinWidth()
    {
        $editor = new EditorWidget();
        $editor->setText("Line 1\nLine 2\nLine 3");

        $width = 40;
        $lines = $editor->render(new RenderContext($width, 24));

        foreach ($lines as $i => $line) {
            $lineWidth = AnsiUtils::visibleWidth($line);
            $this->assertLessThanOrEqual(
                $width,
                $lineWidth,
                \sprintf('Line %d exceeds width: %d > %d', $i, $lineWidth, $width),
            );
        }
    }

    public function testCursorMovement()
    {
        $editor = new EditorWidget();
        $editor->setText('Hello');

        // Move to start
        $editor->handleInput("\x01"); // Ctrl+A (home)

        // Type at start
        $editor->handleInput('X');

        $this->assertSame('XHello', $editor->getText());
    }

    public function testDeleteToEnd()
    {
        $editor = new EditorWidget();
        $editor->setText('Hello World');

        // Move to start then right 5 times (after "Hello")
        $editor->handleInput("\x01"); // Start
        for ($i = 0; $i < 5; ++$i) {
            $editor->handleInput("\x1b[C"); // Right arrow
        }

        // Delete to end
        $editor->handleInput("\x0b"); // Ctrl+K

        $this->assertSame('Hello', $editor->getText());
    }

    public function testDeleteLine()
    {
        [$editor, $tui] = $this->createEditorWithTui();
        $tui->start();
        $editor->setText("Line 1\nLine 2\nLine 3");

        // Move cursor to line 2
        $editor->handleInput("\x1b[B"); // Down arrow

        // Delete line (Ctrl+Shift+K)
        $editor->handleInput("\x1b[107;6u"); // Kitty: Ctrl+Shift+K

        $this->assertSame("Line 1\nLine 3", $editor->getText());

        $tui->stop();
    }

    public function testDeleteLineLastLine()
    {
        [$editor, $tui] = $this->createEditorWithTui();
        $tui->start();
        $editor->setText("Line 1\nLine 2");

        // Move to last line
        $editor->handleInput("\x1b[B"); // Down arrow

        // Delete line
        $editor->handleInput("\x1b[107;6u"); // Kitty: Ctrl+Shift+K

        $this->assertSame('Line 1', $editor->getText());

        $tui->stop();
    }

    public function testDeleteLineSingleLine()
    {
        [$editor, $tui] = $this->createEditorWithTui();
        $tui->start();
        $editor->setText('Only line');

        // Delete line
        $editor->handleInput("\x1b[107;6u"); // Kitty: Ctrl+Shift+K

        $this->assertSame('', $editor->getText());

        $tui->stop();
    }

    public function testLongLineWrapping()
    {
        $editor = new EditorWidget();
        // Create a line that is longer than our test width
        $longText = str_repeat('a', 50);
        $editor->setText($longText);

        // Move cursor to end
        $editor->handleInput("\x05"); // Ctrl+E

        $width = 30;
        $lines = $editor->render(new RenderContext($width, 24));

        // Verify no line exceeds width
        foreach ($lines as $i => $line) {
            $lineWidth = AnsiUtils::visibleWidth($line);
            $this->assertLessThanOrEqual(
                $width,
                $lineWidth,
                \sprintf('Line %d exceeds width: %d > %d', $i, $lineWidth, $width),
            );
        }

        // Should have wrapped into multiple content lines (plus borders)
        // With 50 chars and width 30, we expect at least 2 content lines
        $this->assertGreaterThanOrEqual(4, \count($lines), 'Long line should wrap into multiple display lines');
    }

    public function testLongLineWrappingWithCursorAtEnd()
    {
        $editor = new EditorWidget();
        $editor->setFocused(true);

        // Create a long line
        $longText = str_repeat('x', 100);
        $editor->setText($longText);

        // Move cursor to end
        $editor->handleInput("\x05"); // Ctrl+E

        $width = 40;
        $lines = $editor->render(new RenderContext($width, 24));

        // Verify no line exceeds width - this was the bug
        foreach ($lines as $i => $line) {
            $lineWidth = AnsiUtils::visibleWidth($line);
            $this->assertLessThanOrEqual(
                $width,
                $lineWidth,
                \sprintf('Line %d exceeds width: %d > %d (cursor at end of long line)', $i, $lineWidth, $width),
            );
        }
    }

    public function testCursorAtEndOfFullWidthLine()
    {
        $editor = new EditorWidget();
        $editor->setFocused(true);

        // Create a line that is exactly at the width limit
        // This tests the edge case where cursor at end would normally add a space
        // but there's no room for it
        $width = 50;
        $textLength = $width; // Exactly the content width
        $longText = str_repeat('a', $textLength);
        $editor->setText($longText);

        // Move cursor to end
        $editor->handleInput("\x05"); // Ctrl+E

        $lines = $editor->render(new RenderContext($width, 24));

        // Verify no line exceeds width - the cursor should highlight the last char
        // instead of adding a space
        foreach ($lines as $i => $line) {
            $lineWidth = AnsiUtils::visibleWidth($line);
            $this->assertLessThanOrEqual(
                $width,
                $lineWidth,
                \sprintf('Line %d exceeds width: %d > %d (cursor at end of exactly full-width line)', $i, $lineWidth, $width),
            );
        }
    }

    /**
     * UTF-8 REGRESSION TESTS - Prevent invalid multi-byte character handling.
     * These tests ensure that cursor positioning and text manipulation
     * always work with complete graphemes, not byte boundaries.
     *
     * @see https://github.com/fabpot/into-the-void/issues/XXX
     */
    public function testUtf8CursorLeftMovement()
    {
        // Regression: moveCursorLeft decremented by 1 byte
        $editor = new EditorWidget();
        $editor->setText('café');
        $editor->handleInput("\x1b[F"); // End
        $editor->handleInput("\x1b[D"); // Left arrow
        $editor->handleInput("\x7f");   // Backspace

        // Should delete 'f' (char before cursor at 'é')
        $this->assertSame('caé', $editor->getText());
    }

    public function testUtf8CursorRightMovement()
    {
        // Regression: moveCursorRight incremented by 1 byte
        $editor = new EditorWidget();
        $editor->setText('café');
        $editor->handleInput("\x1b[H"); // Home
        $editor->handleInput("\x1b[C"); // Right arrow
        $editor->handleInput("\x1b[C"); // Right arrow
        $editor->handleInput("\x1b[C"); // Right arrow
        $editor->handleInput("\x1b[C"); // Right arrow - now at 'é'
        $editor->handleInput("\x1b[C"); // Right arrow - should move past 'é'
        $editor->handleInput("\x7f");   // Backspace - delete 'é'

        $this->assertSame('caf', $editor->getText());
    }

    public function testUtf8MultipleBackspaces()
    {
        $editor = new EditorWidget();
        $editor->setText('café');
        $editor->handleInput("\x1b[F"); // End

        // Delete all characters one by one
        $editor->handleInput("\x7f");
        $this->assertSame('caf', $editor->getText());

        $editor->handleInput("\x7f");
        $this->assertSame('ca', $editor->getText());

        $editor->handleInput("\x7f");
        $this->assertSame('c', $editor->getText());

        $editor->handleInput("\x7f");
        $this->assertSame('', $editor->getText());
    }

    public function testUtf8TypeMultibyte()
    {
        $editor = new EditorWidget();
        $editor->handleInput('h');
        $editor->handleInput('i');
        $editor->handleInput('é');
        $editor->handleInput('s');

        $this->assertSame('hiés', $editor->getText());
    }

    public function testUtf8MultilineWithMultibyte()
    {
        $editor = new EditorWidget();
        $editor->setText("café\nté");

        // Move to second line
        $editor->handleInput("\x1b[F"); // End (of first line)
        $editor->handleInput("\x1b[B"); // Down
        $editor->handleInput("\x1b[F"); // End (of second line)
        $editor->handleInput("\x7f");   // Backspace

        $this->assertSame("café\nt", $editor->getText());
    }

    /**
     * @param list<string> $setup  Inputs to position cursor
     * @param list<string> $action Key sequence to execute
     */
    #[DataProvider('utf8OperationsProvider')]
    public function testUtf8Operations(string $text, array $setup, array $action, string $expected)
    {
        $editor = new EditorWidget();
        $editor->setText($text);
        foreach ($setup as $input) {
            $editor->handleInput($input);
        }
        foreach ($action as $input) {
            $editor->handleInput($input);
        }
        $this->assertSame($expected, $editor->getText());
    }

    /**
     * @return iterable<string, array{string, list<string>, list<string>, string}>
     */
    public static function utf8OperationsProvider(): iterable
    {
        yield 'delete to line end' => ['café naïve', ["\x1b[H", "\x1b[C"], ["\x0b"], 'c'];
        yield 'delete to line start' => ['café naïve', ["\x1b[F"], ["\x15"], ''];
        yield 'word delete backward' => ['hello café', ["\x1b[F"], ["\x17"], 'hello '];
        yield 'word delete forward' => ['café hello', ["\x1b[H"], ["\x1bd"], ' hello'];
    }

    #[DataProvider('utf8BackwardDeletionProvider')]
    public function testUtf8BackwardDeletion(string $input, string $expected)
    {
        $editor = new EditorWidget();
        $editor->setText($input);
        $editor->handleInput("\x1b[F"); // End
        $editor->handleInput("\x7f");   // Backspace
        $this->assertSame($expected, $editor->getText());
        $this->assertValidUtf8($editor->getText());
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function utf8BackwardDeletionProvider(): iterable
    {
        yield 'Latin accent' => ['café', 'caf'];
        yield 'Multiple accents' => ['café naïve', 'café naïv'];
        yield 'Emoji' => ['hello👋', 'hello'];
        yield 'Japanese' => ['日本', '日'];
        yield 'Chinese' => ['中文', '中'];
        yield 'Korean' => ['한글', '한'];
        yield 'Mixed Latin+Emoji' => ['hi👋bye', 'hi👋by'];
        yield 'CJK three characters' => ['日本語', '日本'];
        yield 'Mixed scripts Latin+CJK' => ['café日本', 'café日'];
    }

    #[DataProvider('utf8ForwardDeletionProvider')]
    public function testUtf8ForwardDeletion(string $input, string $expected)
    {
        $editor = new EditorWidget();
        $editor->setText($input);
        $editor->handleInput("\x1b[H");   // Home
        $editor->handleInput("\x1b[3~");  // Delete
        $this->assertSame($expected, $editor->getText());
        $this->assertValidUtf8($editor->getText());
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function utf8ForwardDeletionProvider(): iterable
    {
        yield 'Latin accent' => ['café', 'afé'];
        yield 'Emoji in middle' => ['hello👋world', 'ello👋world'];
        yield 'Japanese' => ['日本語', '本語'];
        yield 'Mixed scripts' => ['café🌍hello', 'afé🌍hello'];
    }

    /**
     * UTF-8 CURSOR RENDERING TESTS - Ensure cursor rendering through
     * multi-byte characters doesn't produce invalid UTF-8 output.
     */
    #[DataProvider('cursorRenderingUtf8Provider')]
    public function testCursorRenderingWithUtf8(string $text, int $width)
    {
        [$editor, $tui] = $this->createEditorWithTui();
        $editor->setFocused(true);
        $editor->setText($text);

        $lines = $editor->render(new RenderContext($width, 24));

        foreach ($lines as $i => $line) {
            $this->assertValidUtf8($line, \sprintf('Line %d should have valid UTF-8', $i));
            $this->assertLessThanOrEqual(
                $width,
                AnsiUtils::visibleWidth($line),
                \sprintf('Line %d should not exceed width %d', $i, $width),
            );
        }
    }

    /**
     * @return iterable<string, array{string, int}>
     */
    public static function cursorRenderingUtf8Provider(): iterable
    {
        yield 'single emoji' => ['📝 Real-time preview', 40];
        yield 'multiple emojis multiline' => ["📝 Real-time preview\n🎨 Syntax highlighting\n📂 File loading", 40];
        yield 'cursor at emoji start' => ['📝 Hello', 40];
        yield 'emoji byte sequence' => ['📝 Hello', 40];
        yield 'Latin accent' => ['café', 40];
        yield 'CJK characters' => ['日本語テスト', 40];
        yield 'mixed scripts' => ['Hello café 🌍 日本語', 50];
        yield 'consecutive emojis' => ['📝🎨📂', 40];
        yield 'emoji at wrap boundary' => ['hello world 📝 this is a test', 20];
        yield 'emoji at line start after wrap' => ['12345678901234567890📝test', 21];
    }

    public function testCursorAfterEmoji()
    {
        [$editor, $tui] = $this->createEditorWithTui();
        $editor->setFocused(true);
        $editor->setText('📝 Hello');

        // Move cursor past the emoji
        $editor->handleInput("\x1b[C"); // Move right once (on emoji)
        $editor->handleInput("\x1b[C"); // Move right again (past emoji)
        $editor->handleInput("\x1b[C"); // Move right once more (on space)

        $lines = $editor->render(new RenderContext(40, 24));

        foreach ($lines as $line) {
            $this->assertValidUtf8($line, 'Line should have valid UTF-8 with cursor after emoji');
        }
    }

    public function testBackspaceWithEmojiThenRender()
    {
        [$editor, $tui] = $this->createEditorWithTui();
        $editor->setFocused(true);
        $editor->setText('hello👋');

        $editor->handleInput("\x1b[F"); // End
        $editor->handleInput("\x7f");   // Backspace

        $lines = $editor->render(new RenderContext(40, 24));

        foreach ($lines as $line) {
            $this->assertValidUtf8($line, 'Line should have valid UTF-8 after emoji deletion');
        }
    }

    /**
     * @param list<string> $setup  Inputs to position cursor before the boundary action
     * @param list<string> $action Boundary key + marker input
     */
    #[DataProvider('cursorBoundaryProvider')]
    public function testCursorBoundaryBehavior(string $text, array $setup, array $action, string $expected)
    {
        $editor = new EditorWidget();
        $editor->setText($text);
        foreach ($setup as $input) {
            $editor->handleInput($input);
        }
        foreach ($action as $input) {
            $editor->handleInput($input);
        }
        $this->assertSame($expected, $editor->getText());
    }

    /**
     * @return iterable<string, array{string, list<string>, list<string>, string}>
     */
    public static function cursorBoundaryProvider(): iterable
    {
        yield 'up on first line goes to start' => ['Hello World', ["\x1b[F"], ["\x1b[A", 'X'], 'XHello World'];
        yield 'down on last line goes to end' => ['Hello World', ["\x1b[H"], ["\x1b[B", 'X'], 'Hello WorldX'];
        yield 'up on multiline moves up' => ["Line 1\nLine 2", ["\x1b[B", "\x1b[F"], ["\x1b[A", "\x1b[H", 'X'], "XLine 1\nLine 2"];
        yield 'down on multiline moves down' => ["Line 1\nLine 2", [], ["\x1b[B", "\x1b[H", 'X'], "Line 1\nXLine 2"];
        yield 'up on first line of multiline goes to start' => ["Hello World\nLine 2", ["\x1b[F"], ["\x1b[A", 'X'], "XHello World\nLine 2"];
        yield 'down on last line of multiline goes to end' => ["Line 1\nHello World", ["\x1b[B", "\x1b[H"], ["\x1b[B", 'X'], "Line 1\nHello WorldX"];
    }

    /**
     * @param string[] $inputs
     */
    #[DataProvider('jumpToCharacterProvider')]
    public function testJumpToCharacter(string $text, array $inputs, string $expected)
    {
        $editor = new EditorWidget();
        $editor->setText($text);

        foreach ($inputs as $input) {
            $editor->handleInput($input);
        }

        $editor->handleInput('X');
        $this->assertSame($expected, $editor->getText());
    }

    /**
     * @return iterable<string, array{string, string[], string}>
     */
    public static function jumpToCharacterProvider(): iterable
    {
        yield 'forward to character' => [
            'Hello World',
            ["\x1b[H", "\x1d", 'W'],
            'Hello XWorld',
        ];
        yield 'backward to character' => [
            'Hello World',
            ["\x1b[F", "\x1b\x1d", 'H'],
            'XHello World',
        ];
        yield 'forward skips current position' => [
            'aabaa',
            ["\x1b[H", "\x1d", 'a'],
            'aXabaa',
        ];
        yield 'forward across lines' => [
            "Hello\nWorld",
            ["\x1b[H", "\x1d", 'W'],
            "Hello\nXWorld",
        ];
        yield 'cancelled by pressing again' => [
            'Hello',
            ["\x1b[H", "\x1d", "\x1d"],
            'XHello',
        ];
        yield 'no match does not move cursor' => [
            'Hello',
            ["\x1b[H", "\x1d", 'Z'],
            'XHello',
        ];
        yield 'forward from multi-byte character' => [
            'é_hello',
            ["\x1b[H", "\x1d", 'h'],
            'é_Xhello',
        ];
        yield 'forward skips multi-byte at current position' => [
            'éhéllo',
            ["\x1b[H", "\x1d", 'é'],
            'éhXéllo',
        ];
        yield 'backward to multi-byte character' => [
            'héllo',
            ["\x1b[F", "\x1b\x1d", 'é'],
            'hXéllo',
        ];
        yield 'forward to emoji' => [
            '😀 hello 😀 world',
            ["\x1b[H", "\x1d", '😀'],
            '😀 hello X😀 world',
        ];
    }

    public function testPageScrollDown()
    {
        [$editor, $tui] = $this->createEditorWithTui();
        // Create many lines
        $lines = [];
        for ($i = 0; $i < 50; ++$i) {
            $lines[] = "Line $i";
        }
        $editor->setText(implode("\n", $lines));
        $editor->handleInput("\x1b[H"); // Home

        // Page down
        $editor->handleInput("\x1b[6~"); // Page Down

        // Cursor should have moved down (not on first line anymore)
        $editor->handleInput("\x1b[H"); // Home
        $editor->handleInput('X');

        $text = $editor->getText();
        // First line should NOT have X - cursor moved down
        $this->assertStringStartsWith('Line 0', $text);
        $this->assertStringContainsString('X', $text);
    }

    public function testPageScrollUp()
    {
        [$editor, $tui] = $this->createEditorWithTui();
        // Create many lines
        $lines = [];
        for ($i = 0; $i < 50; ++$i) {
            $lines[] = "Line $i";
        }
        $editor->setText(implode("\n", $lines));

        // Go to last line
        for ($i = 0; $i < 50; ++$i) {
            $editor->handleInput("\x1b[B"); // Down
        }

        // Page up
        $editor->handleInput("\x1b[5~"); // Page Up

        // Cursor should have moved up from last line
        $editor->handleInput("\x1b[H"); // Home
        $editor->handleInput('X');

        $text = $editor->getText();
        // Last line should NOT have X
        $lastLine = explode("\n", $text);
        $this->assertStringStartsWith('Line 49', end($lastLine));
    }

    /**
     * @param list<string> $setup  Inputs to position cursor before the keybinding
     * @param list<string> $action Keybinding + optional marker input
     */
    #[DataProvider('keybindingProvider')]
    public function testKeybinding(string $text, array $setup, array $action, string $expected)
    {
        $editor = new EditorWidget();
        $editor->setText($text);
        foreach ($setup as $input) {
            $editor->handleInput($input);
        }
        foreach ($action as $input) {
            $editor->handleInput($input);
        }
        $this->assertSame($expected, $editor->getText());
    }

    /**
     * @return iterable<string, array{string, list<string>, list<string>, string}>
     */
    public static function keybindingProvider(): iterable
    {
        yield 'Ctrl+B moves left' => ['Hello', ["\x1b[F"], ["\x02", 'X'], 'HellXo'];
        yield 'Ctrl+F moves right' => ['Hello', ["\x1b[H"], ["\x06", 'X'], 'HXello'];
        yield 'Ctrl+D deletes forward' => ['Hello', ["\x1b[H"], ["\x04"], 'ello'];
        yield 'Alt+B moves word left' => ['Hello World', ["\x1b[F"], ["\x1bb", 'X'], 'Hello XWorld'];
        yield 'Alt+F moves word right' => ['Hello World', ["\x1b[H"], ["\x1bf", 'X'], 'HelloX World'];
    }

    /**
     * Test typing behavior and ensure cursor moves properly.
     *
     * This is a regression test for the bug where the cursor didn't move
     * when typing at the initial position.
     */
    public function testTypingMovesCursor()
    {
        [$editor, $tui] = $this->createEditorWithTui();
        $editor->setFocused(true);

        // Initially, cursor should be at position 0 on an empty line
        $this->assertSame('', $editor->getText());

        // Type 'H' - cursor should move to position 1
        $editor->handleInput('H');
        $this->assertSame('H', $editor->getText());

        // Render and verify output is valid UTF-8
        $lines = $editor->render(new RenderContext(40, 24));
        foreach ($lines as $line) {
            $this->assertValidUtf8($line, 'Rendered line should be valid UTF-8');
        }

        // Type 'e' - cursor should move to position 2
        $editor->handleInput('e');
        $this->assertSame('He', $editor->getText());

        // Type 'l' twice, then 'o'
        $editor->handleInput('l');
        $editor->handleInput('l');
        $editor->handleInput('o');
        $this->assertSame('Hello', $editor->getText());

        // Final render should still be valid
        $lines = $editor->render(new RenderContext(40, 24));
        foreach ($lines as $line) {
            $this->assertValidUtf8($line, 'Final rendered line should be valid UTF-8');
        }
    }

    public function testCursorPositionAfterTypingSpace()
    {
        [$editor, $tui] = $this->createEditorWithTui();
        $editor->setFocused(true);

        $editor->handleInput('H');
        $editor->handleInput('i');
        $this->assertSame('Hi', $editor->getText());

        // Type a space; cursor must advance past it
        $editor->handleInput(' ');
        $this->assertSame('Hi ', $editor->getText());

        $lines = $editor->render(new RenderContext(40, 24));

        // The cursor marker should appear after "Hi " (i.e. at column 3),
        // not after "Hi" (column 2). Find the content line (skip border).
        $contentLine = $lines[1]; // first content line after top border
        $markerPos = strpos($contentLine, AnsiUtils::CURSOR_MARKER_PREFIX);
        $this->assertIsInt($markerPos, 'Cursor marker should be present');

        $beforeMarker = substr($contentLine, 0, $markerPos);
        $this->assertSame(3, AnsiUtils::visibleWidth($beforeMarker), 'Cursor should be at column 3 (after "Hi ")');
    }

    public function testTypingEmojiDirectly()
    {
        [$editor, $tui] = $this->createEditorWithTui();
        $editor->setFocused(true);

        // Type emoji directly (not via paste); this was previously blocked
        // because StringUtils::hasControlChars() treated UTF-8 continuation bytes
        // in the 0x80-0x9F range as C1 control characters
        $editor->handleInput('😀');
        $this->assertSame('😀', $editor->getText());

        // Type more emoji
        $editor->handleInput('🎉');
        $this->assertSame('😀🎉', $editor->getText());

        // Mix text and emoji
        $editor->handleInput(' hi');
        $this->assertSame('😀🎉 hi', $editor->getText());
    }

    public function testTypingWithEmojiThenRegularText()
    {
        [$editor, $tui] = $this->createEditorWithTui();
        $editor->setFocused(true);

        // Set text with emoji directly
        $editor->setText('📝 ');
        $this->assertSame('📝 ', $editor->getText());

        // Type regular text after emoji (move cursor to end first)
        $editor->handleInput("\x05"); // End key
        $editor->handleInput('H');
        $editor->handleInput('i');
        $this->assertSame('📝 Hi', $editor->getText());

        // Verify rendering is valid
        $lines = $editor->render(new RenderContext(40, 24));
        foreach ($lines as $line) {
            $this->assertValidUtf8($line);
        }
    }

    /**
     * Regression: typing a space that fills the line to exactly the terminal
     * width caused the rendered output to exceed the width by 1.
     *
     * The bug was in renderLine(): lineVisibleWidth was computed from rtrim'd
     * text, so trailing spaces were not counted. When the cursor was at the
     * end past a trailing space, the "room for block cursor" check passed
     * incorrectly, adding a styled space on top of the full-width content.
     */
    public function testSpaceAtEndOfFullWidthLineDoesNotExceedWidth()
    {
        $editor = new EditorWidget();
        $editor->setFocused(true);

        $width = 20;

        // Fill line to width-1 with non-space chars, then type a space
        $editor->setText(str_repeat('a', $width - 1));
        $editor->handleInput("\x05"); // End
        $editor->handleInput(' ');

        $this->assertSame(str_repeat('a', $width - 1).' ', $editor->getText());

        $lines = $editor->render(new RenderContext($width, 24));

        foreach ($lines as $i => $line) {
            $lineWidth = AnsiUtils::visibleWidth($line);
            $this->assertLessThanOrEqual(
                $width,
                $lineWidth,
                \sprintf('Line %d exceeds width: %d > %d (space at end of full-width line)', $i, $lineWidth, $width),
            );
        }
    }

    public function testTypingInMiddleOfLine()
    {
        [$editor, $tui] = $this->createEditorWithTui();
        $editor->setFocused(true);

        // Type initial "Hello"
        $editor->handleInput('H');
        $editor->handleInput('e');
        $editor->handleInput('l');
        $editor->handleInput('l');
        $editor->handleInput('o');

        // Move to position after 'H' (position 1)
        // First move to home, then right once
        $editor->handleInput("\x01"); // Home
        $editor->handleInput("\x1b[C"); // Right once (to position 1)

        // Type 3 characters at position 1 (after 'H', before 'e')
        // "Hello" becomes "Hxyzello"
        $editor->handleInput('x');
        $editor->handleInput('y');
        $editor->handleInput('z');
        $this->assertSame('Hxyzello', $editor->getText());

        // Verify rendering
        $lines = $editor->render(new RenderContext(40, 24));
        foreach ($lines as $line) {
            $this->assertValidUtf8($line);
        }
    }

    /**
     * Regression: autocomplete stopped working after deleting characters.
     *
     * When typing "/hx" with no matching slash command, autocomplete closed.
     * Then deleting "x" to get "/h" (which has matches) did NOT re-open
     * autocomplete because notifyChange() only called refresh() (which
     * early-returns when inactive) instead of trying to re-trigger.
     */
    public function testUndoRestoresPreviousState()
    {
        $editor = new EditorWidget();
        $editor->handleInput('H');
        $editor->handleInput('i');
        $this->assertSame('Hi', $editor->getText());

        // Undo (Ctrl+-)
        $editor->handleInput("\x1f");
        $this->assertSame('H', $editor->getText());

        $editor->handleInput("\x1f");
        $this->assertSame('', $editor->getText());
    }

    public function testRedoRestoresUndoneState()
    {
        $editor = new EditorWidget();
        $editor->handleInput('H');
        $editor->handleInput('i');
        $this->assertSame('Hi', $editor->getText());

        // Undo (Ctrl+-)
        $editor->handleInput("\x1f");
        $this->assertSame('H', $editor->getText());

        // Redo (Ctrl+Shift+Z)
        $editor->handleInput("\x1b[122;6u");
        $this->assertSame('Hi', $editor->getText());
    }

    public function testRedoDoesNothingWhenStackIsEmpty()
    {
        $editor = new EditorWidget();
        $editor->handleInput('H');
        $this->assertSame('H', $editor->getText());

        // Redo without prior undo does nothing
        $editor->handleInput("\x1b[122;6u");
        $this->assertSame('H', $editor->getText());
    }

    public function testRedoStackClearedOnNewEdit()
    {
        $editor = new EditorWidget();
        $editor->handleInput('A');
        $editor->handleInput('B');
        $this->assertSame('AB', $editor->getText());

        // Undo
        $editor->handleInput("\x1f");
        $this->assertSame('A', $editor->getText());

        // Type something new; redo stack should be cleared
        $editor->handleInput('C');
        $this->assertSame('AC', $editor->getText());

        // Redo should do nothing now
        $editor->handleInput("\x1b[122;6u");
        $this->assertSame('AC', $editor->getText());
    }

    public function testMultipleUndoAndRedo()
    {
        $editor = new EditorWidget();
        $editor->handleInput('A');
        $editor->handleInput('B');
        $editor->handleInput('C');
        $this->assertSame('ABC', $editor->getText());

        // Undo 3 times
        $editor->handleInput("\x1f");
        $this->assertSame('AB', $editor->getText());
        $editor->handleInput("\x1f");
        $this->assertSame('A', $editor->getText());
        $editor->handleInput("\x1f");
        $this->assertSame('', $editor->getText());

        // Redo 3 times
        $editor->handleInput("\x1b[122;6u");
        $this->assertSame('A', $editor->getText());
        $editor->handleInput("\x1b[122;6u");
        $this->assertSame('AB', $editor->getText());
        $editor->handleInput("\x1b[122;6u");
        $this->assertSame('ABC', $editor->getText());

        // Extra redo does nothing
        $editor->handleInput("\x1b[122;6u");
        $this->assertSame('ABC', $editor->getText());
    }

    public function testUndoRedoWithNewLine()
    {
        $editor = new EditorWidget();
        $editor->handleInput('H');
        $editor->handleInput('i');
        $editor->handleInput("\x1b[F"); // End
        $editor->handleInput("\x1b[13;2u"); // Shift+Enter for new line
        $editor->handleInput('!');
        $this->assertSame("Hi\n!", $editor->getText());

        // Undo the '!'
        $editor->handleInput("\x1f");
        $this->assertSame("Hi\n", $editor->getText());

        // Redo the '!'
        $editor->handleInput("\x1b[122;6u");
        $this->assertSame("Hi\n!", $editor->getText());
    }

    public function testSetTextClearsUndoRedoStacks()
    {
        $editor = new EditorWidget();

        // Build up undo history
        $editor->handleInput('A');
        $editor->handleInput('B');
        $editor->handleInput('C');
        $this->assertSame('ABC', $editor->getText());

        // Undo once to also populate the redo stack
        $editor->handleInput("\x1f");
        $this->assertSame('AB', $editor->getText());

        // Programmatically replace all content
        $editor->setText('New content');
        $this->assertSame('New content', $editor->getText());

        // Undo should do nothing; stack was cleared
        $editor->handleInput("\x1f");
        $this->assertSame('New content', $editor->getText());

        // Redo should do nothing; stack was cleared
        $editor->handleInput("\x1b[122;6u");
        $this->assertSame('New content', $editor->getText());
    }

    public function testLargePasteCreatesMarkerAndGetTextReturnsFullContent()
    {
        $editor = new EditorWidget();
        $content = $this->generateLargeContent(15);

        $this->simulatePaste($editor, $content);

        // getText() should return the full paste content
        $this->assertSame($content, $editor->getText());

        // A marker should have been created
        $markers = $editor->getPasteMarkers();
        $this->assertCount(1, $markers);
        $this->assertSame($content, $markers[0]['content']);
    }

    public function testSmallPasteDoesNotCreateMarker()
    {
        $editor = new EditorWidget();
        $content = "line 1\nline 2\nline 3";

        $this->simulatePaste($editor, $content);

        $this->assertSame($content, $editor->getText());
        $this->assertSame([], $editor->getPasteMarkers());
    }

    public function testPasteMarkerCollisionWithUserText()
    {
        $editor = new EditorWidget();

        // First, do a large paste
        $pasteContent = $this->generateLargeContent(15);
        $this->simulatePaste($editor, $pasteContent);

        // Get the marker that was created
        $markers = $editor->getPasteMarkers();
        $this->assertCount(1, $markers);
        $markerText = $markers[0]['marker'];

        // Now create a second editor where user types a string that looks like
        // a paste marker (but without the random ID)
        $editor2 = new EditorWidget();
        $editor2->handleInput('[paste #1 +15 lines]');

        // The user-typed text should remain as-is, not be replaced
        $this->assertSame('[paste #1 +15 lines]', $editor2->getText());

        // The actual marker contains a random hex ID, so it's different
        $this->assertStringContainsString('<', $markerText);
        $this->assertStringContainsString('>', $markerText);
    }

    public function testPasteMarkerUniqueness()
    {
        $editor = new EditorWidget();
        $content1 = $this->generateLargeContent(12);
        $content2 = $this->generateLargeContent(12);

        $this->simulatePaste($editor, $content1);
        $this->simulatePaste($editor, $content2);

        $markers = $editor->getPasteMarkers();
        $this->assertCount(2, $markers);

        // Each marker should have a unique string
        $this->assertNotSame($markers[0]['marker'], $markers[1]['marker']);
    }

    public function testPasteContentContainingMarkerLikeText()
    {
        $editor = new EditorWidget();

        // First paste: content that looks like a marker
        $maliciousContent = implode("\n", array_fill(0, 12, '[paste #2 +12 lines]'));
        $this->simulatePaste($editor, $maliciousContent);

        // Second paste
        $normalContent = $this->generateLargeContent(12);
        $this->simulatePaste($editor, $normalContent);

        // getText() should correctly expand both markers without chaining
        $text = $editor->getText();
        $this->assertStringContainsString($maliciousContent, $text);
        $this->assertStringContainsString($normalContent, $text);
    }

    public function testSetTextClearsPasteMarkers()
    {
        $editor = new EditorWidget();

        // Create a large paste
        $content = $this->generateLargeContent(15);
        $this->simulatePaste($editor, $content);
        $this->assertNotSame([], $editor->getPasteMarkers());

        // setText() should clear paste markers
        $editor->setText('new content');
        $this->assertSame([], $editor->getPasteMarkers());
        $this->assertSame('new content', $editor->getText());
    }

    public function testMultipleLargePastesExpandCorrectly()
    {
        $editor = new EditorWidget();

        $content1 = $this->generateLargeContent(11, 'alpha');
        $this->simulatePaste($editor, $content1);

        // Add a newline between pastes
        $editor->handleInput("\x1b[13;2u"); // Shift+Enter

        $content2 = $this->generateLargeContent(13, 'beta');
        $this->simulatePaste($editor, $content2);

        $text = $editor->getText();
        $this->assertStringContainsString($content1, $text);
        $this->assertStringContainsString($content2, $text);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function largePasteLineEndingProvider(): iterable
    {
        yield 'Windows \\r\\n' => ["\r\n"];
        yield 'old Mac \\r' => ["\r"];
    }

    #[DataProvider('largePasteLineEndingProvider')]
    public function testLargePasteNormalizesLineEndings(string $separator)
    {
        $editor = new EditorWidget();

        $lines = [];
        for ($i = 1; $i <= 12; ++$i) {
            $lines[] = "line $i";
        }
        $content = implode($separator, $lines);

        $this->simulatePaste($editor, $content);

        $text = $editor->getText();
        $this->assertStringNotContainsString("\r", $text);
        $this->assertSame(implode("\n", $lines), $text);
    }

    public function testRenderResolvesElementStylesOncePerPass()
    {
        $counter = new \stdClass();
        $counter->calls = [];

        $stylesheet = new class($counter) extends StyleSheet {
            public function __construct(
                private readonly \stdClass $counter,
            ) {
                parent::__construct();
            }

            public function resolveElement(AbstractWidget $widget, string $element): Style
            {
                $this->counter->calls[] = $element;

                return parent::resolveElement($widget, $element);
            }
        };

        $terminal = new VirtualTerminal(80, 24);
        $tui = new Tui(styleSheet: $stylesheet, terminal: $terminal);
        $editor = new EditorWidget();
        $tui->add($editor);
        $editor->setFocused(true);

        // Set up multiline content with cursor on a line that wraps
        $editor->setText("Line 1\nLine 2\nLine 3\nLine 4\nLine 5");

        // Reset counter after setup
        $counter->calls = [];

        // Render
        $editor->render(new RenderContext(40, 24));

        // resolveElement should be called exactly twice: once for 'cursor', once for 'frame'
        $this->assertSame(['cursor', 'frame'], $counter->calls);
    }

    public function testPageScrollRespectsMaxVisibleLines()
    {
        $terminal = new VirtualTerminal(80, 24);
        $tui = new Tui(terminal: $terminal);
        $editor = new EditorWidget();
        $tui->add($editor);

        // Set maxVisibleLines to 3
        $editor->setMaxVisibleLines(3);

        // Create 20 lines
        $lines = [];
        for ($i = 0; $i < 20; ++$i) {
            $lines[] = "Line $i";
        }
        $editor->setText(implode("\n", $lines));
        $editor->handleInput("\x1b[H"); // Home - cursor at line 0

        // Render so that lastMaxVisibleLines is computed (should be 3)
        $editor->render(new RenderContext(80, 24));

        // Page down should move by maxVisibleLines (3), not 30% of 24 (7)
        $editor->handleInput("\x1b[6~"); // Page Down

        // Cursor should be at line 3
        $editor->handleInput("\x1b[H"); // Home
        $editor->handleInput('X');

        $text = $editor->getText();
        $resultLines = explode("\n", $text);
        $this->assertSame('XLine 3', $resultLines[3]);
    }

    public function testPageScrollRespectsVerticallyExpandedMode()
    {
        $terminal = new VirtualTerminal(80, 40);
        $tui = new Tui(terminal: $terminal);
        $editor = new EditorWidget();
        $tui->add($editor);

        $editor->expandVertically(true);

        // Create 100 lines
        $lines = [];
        for ($i = 0; $i < 100; ++$i) {
            $lines[] = "Line $i";
        }
        $editor->setText(implode("\n", $lines));
        $editor->handleInput("\x1b[H"); // Home

        // Render with 40 rows context (fill mode: maxVisibleLines = 40 - 2 = 38)
        $editor->render(new RenderContext(80, 40));

        // Page down should move by 38, not 30% of 40 (12)
        $editor->handleInput("\x1b[6~"); // Page Down

        $editor->handleInput("\x1b[H"); // Home
        $editor->handleInput('X');

        $text = $editor->getText();
        $resultLines = explode("\n", $text);
        $this->assertSame('XLine 38', $resultLines[38]);
    }

    #[DataProvider('lineEndingNormalizationProvider')]
    public function testSetTextNormalizesLineEndings(string $input, string $expected)
    {
        $editor = new EditorWidget();
        $editor->setText($input);

        $this->assertSame($expected, $editor->getText());
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function lineEndingNormalizationProvider(): iterable
    {
        yield 'Windows \\r\\n' => ["Line 1\r\nLine 2\r\nLine 3", "Line 1\nLine 2\nLine 3"];
        yield 'Old Mac \\r' => ["Line 1\rLine 2\rLine 3", "Line 1\nLine 2\nLine 3"];
        yield 'Mixed endings' => ["Line 1\r\nLine 2\rLine 3\nLine 4", "Line 1\nLine 2\nLine 3\nLine 4"];
    }

    public function testTypingAfterSetTextWithCarriageReturn()
    {
        $editor = new EditorWidget();
        $editor->setText("Hello\r\nWorld");

        // Move to end of first line and type
        $editor->handleInput("\x1b[F"); // End
        $editor->handleInput('!');

        $this->assertSame("Hello!\nWorld", $editor->getText());
    }

    #[DataProvider('pasteLineEndingNormalizationProvider')]
    public function testPasteNormalizesLineEndings(string $pasteContent)
    {
        $editor = new EditorWidget();
        $editor->handleInput("\x1b[200~".$pasteContent."\x1b[201~");

        $this->assertSame("Line 1\nLine 2\nLine 3", $editor->getText());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function pasteLineEndingNormalizationProvider(): iterable
    {
        yield 'Windows \\r\\n' => ["Line 1\r\nLine 2\r\nLine 3"];
        yield 'Old Mac \\r' => ["Line 1\rLine 2\rLine 3"];
    }

    public function testPageScrollFallsBackBeforeFirstRender()
    {
        $terminal = new VirtualTerminal(80, 24);
        $tui = new Tui(terminal: $terminal);
        $editor = new EditorWidget();
        $tui->add($editor);

        // Create 50 lines
        $lines = [];
        for ($i = 0; $i < 50; ++$i) {
            $lines[] = "Line $i";
        }
        $editor->setText(implode("\n", $lines));
        $editor->handleInput("\x1b[H"); // Home

        // Page down WITHOUT rendering first; should use fallback (30% of 24 = 7)
        $editor->handleInput("\x1b[6~"); // Page Down

        $editor->handleInput("\x1b[H"); // Home
        $editor->handleInput('X');

        $text = $editor->getText();
        $resultLines = explode("\n", $text);
        $this->assertSame('XLine 7', $resultLines[7]);
    }

    public function testRenderWithWrappingLinesRespectsDisplayRowBudget()
    {
        $editor = new EditorWidget();
        $editor->expandVertically(true);

        // Create lines that each wrap to 2 display rows in 30 columns
        $content = [];
        for ($i = 0; $i < 20; ++$i) {
            $content[] = "Line $i: This is a long line that will definitely wrap.";
        }
        $editor->setText(implode("\n", $content));

        // Render with 12 rows available (10 display rows for content + 2 borders)
        $lines = $editor->render(new RenderContext(30, 12));

        // Total output should not exceed 12 rows (the allocated context height)
        $this->assertLessThanOrEqual(12, \count($lines), 'Editor must not exceed allocated display rows when lines wrap');

        // Should have top border + content + bottom border
        $firstLine = AnsiUtils::stripAnsiCodes($lines[0]);
        $lastLine = AnsiUtils::stripAnsiCodes($lines[\count($lines) - 1]);
        $this->assertStringContainsString('─', $firstLine, 'First line should be top border');
        $this->assertStringContainsString('─', $lastLine, 'Last line should be bottom border');

        // The bottom border should show "more" indicator since we can't fit all 20 lines
        $this->assertStringContainsString('more', $lastLine, 'Should show scroll indicator when content overflows');
    }

    public function testScrollWithWrappingLinesKeepsCursorVisible()
    {
        $terminal = new VirtualTerminal(50, 15);
        $renderer = new Renderer();
        $tui = new Tui(terminal: $terminal, renderer: $renderer);

        $editor = new EditorWidget();
        $editor->expandVertically(true);

        // Lines that wrap in 48 columns (50 - 2 padding)
        $content = [];
        for ($i = 0; $i < 20; ++$i) {
            $content[] = "Line $i: This text is long enough to wrap in the editor.";
        }
        $editor->setText(implode("\n", $content));
        $tui->add($editor);
        $tui->setFocus($editor);
        $tui->start();
        $tui->processRender();

        // Navigate down past the visible area
        for ($i = 0; $i < 10; ++$i) {
            $terminal->simulateInput("\x1b[B"); // Down arrow
            $tui->processRender();
        }

        // Type a marker to verify cursor is at logical line 10
        $editor->handleInput('X');
        $lines = explode("\n", $editor->getText());
        $this->assertStringStartsWith('XLine 10:', $lines[10], 'Cursor should be at the beginning of line 10');
        // Undo the marker to restore original state
        $editor->handleInput("\x1f");

        // Render and verify scrolling happened (Line 0 should not be visible)
        $root = new ContainerWidget();
        $root->add($editor);
        $editor->invalidate();
        $result = $renderer->render($root, 50, 15);
        $allContent = implode("\n", $result);
        $this->assertStringNotContainsString('Line 0:', $allContent, 'Scroll offset should advance when cursor moves past visible area with wrapping lines');

        // Verify the rendered output doesn't exceed the allocated height
        $this->assertLessThanOrEqual(15, \count($result), 'Total render should not exceed terminal height');

        $tui->stop();
    }

    private function assertValidUtf8(string $value, string $message = ''): void
    {
        $this->assertTrue(mb_check_encoding($value, 'UTF-8'), $message ?: 'Value should be valid UTF-8');
    }

    /**
     * @return array{EditorWidget, Tui}
     */
    private function createEditorWithTui(): array
    {
        $terminal = new VirtualTerminal(80, 24);
        $tui = new Tui(terminal: $terminal);
        $editor = new EditorWidget();
        $tui->add($editor);

        return [$editor, $tui];
    }

    /**
     * Helper to simulate a bracketed paste into the editor.
     */
    private function simulatePaste(EditorWidget $editor, string $content): void
    {
        $editor->handleInput("\x1b[200~".$content."\x1b[201~");
    }

    /**
     * Generate a large paste content with the given number of lines.
     */
    private function generateLargeContent(int $lineCount, string $prefix = 'line'): string
    {
        $lines = [];
        for ($i = 1; $i <= $lineCount; ++$i) {
            $lines[] = "$prefix $i content";
        }

        return implode("\n", $lines);
    }
}
