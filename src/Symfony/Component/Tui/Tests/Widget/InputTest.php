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
use Symfony\Component\Tui\Event\CancelEvent;
use Symfony\Component\Tui\Event\ChangeEvent;
use Symfony\Component\Tui\Event\SubmitEvent;
use Symfony\Component\Tui\Render\RenderContext;
use Symfony\Component\Tui\Terminal\VirtualTerminal;
use Symfony\Component\Tui\Tui;
use Symfony\Component\Tui\Widget\InputWidget;

class InputTest extends TestCase
{
    public function testRenderEmpty()
    {
        $input = new InputWidget();
        $lines = $input->render(new RenderContext(40, 24));

        $this->assertCount(1, $lines);
        $this->assertStringContainsString('>', $lines[0]);
    }

    public function testRenderWithEmptyPrompt()
    {
        $input = new InputWidget();
        $input->setPrompt('');
        $input->setValue('Hello');
        $lines = $input->render(new RenderContext(40, 24));

        $this->assertCount(1, $lines);
        // Full width available for text, no prompt prefix
        $this->assertSame(40, AnsiUtils::visibleWidth($lines[0]));
        $stripped = AnsiUtils::stripAnsiCodes($lines[0]);
        $this->assertStringNotContainsString('> ', substr($stripped, 0, 2));
    }

    /**
     * Regression: prompt width was calculated with strlen(), which returns
     * byte count instead of visible column width, breaking layout for
     * multi-byte prompts.
     *
     * @see https://github.com/fabpot/into-the-void/issues/280
     */
    #[DataProvider('promptRenderingProvider')]
    public function testRenderWithPrompt(string $prompt, string $value)
    {
        $input = new InputWidget();
        $input->setPrompt($prompt);
        $input->setValue($value);
        $lines = $input->render(new RenderContext(40, 24));

        $this->assertCount(1, $lines);
        $this->assertSame(40, AnsiUtils::visibleWidth($lines[0]));
        $this->assertStringContainsString($value, AnsiUtils::stripAnsiCodes($lines[0]));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function promptRenderingProvider(): iterable
    {
        yield 'plain text' => ['Email: ', 'test@example.com'];
        yield 'emoji (multi-byte width)' => ['🔍 ', 'search term'];
        yield 'ANSI styled' => ["\033[1m> \033[0m", 'styled'];
        yield 'CJK characters' => ['検索: ', 'query'];
    }

    public function testRenderWithValue()
    {
        $input = new InputWidget();
        $input->setValue('Hello');
        $lines = $input->render(new RenderContext(40, 24));

        // The value is rendered with the first char in inverse video (cursor)
        // So we check for "ello" (rest of word) and the line contains the value
        $this->assertStringContainsString('ello', $lines[0]);
    }

    public function testTypeCharacter()
    {
        $input = new InputWidget();
        $input->handleInput('H');
        $input->handleInput('i');

        $this->assertSame('Hi', $input->getValue());
    }

    public function testBackspace()
    {
        $input = new InputWidget();
        $input->setValue('Hello');
        // Move cursor to end first (Ctrl+E)
        $input->handleInput("\x05");
        $input->handleInput("\x7f"); // Backspace

        $this->assertSame('Hell', $input->getValue());
    }

    public function testOnSubmitCallback()
    {
        [$input, $tui] = $this->createInputWithTui();

        $submitted = null;
        $tui->addListener(static function (SubmitEvent $e) use (&$submitted) {
            $submitted = $e->getValue();
        });

        $input->setValue('test');
        $input->handleInput("\r"); // Enter

        $this->assertSame('test', $submitted);
    }

    public function testOnCancelCallback()
    {
        [$input, $tui] = $this->createInputWithTui();

        $cancelled = false;
        $tui->addListener(static function (CancelEvent $e) use (&$cancelled) {
            $cancelled = true;
        });

        $input->handleInput("\x1b"); // Escape

        $this->assertTrue($cancelled);
    }

    public function testOnInputCallbackConsumesEvent()
    {
        $input = new InputWidget();

        $intercepted = false;
        $input->onInput(static function (string $data) use (&$intercepted): bool {
            if ('x' === $data) {
                $intercepted = true;

                return true;
            }

            return false;
        });

        $input->handleInput('x');
        $this->assertTrue($intercepted);
        $this->assertSame('', $input->getValue(), 'Consumed input should not be typed');
    }

    public function testOnInputCallbackPassesThrough()
    {
        $input = new InputWidget();

        $input->onInput(static fn (string $data): bool => 'x' === $data);

        $input->handleInput('y');
        $this->assertSame('y', $input->getValue(), 'Non-consumed input should be typed');
    }

    public function testCursorMovement()
    {
        $input = new InputWidget();
        $input->setValue('Hello');

        // Move to start
        $input->handleInput("\x01"); // Ctrl+A

        // Type at start
        $input->handleInput('X');

        $this->assertSame('XHello', $input->getValue());
    }

    public function testDeleteWordBackwardWithAltBackspace()
    {
        $input = new InputWidget();
        $input->setValue('hello world');
        // Move cursor to end
        $input->handleInput("\x05"); // Ctrl+E
        // Alt+Backspace (legacy: ESC + DEL)
        $input->handleInput("\x1b\x7f");

        $this->assertSame('hello ', $input->getValue());
    }

    public function testDeleteWordBackwardWithCtrlW()
    {
        $input = new InputWidget();
        $input->setValue('hello world');
        // Move cursor to end
        $input->handleInput("\x05"); // Ctrl+E
        // Ctrl+W
        $input->handleInput("\x17");

        $this->assertSame('hello ', $input->getValue());
    }

    public function testDeleteToEnd()
    {
        $input = new InputWidget();
        $input->setValue('Hello World');

        // Move to position 5 (after "Hello")
        $input->handleInput("\x01"); // Start
        for ($i = 0; $i < 5; ++$i) {
            $input->handleInput("\x1b[C"); // Right arrow
        }

        // Delete to end
        $input->handleInput("\x0b"); // Ctrl+K

        $this->assertSame('Hello', $input->getValue());
    }

    public function testRenderLineWidth()
    {
        $input = new InputWidget();
        $input->setValue('Some text');
        $width = 40;
        $lines = $input->render(new RenderContext($width, 24));

        $this->assertSame($width, AnsiUtils::visibleWidth($lines[0]));
    }

    public function testFocusable()
    {
        $input = new InputWidget();

        $this->assertFalse($input->isFocused());

        $input->setFocused(true);
        $this->assertTrue($input->isFocused());

        $input->setFocused(false);
        $this->assertFalse($input->isFocused());
    }

    public function testOnChangeCallback()
    {
        [$input, $tui] = $this->createInputWithTui();

        $changedValue = null;
        $tui->addListener(static function (ChangeEvent $e) use (&$changedValue) {
            $changedValue = $e->getValue();
        });

        $input->handleInput('X');

        $this->assertSame('X', $changedValue);
    }

    /**
     * UTF-8 REGRESSION TESTS - Prevent invalid multi-byte character handling.
     * These tests ensure that cursor positioning and text manipulation
     * always work with complete graphemes, not byte boundaries.
     *
     * @see https://github.com/fabpot/into-the-void/issues/XXX
     */
    public function testUtf8BackspaceAfterSetValue()
    {
        // Regression: setValue() was using min() for cursor, keeping it at 0
        $input = new InputWidget();
        $input->setValue('café');
        $input->handleInput("\x7f"); // Backspace

        $this->assertSame('caf', $input->getValue());
        // Verify no invalid UTF-8
        $this->assertValidUtf8($input->getValue(), 'Value should be valid UTF-8');
    }

    public function testUtf8CursorMovementLeft()
    {
        // Regression: moveCursorLeft used byte decrement, could land mid-character
        $input = new InputWidget();
        $input->setValue('café');
        $input->handleInput("\x1b[D"); // Left arrow
        $input->handleInput("\x7f");   // Backspace (delete char before cursor)

        $this->assertSame('caé', $input->getValue());
    }

    public function testUtf8MultipleBackspaces()
    {
        $input = new InputWidget();
        $input->setValue('café');

        // Delete all characters one by one
        $input->handleInput("\x7f");
        $this->assertSame('caf', $input->getValue());

        $input->handleInput("\x7f");
        $this->assertSame('ca', $input->getValue());

        $input->handleInput("\x7f");
        $this->assertSame('c', $input->getValue());

        $input->handleInput("\x7f");
        $this->assertSame('', $input->getValue());
    }

    public function testUtf8TypeMultibyte()
    {
        $input = new InputWidget();
        $input->handleInput('h');
        $input->handleInput('i');
        $input->handleInput('é');
        $input->handleInput('s');

        $this->assertSame('hiés', $input->getValue());
    }

    /**
     * @return iterable<string, array{list<string>, string}>
     */
    public static function utf8DeleteRangeProvider(): iterable
    {
        yield 'delete to start from middle' => [["\x01", "\x1b[C", "\x1b[C", "\x15"], 'fé'];
        yield 'delete to end from start' => [["\x01", "\x0b"], ''];
    }

    /**
     * @param list<string> $inputs
     */
    #[DataProvider('utf8DeleteRangeProvider')]
    public function testUtf8DeleteRange(array $inputs, string $expected)
    {
        $input = new InputWidget();
        $input->setValue('café');

        foreach ($inputs as $key) {
            $input->handleInput($key);
        }

        $this->assertSame($expected, $input->getValue());
    }

    public function testUtf8WordMovement()
    {
        $input = new InputWidget();
        $input->setValue('hello café');
        // Move to end
        $input->handleInput("\x05"); // Ctrl+E (end)

        // Delete to start should delete everything
        $input->handleInput("\x15"); // Ctrl+U (delete to start)
        $this->assertSame('', $input->getValue());
    }

    public function testUtf8ControlCharactersIgnored()
    {
        // Verify that only printable characters are inserted
        $input = new InputWidget();
        $input->handleInput('h');
        $input->handleInput('e');
        $input->handleInput("\x00"); // NULL byte - should be ignored
        $input->handleInput('l');

        $this->assertSame('hel', $input->getValue());
    }

    #[DataProvider('utf8DeletionProvider')]
    public function testUtf8Deletion(string $initial, string $expected)
    {
        $widget = new InputWidget();
        $widget->setValue($initial);
        $widget->handleInput("\x7f");
        $this->assertSame($expected, $widget->getValue());
        $this->assertValidUtf8($widget->getValue());
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function utf8DeletionProvider(): iterable
    {
        yield 'latin accent' => ['café', 'caf'];
        yield 'multiple accents' => ['café naïve', 'café naïv'];
        yield 'emoji' => ['hello👋', 'hello'];
        yield 'japanese' => ['日本', '日'];
        yield 'chinese' => ['中文', '中'];
        yield 'korean' => ['한글', '한'];
        yield 'mixed latin+emoji' => ['hi👋bye', 'hi👋by'];
        yield 'mixed scripts' => ['café日本', 'café日'];
    }

    /**
     * Regression: render() used single-byte array access to extract the cursor
     * character, splitting multi-byte UTF-8 characters.
     *
     * @see https://github.com/fabpot/into-the-void/issues/268
     */
    public function testRenderDoesNotSplitMultiByteCharacterAtCursor()
    {
        $input = new InputWidget();
        $input->setValue('café');

        // Cursor is at end (byte offset 5), move left to land on 'é'
        $input->handleInput("\x1b[D"); // Left arrow

        $lines = $input->render(new RenderContext(40, 24));
        $stripped = AnsiUtils::stripAnsiCodes($lines[0]);

        // The rendered line must contain valid UTF-8 (no broken bytes)
        $this->assertValidUtf8($stripped, 'Rendered line contains invalid UTF-8');
        // 'é' should appear intact, not split into broken bytes
        $this->assertStringContainsString('é', $stripped);
    }

    /**
     * @see https://github.com/fabpot/into-the-void/issues/268
     */
    public function testRenderDoesNotSplitEmojiAtCursor()
    {
        $input = new InputWidget();
        $input->setValue('hi👋');

        // Cursor is at end, move left to land on the emoji
        $input->handleInput("\x1b[D"); // Left arrow

        $lines = $input->render(new RenderContext(40, 24));
        $stripped = AnsiUtils::stripAnsiCodes($lines[0]);

        $this->assertValidUtf8($stripped, 'Rendered line contains invalid UTF-8');
        $this->assertStringContainsString('👋', $stripped);
    }

    public function testRenderScrollingWithCJKCharacters()
    {
        $input = new InputWidget();
        // CJK characters are 2 columns wide each; 10 chars = 20 columns display width
        $input->setValue('日本語漢字中文韓國語言');

        // Render with width 12 (prompt "> " = 2 cols, available = 10)
        // Only 5 CJK chars fit (5 × 2 = 10 columns)
        $lines = $input->render(new RenderContext(12, 24));
        $stripped = AnsiUtils::stripAnsiCodes($lines[0]);

        $this->assertValidUtf8($stripped, 'Rendered line contains invalid UTF-8');
        $this->assertSame(12, AnsiUtils::visibleWidth($lines[0]));
    }

    public function testRenderWithPromptWiderThanAvailableColumns()
    {
        // When prompt is wider than available columns, it should be truncated
        // to the column width, not returned verbatim.
        $input = new InputWidget();
        $input->setPrompt('This prompt is way too long to fit: ');
        $input->setValue('hidden');

        $columns = 10;
        $lines = $input->render(new RenderContext($columns, 24));

        $this->assertCount(1, $lines);
        $this->assertSame($columns, AnsiUtils::visibleWidth($lines[0]));
        // The truncated prompt should be a prefix of the original
        $stripped = AnsiUtils::stripAnsiCodes($lines[0]);
        $this->assertStringStartsWith('This', $stripped);
    }

    public function testRenderWithPromptWiderThanAvailableColumnsEmptyPrompt()
    {
        $input = new InputWidget();
        $input->setPrompt('');
        $input->setValue('hello');

        $lines = $input->render(new RenderContext(5, 24));

        $this->assertCount(1, $lines);
        $this->assertSame(5, AnsiUtils::visibleWidth($lines[0]));
    }

    public function testRenderScrollingWithMixedWidthCharacters()
    {
        $input = new InputWidget();
        // Mix of ASCII (1 col), accented (1 col), CJK (2 col), emoji (2 col)
        $input->setValue('café日本👋hello世界🎉test');

        // Move cursor to middle
        $input->handleInput("\x01"); // Home
        for ($i = 0; $i < 6; ++$i) {
            $input->handleInput("\x1b[C"); // Right arrow
        }

        $lines = $input->render(new RenderContext(15, 24));
        $stripped = AnsiUtils::stripAnsiCodes($lines[0]);

        $this->assertValidUtf8($stripped, 'Rendered line contains invalid UTF-8');
        $this->assertLessThanOrEqual(15, AnsiUtils::visibleWidth($lines[0]));
    }

    private function assertValidUtf8(string $value, string $message = ''): void
    {
        $this->assertTrue(mb_check_encoding($value, 'UTF-8'), $message ?: 'Value should be valid UTF-8');
    }

    /**
     * @return array{InputWidget, Tui}
     */
    private function createInputWithTui(): array
    {
        $terminal = new VirtualTerminal(80, 24);
        $tui = new Tui(terminal: $terminal);
        $input = new InputWidget();
        $tui->add($input);

        return [$input, $tui];
    }
}
