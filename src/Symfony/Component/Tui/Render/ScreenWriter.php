<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Tui\Render;

use Symfony\Component\Tui\Ansi\AnsiUtils;
use Symfony\Component\Tui\Exception\RenderException;
use Symfony\Component\Tui\Terminal\TerminalInterface;

/**
 * Handles efficient terminal output with differential rendering.
 *
 * Accepts rendered lines (the composited screen state) and writes them
 * to the terminal with minimal updates using line-level diffing.
 *
 * This class is responsible for:
 * - Tracking screen state (previous lines, cursor position)
 * - Computing minimal updates between frames
 * - Writing ANSI sequences to the terminal
 * - Managing cursor position for differential rendering
 *
 * @experimental
 *
 * @internal
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
final class ScreenWriter
{
    private const PRINTABLE_ASCII = ' !"#$%&\'()*+,-./0123456789:;<=>?@ABCDEFGHIJKLMNOPQRSTUVWXYZ[\\]^_`abcdefghijklmnopqrstuvwxyz{|}~';

    /** @var string[] */
    private array $previousLines = [];
    private int $previousWidth = 0;
    private int $cursorRow = 0;
    private int $hardwareCursorRow = 0;
    private int $maxLinesRendered = 0;
    private bool $showHardwareCursor = true;
    private int $scrollOffset = 0;

    /** @var string[] */
    private array $previousRawLines = [];

    /** @var array{row: int, col: int, shape: int}|null */
    private ?array $previousCursorPos = null;

    public function __construct(
        private readonly TerminalInterface $terminal,
    ) {
    }

    public function setShowHardwareCursor(bool $enabled): void
    {
        if ($this->showHardwareCursor === $enabled) {
            return;
        }

        $this->showHardwareCursor = $enabled;

        if (!$enabled) {
            $this->terminal->hideCursor();
        }
    }

    /**
     * Set the scroll offset (lines from bottom).
     *
     * When the content exceeds the viewport, the viewport normally shows
     * the bottom portion. A positive scroll offset shifts the viewport
     * up by that many lines.
     */
    public function setScrollOffset(int $offset): void
    {
        if ($this->scrollOffset !== $offset = max(0, $offset)) {
            $this->scrollOffset = $offset;
            $this->reset();
        }
    }

    /**
     * Get the current scroll offset.
     */
    public function getScrollOffset(): int
    {
        return $this->scrollOffset;
    }

    /**
     * Write ANSI lines to the terminal using differential rendering.
     *
     * @param string[] $lines The new content to display
     */
    public function writeLines(array $lines): void
    {
        // Apply scroll offset: when content exceeds the viewport, slice to
        // show a window shifted up from the bottom by scrollOffset lines.
        if ($this->scrollOffset > 0) {
            $totalLines = \count($lines);
            $rows = $this->terminal->getRows();
            if ($totalLines > $rows) {
                $maxOffset = $totalLines - $rows;
                $effectiveOffset = min($this->scrollOffset, $maxOffset);
                $startLine = $totalLines - $rows - $effectiveOffset;
                $lines = \array_slice($lines, $startLine, $rows);
            }
        }

        if ($this->previousLines && $this->previousWidth === $this->terminal->getColumns() && $lines === $this->previousRawLines) {
            $this->positionHardwareCursor($this->previousCursorPos, \count($this->previousLines));

            return;
        }

        $rawLines = $lines;
        ['lines' => $lines, 'cursor_pos' => $cursorPos, 'first_changed' => $firstChanged, 'last_changed' => $lastChanged] = $this->prepareLines($lines);

        $this->writeInternal($lines, $cursorPos, $firstChanged, $lastChanged);
        $this->previousRawLines = $rawLines;
        $this->previousCursorPos = $cursorPos;
    }

    /**
     * Clear rendering state, forcing a full re-render on next write.
     *
     * The scroll offset is preserved so that a forced re-render does not
     * jump back to the bottom of the content.
     */
    public function reset(): void
    {
        $this->previousLines = [];
        $this->previousRawLines = [];
        $this->previousCursorPos = null;
        $this->previousWidth = -1; // -1 triggers widthChanged
        $this->cursorRow = 0;
        $this->hardwareCursorRow = 0;
        $this->maxLinesRendered = 0;
    }

    /**
     * Get the final cursor position for cleanup when stopping.
     *
     * @return array{line_count: int, cursor_row: int}
     */
    public function getState(): array
    {
        return [
            'line_count' => \count($this->previousLines),
            'cursor_row' => $this->hardwareCursorRow,
        ];
    }

    /**
     * Internal write implementation.
     *
     * @param string[]                                   $lines
     * @param array{row: int, col: int, shape: int}|null $cursorPos
     */
    private function writeInternal(array $lines, ?array $cursorPos, int $firstChanged, int $lastChanged): void
    {
        $columns = $this->terminal->getColumns();
        $rows = $this->terminal->getRows();

        // Width changed - need full re-render
        $widthChanged = 0 !== $this->previousWidth && $this->previousWidth !== $columns;

        // First render or width changed
        if (!$this->previousLines || $widthChanged) {
            $this->fullRender($lines, $cursorPos, $widthChanged);

            return;
        }

        $lineCount = \count($lines);

        if (-1 === $firstChanged) {
            $this->positionHardwareCursor($cursorPos, $lineCount);

            return;
        }

        if ($firstChanged >= $lineCount) {
            $this->handleDeletedLines($lines, $cursorPos, $rows);

            return;
        }

        // Check if firstChanged is outside the viewport
        $viewportTop = $this->terminal->isVirtual() ? 0 : max(0, $this->maxLinesRendered - $rows);

        if ($firstChanged < $viewportTop) {
            $this->fullRender($lines, $cursorPos, true);

            return;
        }

        // Differential render
        $this->differentialRender($lines, $cursorPos, $firstChanged, $lastChanged, $columns);
    }

    /**
     * Writes all lines to the terminal from scratch.
     *
     * This is only used for the first render and for cases where incremental
     * updates are not possible. For subsequent renders where changed lines are
     * within the viewport, differentialRender() is used instead.
     *
     * When $clear is false, the output is appended from the current cursor
     * position (used for the very first render when the screen is already
     * empty).
     *
     * When $clear is true, the screen is erased and the cursor is moved home
     * before writing. The consequence is that the display resets and starts
     * from the top of the screen, which is a small caveat of the algorithm
     * used. $clear must be true in three cases:
     *
     *  - On terminal resize: a line may have been split into 2 lines by the
     *    terminal, making it impossible to update the display incrementally.
     *  - When changed lines are outside the viewport: there is no way to
     *    address lines that have scrolled out of the visible area.
     *  - When too many trailing lines were deleted: if the number of lines to
     *    erase exceeds the terminal height, clearing is more efficient than
     *    erasing them one by one.
     *
     * @param string[]                                   $newLines
     * @param array{row: int, col: int, shape: int}|null $cursorPos
     */
    private function fullRender(array $newLines, ?array $cursorPos, bool $clear): void
    {
        $buffer = "\x1b[?2026h"; // Begin synchronized output

        if ($clear) {
            $buffer .= "\x1b[2J\x1b[3J\x1b[H"; // Clear screen, clear scrollback, and home
        }

        if ($newLines) {
            $buffer .= implode("\r\n", $newLines);
        }

        $buffer .= "\x1b[?2026l"; // End synchronized output

        $this->terminal->write($buffer);
        $this->cursorRow = max(0, \count($newLines) - 1);
        $this->hardwareCursorRow = $this->cursorRow;

        if ($clear) {
            $this->maxLinesRendered = \count($newLines);
        } else {
            $this->maxLinesRendered = max($this->maxLinesRendered, \count($newLines));
        }

        $this->positionHardwareCursor($cursorPos, \count($newLines));
        $this->previousLines = $newLines;
        $this->previousWidth = $this->terminal->getColumns();
    }

    /**
     * @param string[]                                   $newLines
     * @param array{row: int, col: int, shape: int}|null $cursorPos
     *
     * @return bool True when a full render fallback was used
     */
    private function handleDeletedLines(array $newLines, ?array $cursorPos, int $height): bool
    {
        if (\count($this->previousLines) <= \count($newLines)) {
            $this->positionHardwareCursor($cursorPos, \count($newLines));
            $this->previousLines = $newLines;
            $this->previousWidth = $this->terminal->getColumns();

            return false;
        }

        $buffer = "\x1b[?2026h";

        $targetRow = max(0, \count($newLines) - 1);
        $lineDiff = $targetRow - $this->hardwareCursorRow;

        if ($lineDiff > 0) {
            $buffer .= "\x1b[{$lineDiff}B";
        } elseif ($lineDiff < 0) {
            $buffer .= "\x1b[".(-$lineDiff).'A';
        }

        $buffer .= "\r";

        $extraLines = \count($this->previousLines) - \count($newLines);

        if ($extraLines > $height) {
            $this->fullRender($newLines, $cursorPos, true);

            return true;
        }

        $newLineCount = \count($newLines);

        if ($extraLines > 0 && $newLineCount > 0) {
            $buffer .= "\x1b[1B";
        }

        for ($i = 0; $i < $extraLines; ++$i) {
            $buffer .= "\r\x1b[2K";
            if ($i < $extraLines - 1) {
                $buffer .= "\x1b[1B";
            }
        }

        $moveUp = $extraLines + ($newLineCount > 0 ? 0 : -1);
        if ($moveUp > 0) {
            $buffer .= "\x1b[{$moveUp}A";
        }

        $buffer .= "\x1b[?2026l";

        $this->terminal->write($buffer);
        $this->cursorRow = $targetRow;
        $this->hardwareCursorRow = $targetRow;

        $this->positionHardwareCursor($cursorPos, \count($newLines));
        $this->previousLines = $newLines;
        $this->previousWidth = $this->terminal->getColumns();

        return false;
    }

    /**
     * @param string[]                                   $newLines
     * @param array{row: int, col: int, shape: int}|null $cursorPos
     */
    private function differentialRender(array $newLines, ?array $cursorPos, int $firstChanged, int $lastChanged, int $width): void
    {
        $buffer = "\x1b[?2026h"; // Begin synchronized output

        // Move cursor to first changed line
        $lineDiff = $firstChanged - $this->hardwareCursorRow;
        if ($lineDiff > 0) {
            $buffer .= "\x1b[{$lineDiff}B";
        } elseif ($lineDiff < 0) {
            $buffer .= "\x1b[".(-$lineDiff).'A';
        }

        $buffer .= "\r";

        // Render changed lines
        $renderEnd = min($lastChanged, \count($newLines) - 1);

        for ($i = $firstChanged; $i <= $renderEnd; ++$i) {
            if ($i > $firstChanged) {
                $buffer .= "\r\n";
            }
            $buffer .= "\x1b[2K";

            $line = $newLines[$i];
            $lineWidth = null;
            $lineLength = \strlen($line);

            if ($lineLength === strcspn($line, "\x1b\t") && $lineLength === strspn($line, self::PRINTABLE_ASCII)) {
                $lineWidth = $lineLength;
            } elseif (!AnsiUtils::containsImage($line)) {
                $lineWidth = AnsiUtils::visibleWidth($line);
            }

            if (null !== $lineWidth && $lineWidth > $width) {
                // End synchronized output before throwing so the terminal
                // is not left in buffered mode and ScreenWriter state stays consistent
                $buffer .= "\x1b[?2026l";
                $this->terminal->write($buffer);

                $this->hardwareCursorRow = $i;
                // Force a full re-render with screen clear on next call
                // since the screen is now in a partially updated state
                $this->previousLines = [];
                $this->previousWidth = -1;

                // Strip ANSI codes for readable debug output
                $plainLine = preg_replace('/\x1b(?:\[[0-9;]*[a-zA-Z]|\][^\x07]*\x07)/', '', $line);
                $preview = mb_substr($plainLine, 0, 100);

                throw new RenderException(\sprintf("Rendered line %d exceeds terminal width (%d > %d).\nLine preview: %s%s.", $i, $lineWidth, $width, $preview, mb_strlen($plainLine) > 100 ? '...' : ''), $i, $lineWidth, $width);
            }

            $buffer .= $line;
        }

        $finalCursorRow = $renderEnd;

        // Handle content size changes
        if (\count($this->previousLines) > \count($newLines)) {
            // Content shrunk - clear extra lines
            if ($renderEnd < \count($newLines) - 1) {
                $moveDown = \count($newLines) - 1 - $renderEnd;
                $buffer .= "\x1b[{$moveDown}B";
                $finalCursorRow = \count($newLines) - 1;
            }

            $extraLines = \count($this->previousLines) - \count($newLines);
            $buffer .= str_repeat("\r\n\x1b[2K", $extraLines);

            $buffer .= "\x1b[{$extraLines}A";
        } elseif (\count($newLines) > \count($this->previousLines) && $renderEnd < \count($newLines) - 1) {
            // Content grew - output any additional lines not already rendered
            // Only needed if renderEnd < newLines.length - 1 (i.e., we didn't render to the end)
            for ($i = $renderEnd + 1; $i < \count($newLines); ++$i) {
                $buffer .= "\r\n\x1b[2K";
                $buffer .= $newLines[$i];
                $finalCursorRow = $i;
            }
        }

        $buffer .= "\x1b[?2026l"; // End synchronized output

        $this->terminal->write($buffer);

        $this->cursorRow = max(0, \count($newLines) - 1);
        $this->hardwareCursorRow = $finalCursorRow;
        $this->maxLinesRendered = max($this->maxLinesRendered, \count($newLines));

        $this->positionHardwareCursor($cursorPos, \count($newLines));
        $this->previousLines = $newLines;
        $this->previousWidth = $this->terminal->getColumns();
    }

    /**
     * Strip cursor markers, apply line resets, and detect changed rows in one pass.
     *
     * @param string[] $lines
     *
     * @return array{lines: string[], cursor_pos: array{row: int, col: int, shape: int}|null, first_changed: int, last_changed: int}
     */
    private function prepareLines(array $lines): array
    {
        $cursorPos = null;
        $firstChanged = -1;
        $lastChanged = -1;
        $lineCount = \count($lines);
        $previousLineCount = \count($this->previousLines);

        foreach ($lines as $row => $line) {
            if ($line === $oldLine = $row < $previousLineCount ? $this->previousLines[$row] : '') {
                continue;
            }

            if (str_contains($line, "\x1b")) {
                if ($oldLine === $line."\x1b[0m" || $oldLine === $line.AnsiUtils::SEGMENT_RESET) {
                    $lines[$row] = $oldLine;
                    continue;
                }

                if (null === $cursorPos) {
                    $markerIndex = strpos($line, AnsiUtils::CURSOR_MARKER_PREFIX);
                    if (false !== $markerIndex && false !== $endIndex = strpos($line, "\x07", $markerIndex)) {
                        $markerLen = $endIndex - $markerIndex + 1;
                        $shapeStr = substr($line, $markerIndex + \strlen(AnsiUtils::CURSOR_MARKER_PREFIX), $endIndex - $markerIndex - \strlen(AnsiUtils::CURSOR_MARKER_PREFIX));
                        $beforeMarker = substr($line, 0, $markerIndex);
                        $cursorPos = ['row' => $row, 'col' => AnsiUtils::visibleWidth($beforeMarker), 'shape' => (int) $shapeStr];
                        $line = substr($line, 0, $markerIndex).substr($line, $markerIndex + $markerLen);
                    }
                }

                if (str_contains($line, "\x1b") && !AnsiUtils::containsImage($line)) {
                    $line = str_contains($line, "\x1b]8;")
                        ? $line.AnsiUtils::SEGMENT_RESET
                        : $line."\x1b[0m";
                }
            }

            $lines[$row] = $line;

            if ($oldLine !== $line) {
                if (-1 === $firstChanged) {
                    $firstChanged = $row;
                }
                $lastChanged = $row;
            }
        }

        if ($previousLineCount > $lineCount) {
            if (-1 === $firstChanged) {
                $firstChanged = $lineCount;
            }
            $lastChanged = $previousLineCount - 1;
        }

        return [
            'lines' => $lines,
            'cursor_pos' => $cursorPos,
            'first_changed' => $firstChanged,
            'last_changed' => $lastChanged,
        ];
    }

    /**
     * Position the hardware cursor, set its shape, and manage visibility.
     *
     * @param array{row: int, col: int, shape: int}|null $cursorPos
     */
    private function positionHardwareCursor(?array $cursorPos, int $totalLines): void
    {
        if (null === $cursorPos || $totalLines <= 0) {
            $this->terminal->hideCursor();

            return;
        }

        $targetRow = max(0, min($cursorPos['row'], $totalLines - 1));
        $targetCol = max(0, $cursorPos['col']);

        $rowDelta = $targetRow - $this->hardwareCursorRow;
        $buffer = '';

        if ($rowDelta > 0) {
            $buffer .= "\x1b[{$rowDelta}B";
        } elseif ($rowDelta < 0) {
            $buffer .= "\x1b[".(-$rowDelta).'A';
        }

        // Move to absolute column (1-indexed)
        $buffer .= "\x1b[".($targetCol + 1).'G';

        // Set cursor shape via DECSCUSR (Set Cursor Style)
        $buffer .= "\x1b[".$cursorPos['shape'].' q';

        $this->terminal->write($buffer);

        $this->hardwareCursorRow = $targetRow;

        if ($this->showHardwareCursor) {
            $this->terminal->showCursor();
        } else {
            $this->terminal->hideCursor();
        }
    }
}
