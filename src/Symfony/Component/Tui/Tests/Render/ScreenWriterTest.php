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
use Symfony\Component\Tui\Ansi\AnsiUtils;
use Symfony\Component\Tui\Exception\RenderException;
use Symfony\Component\Tui\Render\ScreenWriter;
use Symfony\Component\Tui\Terminal\VirtualTerminal;

class ScreenWriterTest extends TestCase
{
    private const SYNC_START = "\x1b[?2026h";
    private const SYNC_END = "\x1b[?2026l";
    private const CLEAR_SCREEN = "\x1b[2J\x1b[3J\x1b[H";
    private const CLEAR_LINE = "\x1b[2K";
    private const HIDE_CURSOR = "\x1b[?25l";
    private const SHOW_CURSOR = "\x1b[?25h";

    // --- Full render (initial) ---

    public function testFirstRenderWritesAllLines()
    {
        $terminal = new VirtualTerminal(80, 24);
        $writer = new ScreenWriter($terminal);

        $writer->writeLines(['Line 1', 'Line 2', 'Line 3']);

        $output = $terminal->getOutput();

        // Should use synchronized output
        $this->assertStringContainsString(self::SYNC_START, $output);
        $this->assertStringContainsString(self::SYNC_END, $output);

        // Should NOT clear screen on first render
        $this->assertStringNotContainsString(self::CLEAR_SCREEN, $output);

        // All lines should be present
        $this->assertStringContainsString('Line 1', $output);
        $this->assertStringContainsString('Line 2', $output);
        $this->assertStringContainsString('Line 3', $output);

        // Plain text lines should be separated by \r\n without reset
        $this->assertStringContainsString("Line 1\r\nLine 2", $output);
    }

    public function testFirstRenderWithEmptyLines()
    {
        $terminal = new VirtualTerminal(80, 24);
        $writer = new ScreenWriter($terminal);

        $writer->writeLines([]);

        $output = $terminal->getOutput();

        // Synchronized output should still be written
        $this->assertStringContainsString(self::SYNC_START, $output);
        $this->assertStringContainsString(self::SYNC_END, $output);
    }

    // --- No-op (identical lines produce no output) ---

    public function testIdenticalLinesProduceNoContentOutput()
    {
        $terminal = new VirtualTerminal(80, 24);
        $writer = new ScreenWriter($terminal);

        $writer->writeLines(['Hello', 'World']);

        $terminal->clearOutput();

        // Write the exact same lines again
        $writer->writeLines(['Hello', 'World']);

        $output = $terminal->getOutput();

        // Should only contain the hide cursor call, no sync markers or content
        $this->assertStringNotContainsString(self::SYNC_START, $output);
        $this->assertStringNotContainsString('Hello', $output);
        $this->assertStringNotContainsString('World', $output);
    }

    // --- Differential render ---

    public function testOnlyChangedLinesAreRewritten()
    {
        $terminal = new VirtualTerminal(80, 24);
        $writer = new ScreenWriter($terminal);

        $writer->writeLines(['Line A', 'Line B', 'Line C']);

        $terminal->clearOutput();

        // Change only the middle line
        $writer->writeLines(['Line A', 'Line X', 'Line C']);

        $output = $terminal->getOutput();

        // Should contain the changed line
        $this->assertStringContainsString('Line X', $output);

        // Should NOT re-write unchanged lines
        $this->assertStringNotContainsString('Line A', $output);
        $this->assertStringNotContainsString('Line C', $output);

        // Should use sync markers
        $this->assertStringContainsString(self::SYNC_START, $output);
        $this->assertStringContainsString(self::SYNC_END, $output);
    }

    public function testDifferentialRenderMultipleChangedLines()
    {
        $terminal = new VirtualTerminal(80, 24);
        $writer = new ScreenWriter($terminal);

        $writer->writeLines(['A', 'B', 'C', 'D', 'E']);

        $terminal->clearOutput();

        // Change lines B and D
        $writer->writeLines(['A', 'X', 'C', 'Y', 'E']);

        $output = $terminal->getOutput();

        $this->assertStringContainsString('X', $output);
        $this->assertStringContainsString('Y', $output);
        $this->assertStringNotContainsString('A'.AnsiUtils::SEGMENT_RESET, $output);
        $this->assertStringNotContainsString('E'.AnsiUtils::SEGMENT_RESET, $output);
    }

    // --- Content shrink (deleted lines are cleared) ---

    public function testContentShrinkClearsExtraLines()
    {
        $terminal = new VirtualTerminal(80, 24);
        $writer = new ScreenWriter($terminal);

        $writer->writeLines(['Line 1', 'Line 2', 'Line 3', 'Line 4']);

        $terminal->clearOutput();

        // Shrink to 2 lines (same first 2 lines)
        $writer->writeLines(['Line 1', 'Line 2']);

        $output = $terminal->getOutput();

        // Should clear the deleted lines
        $this->assertStringContainsString(self::CLEAR_LINE, $output);
    }

    public function testContentShrinkWithChangedLines()
    {
        $terminal = new VirtualTerminal(80, 24);
        $writer = new ScreenWriter($terminal);

        $writer->writeLines(['A', 'B', 'C', 'D']);

        $terminal->clearOutput();

        // Change B and remove C,D
        $writer->writeLines(['A', 'X']);

        $output = $terminal->getOutput();

        $this->assertStringContainsString('X', $output);
        // Extra lines should be cleared
        $this->assertStringContainsString(self::CLEAR_LINE, $output);
    }

    public function testContentShrinkRewritesShiftedTrailingLines()
    {
        $terminal = new VirtualTerminal(80, 24);
        $writer = new ScreenWriter($terminal);

        $writer->writeLines(['A', 'B', 'C', 'D']);

        $terminal->clearOutput();

        // Removing a middle line shifts the remaining tail upward, so the
        // shifted trailing lines must still be rewritten.
        $writer->writeLines(['A', 'C', 'D']);

        $output = $terminal->getOutput();

        $this->assertStringContainsString('C', $output);
        $this->assertStringContainsString('D', $output);
        $this->assertStringContainsString(self::CLEAR_LINE, $output);
    }

    public function testContentShrinkWithOnlyDeletedLines()
    {
        $terminal = new VirtualTerminal(80, 24);
        $writer = new ScreenWriter($terminal);

        $writer->writeLines(['A', 'B', 'C']);

        $terminal->clearOutput();

        // Keep the same lines but remove last one
        $writer->writeLines(['A', 'B']);

        $output = $terminal->getOutput();

        // The shrink handler should clear the deleted line
        $this->assertStringContainsString(self::CLEAR_LINE, $output);
    }

    /**
     * @return iterable<string, array{list<string>, int}>
     */
    public static function contentShrinkToEmptyProvider(): iterable
    {
        yield 'three lines to empty' => [['Line 1', 'Line 2', 'Line 3'], 3];
        yield 'one line to empty' => [['Only line'], 1];
    }

    /**
     * @param list<string> $initialLines
     */
    #[DataProvider('contentShrinkToEmptyProvider')]
    public function testContentShrinkToEmptyClearsAllLines(array $initialLines, int $expectedClears)
    {
        $terminal = new VirtualTerminal(80, 24);
        $writer = new ScreenWriter($terminal);

        $writer->writeLines($initialLines);

        $terminal->clearOutput();

        $writer->writeLines([]);

        $output = $terminal->getOutput();

        $this->assertSame($expectedClears, substr_count($output, self::CLEAR_LINE));
    }

    public function testMassiveShrinkTriggersFullRender()
    {
        $terminal = new VirtualTerminal(80, 5); // small terminal
        $writer = new ScreenWriter($terminal);

        // Build many lines (more than terminal height)
        $lines = [];
        for ($i = 0; $i < 10; ++$i) {
            $lines[] = "Line $i";
        }
        $writer->writeLines($lines);

        $terminal->clearOutput();

        // Shrink to just the first line (unchanged) - all changes are in deleted lines
        // Extra lines (9) exceed terminal height (5), triggering full render
        $writer->writeLines(['Line 0']);

        $output = $terminal->getOutput();

        // Should trigger a full render with clear
        $this->assertStringContainsString(self::CLEAR_SCREEN, $output);
    }

    // --- Content grow (new lines are appended) ---

    public function testContentGrowAppendsNewLines()
    {
        $terminal = new VirtualTerminal(80, 24);
        $writer = new ScreenWriter($terminal);

        $writer->writeLines(['A', 'B']);

        $terminal->clearOutput();

        // Add more lines
        $writer->writeLines(['A', 'B', 'C', 'D']);

        $output = $terminal->getOutput();

        // New lines should appear
        $this->assertStringContainsString('C', $output);
        $this->assertStringContainsString('D', $output);
    }

    // --- Width change triggers full re-render ---

    public function testWidthChangeTriggersFullReRender()
    {
        $terminal = new VirtualTerminal(80, 24);
        $writer = new ScreenWriter($terminal);

        $writer->writeLines(['Hello', 'World']);

        // Simulate resize
        $terminal->simulateResize(100, 24);
        $terminal->clearOutput();

        $writer->writeLines(['Hello', 'World']);

        $output = $terminal->getOutput();

        // Width change should trigger full re-render with clear
        $this->assertStringContainsString(self::CLEAR_SCREEN, $output);
        $this->assertStringContainsString('Hello', $output);
        $this->assertStringContainsString('World', $output);
    }

    // --- Cursor position extraction ---

    public function testCursorMarkerIsDetectedAndStripped()
    {
        $terminal = new VirtualTerminal(80, 24);
        $writer = new ScreenWriter($terminal);

        $marker = AnsiUtils::cursorMarker();
        $writer->writeLines(["Hello{$marker}World"]);

        $output = $terminal->getOutput();

        // Marker should be stripped from the output
        $this->assertStringNotContainsString($marker, $output);

        // Both parts of the line should be present
        $this->assertStringContainsString('Hello', $output);
        $this->assertStringContainsString('World', $output);
    }

    public function testCursorPositionIsExtractedFromCorrectRow()
    {
        $terminal = new VirtualTerminal(80, 24);
        $writer = new ScreenWriter($terminal);

        $marker = AnsiUtils::cursorMarker();
        // Place cursor at beginning of second line (row 1, col 0)
        $writer->writeLines(['First line', "{$marker}Second line"]);

        $output = $terminal->getOutput();

        // Cursor should be positioned - check for column positioning escape
        // The cursor is at col 0, so the sequence is \x1b[1G (1-indexed)
        $this->assertStringContainsString("\x1b[1G", $output);

        // Marker should be stripped
        $this->assertStringNotContainsString($marker, $output);
    }

    // --- Cursor positioning (hardware cursor moves to correct position) ---

    public function testHardwareCursorMovesToCursorPosition()
    {
        $terminal = new VirtualTerminal(80, 24);
        $writer = new ScreenWriter($terminal);

        $marker = AnsiUtils::cursorMarker();
        // Place cursor in the middle of first line (col = 5)
        $writer->writeLines(["Hello{$marker}World", 'Other']);

        $output = $terminal->getOutput();

        // After rendering, cursor should be at row 0, col 5
        // Since hardware cursor ends at row 1 after rendering, it should move UP
        // Column should be 6 (5 + 1 for 1-indexed)
        $this->assertStringContainsString("\x1b[6G", $output);
    }

    public function testNoCursorMarkerHidesCursor()
    {
        $terminal = new VirtualTerminal(80, 24);
        $writer = new ScreenWriter($terminal);

        $writer->writeLines(['No cursor here']);

        $output = $terminal->getOutput();

        // Cursor should be hidden when no marker is present
        $this->assertStringContainsString(self::HIDE_CURSOR, $output);
    }

    // --- Show hardware cursor setting ---

    /**
     * @return iterable<string, array{bool, bool, string, string}>
     */
    public static function cursorVisibilityProvider(): iterable
    {
        $marker = AnsiUtils::cursorMarker();

        yield 'marker present, showHardwareCursor off' => [false, true, self::HIDE_CURSOR, self::SHOW_CURSOR];
        yield 'marker present, showHardwareCursor on' => [true, true, self::SHOW_CURSOR, self::HIDE_CURSOR];
        yield 'no marker, showHardwareCursor on' => [true, false, self::HIDE_CURSOR, self::SHOW_CURSOR];
    }

    #[DataProvider('cursorVisibilityProvider')]
    public function testCursorVisibility(bool $showHardwareCursor, bool $hasMarker, string $expectedContains, string $expectedNotContains)
    {
        $terminal = new VirtualTerminal(80, 24);
        $writer = new ScreenWriter($terminal);
        $writer->setShowHardwareCursor($showHardwareCursor);

        $marker = AnsiUtils::cursorMarker();
        $line = $hasMarker ? "Hello{$marker}World" : 'No cursor here';
        $writer->writeLines([$line]);

        $output = $terminal->getOutput();

        $this->assertStringContainsString($expectedContains, $output);
        $this->assertStringNotContainsString($expectedNotContains, $output);
    }

    public function testDisablingShowHardwareCursorHidesCursorImmediately()
    {
        $terminal = new VirtualTerminal(80, 24);
        $writer = new ScreenWriter($terminal);
        $writer->setShowHardwareCursor(true);

        $terminal->clearOutput();
        $writer->setShowHardwareCursor(false);

        $output = $terminal->getOutput();

        // Disabling should immediately hide the cursor
        $this->assertStringContainsString(self::HIDE_CURSOR, $output);
    }

    // --- Line resets ---

    public function testPlainTextLinesDoNotGetReset()
    {
        $terminal = new VirtualTerminal(80, 24);
        $writer = new ScreenWriter($terminal);

        $writer->writeLines(['Plain text line']);

        $output = $terminal->getOutput();

        // Plain text lines (no ANSI) should NOT have any reset appended
        $this->assertStringNotContainsString('Plain text line'."\x1b[0m", $output);
        $this->assertStringNotContainsString('Plain text line'.AnsiUtils::SEGMENT_RESET, $output);
        $this->assertStringContainsString('Plain text line', $output);
    }

    public function testStyledLinesGetSgrReset()
    {
        $terminal = new VirtualTerminal(80, 24);
        $writer = new ScreenWriter($terminal);

        $writer->writeLines(["\x1b[31mRed text\x1b[0m"]);

        $output = $terminal->getOutput();

        // Lines with ANSI styling should get SGR reset appended
        $this->assertStringContainsString("\x1b[31mRed text\x1b[0m\x1b[0m", $output);
        // But NOT the full SEGMENT_RESET (no OSC 8 hyperlink reset)
        $this->assertStringNotContainsString("\x1b[31mRed text\x1b[0m".AnsiUtils::SEGMENT_RESET, $output);
    }

    public function testHyperlinkLinesGetFullSegmentReset()
    {
        $terminal = new VirtualTerminal(80, 24);
        $writer = new ScreenWriter($terminal);

        $writer->writeLines(["\x1b]8;;https://example.com\x07Link\x1b]8;;\x07"]);

        $output = $terminal->getOutput();

        // Lines with hyperlinks should get the full SEGMENT_RESET
        $this->assertStringContainsString("\x1b]8;;\x07".AnsiUtils::SEGMENT_RESET, $output);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function imageLineProvider(): iterable
    {
        yield 'kitty protocol' => ["prefix\x1b_Gsome-image-data\x1b\\suffix"];
        yield 'iterm2 protocol' => ["\x1b]1337;File=inline=1:data\x07"];
    }

    #[DataProvider('imageLineProvider')]
    public function testImageLinesDoNotGetReset(string $imageLine)
    {
        $terminal = new VirtualTerminal(80, 24);
        $writer = new ScreenWriter($terminal);

        $writer->writeLines([$imageLine]);

        $output = $terminal->getOutput();

        $this->assertStringNotContainsString($imageLine.AnsiUtils::SEGMENT_RESET, $output);
        $this->assertStringContainsString($imageLine, $output);
    }

    // --- reset() clears all state ---

    public function testResetForcesFullReRender()
    {
        $terminal = new VirtualTerminal(80, 24);
        $writer = new ScreenWriter($terminal);

        $writer->writeLines(['First', 'Second']);

        $writer->reset();
        $terminal->clearOutput();

        // After reset, even identical lines should trigger a full re-render
        $writer->writeLines(['First', 'Second']);

        $output = $terminal->getOutput();

        // Reset sets previousWidth to -1, which triggers widthChanged
        $this->assertStringContainsString(self::CLEAR_SCREEN, $output);
        $this->assertStringContainsString('First', $output);
        $this->assertStringContainsString('Second', $output);
    }

    public function testResetClearsState()
    {
        $terminal = new VirtualTerminal(80, 24);
        $writer = new ScreenWriter($terminal);

        $writer->writeLines(['A', 'B', 'C']);

        $state = $writer->getState();
        $this->assertSame(3, $state['line_count']);

        $writer->reset();

        $state = $writer->getState();
        $this->assertSame(0, $state['line_count']);
        $this->assertSame(0, $state['cursor_row']);
    }

    // --- RenderException tests (pre-existing) ---

    public function testRenderExceptionDoesNotCallStopOnTerminal()
    {
        $terminal = new VirtualTerminal(20, 24);
        $screenWriter = new ScreenWriter($terminal);

        // First render: short lines that fit
        $screenWriter->writeLines(['Hello', 'World']);

        $terminal->clearOutput();

        // Second render triggers differential path with a line that exceeds width
        try {
            $screenWriter->writeLines(['Hello', str_repeat('X', 30)]);
            $this->fail('Expected RenderException was not thrown');
        } catch (RenderException $e) {
            $this->assertSame(1, $e->getLineNumber());
            $this->assertSame(30, $e->getLineWidth());
            $this->assertSame(20, $e->getTerminalWidth());
        }

        // The output should NOT contain showCursor sequence
        // which would indicate stop()/showCursor() were called
        $output = $terminal->getOutput();
        $this->assertStringNotContainsString(self::SHOW_CURSOR, $output, 'showCursor() should not be called');

        // The output should end synchronized output properly
        $this->assertStringContainsString("\x1b[?2026l", $output, 'Synchronized output should be ended');
    }

    public function testScreenWriterCanRenderAfterRenderException()
    {
        $terminal = new VirtualTerminal(20, 24);
        $screenWriter = new ScreenWriter($terminal);

        // First render
        $screenWriter->writeLines(['Hello', 'World']);

        // Trigger exception with oversized line
        try {
            $screenWriter->writeLines(['Hello', str_repeat('X', 30)]);
        } catch (RenderException) {
            // expected
        }

        $terminal->clearOutput();

        // ScreenWriter should recover with a full screen-clearing re-render
        $screenWriter->writeLines(['Hello', 'Fixed']);

        $output = $terminal->getOutput();
        $this->assertStringContainsString('Fixed', $output);
        // Verify the screen was cleared (full re-render with clear)
        $this->assertStringContainsString("\x1b[2J", $output, 'Screen should be cleared on recovery');
    }

    public function testRenderExceptionOnFirstChangedLine()
    {
        $terminal = new VirtualTerminal(20, 24);
        $screenWriter = new ScreenWriter($terminal);

        // First render
        $screenWriter->writeLines(['Hello', 'World']);

        // The first changed line itself is oversized
        try {
            $screenWriter->writeLines([str_repeat('X', 30), 'World']);
            $this->fail('Expected RenderException was not thrown');
        } catch (RenderException $e) {
            $this->assertSame(0, $e->getLineNumber());
        }

        $terminal->clearOutput();

        // ScreenWriter should recover with a full screen-clearing re-render
        $screenWriter->writeLines(['Recovered', 'OK']);

        $output = $terminal->getOutput();
        $this->assertStringContainsString('Recovered', $output);
        $this->assertStringContainsString('OK', $output);
        $this->assertStringContainsString("\x1b[2J", $output, 'Screen should be cleared on recovery');
    }
}
