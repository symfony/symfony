<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Tui\Widget\Editor;

use Symfony\Component\Tui\Ansi\TextWrapper;

/**
 * Manages scroll offset and viewport calculations for the editor.
 *
 * Owns the scroll offset and computes which logical lines are visible
 * in the terminal viewport, accounting for word-wrap. Also handles
 * mouse cursor placement (display-row → logical line+col mapping).
 *
 * @experimental
 *
 * @author Fabien Potencier <fabien@symfony.com>
 *
 * @internal
 */
final class EditorViewport
{
    private int $scrollOffset = 0;

    public function getScrollOffset(): int
    {
        return $this->scrollOffset;
    }

    public function reset(): void
    {
        $this->scrollOffset = 0;
    }

    /**
     * Scroll by a full page.
     *
     * @param string[] $lines      Document lines
     * @param int      $direction  1 for down, -1 for up
     * @param int      $pageSize   Number of lines per page
     * @param int      $cursorLine Current cursor line
     * @param int      $cursorCol  Current cursor column
     *
     * @return array{cursor_line: int, cursor_col: int}|null New cursor state, or null if unchanged
     */
    public function pageScroll(array $lines, int $direction, int $pageSize, int $cursorLine, int $cursorCol): ?array
    {
        if ($cursorLine === $targetLine = max(0, min(\count($lines) - 1, $cursorLine + $direction * $pageSize))) {
            return null;
        }

        return [
            'cursor_line' => $targetLine,
            'cursor_col' => min($cursorCol, \strlen($lines[$targetLine])),
        ];
    }

    /**
     * Adjust scroll offset so the cursor is visible, and return viewport parameters.
     *
     * @param string[] $lines              Document lines
     * @param int      $cursorLine         Current cursor line
     * @param int      $maxDisplayRows     Maximum display rows available
     * @param int      $columns            Terminal columns
     * @param bool     $verticallyExpanded Whether to fill all available rows
     * @param int      $minVisibleLines    Minimum visible lines
     *
     * @return array{scroll_offset: int, visible_line_count: int, lines_above: int, lines_below: int}
     */
    public function computeViewport(array $lines, int $cursorLine, int $maxDisplayRows, int $columns, bool $verticallyExpanded, int $minVisibleLines): array
    {
        $totalLines = \count($lines);

        // Calculate how many logical lines fit from the current scroll offset
        $logicalLinesFitting = self::logicalLinesFitting($lines, $this->scrollOffset, $maxDisplayRows, $columns);

        // Adjust scroll offset to keep cursor visible
        if ($cursorLine < $this->scrollOffset) {
            $this->scrollOffset = $cursorLine;
        } elseif ($cursorLine >= $this->scrollOffset + $logicalLinesFitting) {
            $this->scrollOffset = self::scrollOffsetForCursorLine($lines, $cursorLine, $maxDisplayRows, $columns);
        }

        // Clamp scroll offset to valid range
        $this->scrollOffset = max(0, min($this->scrollOffset, max(0, $totalLines - 1)));

        // Recalculate after potential scroll offset change
        $logicalLinesFitting = self::logicalLinesFitting($lines, $this->scrollOffset, $maxDisplayRows, $columns);

        // Calculate visible line count
        $visibleLineCount = $verticallyExpanded ? $logicalLinesFitting : min(max($minVisibleLines, $totalLines), $logicalLinesFitting);

        return [
            'scroll_offset' => $this->scrollOffset,
            'visible_line_count' => $visibleLineCount,
            'lines_above' => $this->scrollOffset,
            'lines_below' => max(0, $totalLines - $this->scrollOffset - $visibleLineCount),
        ];
    }

    /**
     * Calculate how many logical lines fit in a given number of display rows,
     * starting from a given offset, accounting for wrapping.
     *
     * @param string[] $lines
     */
    private static function logicalLinesFitting(array $lines, int $fromLine, int $maxDisplayRows, int $columns): int
    {
        $displayRows = 0;
        $count = 0;
        $totalLines = \count($lines);

        for ($i = $fromLine; $i < $totalLines; ++$i) {
            $lineDisplayRows = \count(TextWrapper::wrapLineIntoChunks($lines[$i], $columns));
            if ($displayRows + $lineDisplayRows > $maxDisplayRows) {
                break;
            }
            $displayRows += $lineDisplayRows;
            ++$count;
        }

        return max(1, $count);
    }

    /**
     * Find the scroll offset that places cursorLine as the last visible
     * logical line, accounting for wrapping.
     *
     * @param string[] $lines
     */
    private static function scrollOffsetForCursorLine(array $lines, int $cursorLine, int $maxDisplayRows, int $columns): int
    {
        $displayRows = 0;
        $offset = $cursorLine;

        for ($i = $cursorLine; $i >= 0; --$i) {
            $lineDisplayRows = \count(TextWrapper::wrapLineIntoChunks($lines[$i], $columns));
            if ($displayRows + $lineDisplayRows > $maxDisplayRows) {
                break;
            }
            $displayRows += $lineDisplayRows;
            $offset = $i;
        }

        return $offset;
    }
}
