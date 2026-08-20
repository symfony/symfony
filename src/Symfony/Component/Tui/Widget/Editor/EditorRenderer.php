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

use Symfony\Component\Tui\Ansi\AnsiUtils;
use Symfony\Component\Tui\Ansi\TextWrapper;
use Symfony\Component\Tui\Style\CursorShape;
use Symfony\Component\Tui\Style\Style;

/**
 * Renders editor content lines with cursor and word-wrap.
 *
 * This is a stateless helper: all state (document content, cursor position,
 * scroll offset) is passed in from the EditorWidget.
 *
 * @experimental
 *
 * @author Fabien Potencier <fabien@symfony.com>
 *
 * @internal
 */
final class EditorRenderer
{
    /**
     * Render the full editor output: borders + content lines + padding.
     *
     * @param string[]                                                                               $lines              Document lines
     * @param array{scroll_offset: int, visible_line_count: int, lines_above: int, lines_below: int} $viewport           Viewport parameters
     * @param int                                                                                    $cursorLine         Current cursor line
     * @param int                                                                                    $cursorCol          Current cursor column
     * @param int                                                                                    $columns            Terminal columns
     * @param int                                                                                    $maxDisplayRows     Maximum display rows
     * @param bool                                                                                   $verticallyExpanded Whether to fill all rows
     * @param bool                                                                                   $focused            Whether the editor has focus
     * @param CursorShape                                                                            $cursorShape        Cursor shape
     * @param Style                                                                                  $frameStyle         Style for borders
     *
     * @return string[]
     */
    public function render(array $lines, array $viewport, int $cursorLine, int $cursorCol, int $columns, int $maxDisplayRows, bool $verticallyExpanded, bool $focused, CursorShape $cursorShape, Style $frameStyle): array
    {
        $result = [];

        // Top border (with scroll indicator if scrolled down)
        $result[] = $this->renderFrameRow($viewport['lines_above'], '↑', $columns, $frameStyle);

        // Render visible lines
        $contentRows = [];
        $cursorRow = null;
        for ($i = 0; $i < $viewport['visible_line_count']; ++$i) {
            $lineIndex = $viewport['scroll_offset'] + $i;
            $line = $lines[$lineIndex] ?? '';
            $isCursorLine = $lineIndex === $cursorLine;

            $rendered = $this->renderLine($line, $isCursorLine, $cursorCol, $columns, $cursorShape, $focused);
            if (null !== $rendered['cursor_row']) {
                $cursorRow = \count($contentRows) + $rendered['cursor_row'];
            }
            foreach ($rendered['lines'] as $renderedLine) {
                $contentRows[] = $renderedLine;
            }
        }

        // The viewport hands back whole logical lines and always keeps at
        // least one, so the cursor line is drawn even when it wraps to more
        // rows than the editor was given. Show the window of rows the cursor
        // is in rather than every one of them, which would run the editor
        // past its own frame and push whatever is laid out below off screen.
        if (\count($contentRows) > $maxDisplayRows) {
            $start = 0;
            if (null !== $cursorRow && $cursorRow >= $maxDisplayRows) {
                $start = min($cursorRow - $maxDisplayRows + 1, \count($contentRows) - $maxDisplayRows);
            }
            $contentRows = \array_slice($contentRows, $start, $maxDisplayRows);
        }

        $displayRowsRendered = \count($contentRows);
        foreach ($contentRows as $contentRow) {
            $result[] = $contentRow;
        }

        // In fill mode, pad with empty rows to fill the allocated space
        if ($verticallyExpanded && $displayRowsRendered < $maxDisplayRows) {
            $emptyLine = str_repeat(' ', $columns);
            for ($i = $displayRowsRendered; $i < $maxDisplayRows; ++$i) {
                $result[] = $emptyLine;
            }
        }

        // Bottom border (with scroll indicator if more content below)
        $result[] = $this->renderFrameRow($viewport['lines_below'], '↓', $columns, $frameStyle);

        return $result;
    }

    /**
     * Render one frame row, carrying the count of lines hidden in that
     * direction when there are any.
     */
    private function renderFrameRow(int $hiddenLines, string $arrow, int $columns, Style $frameStyle): string
    {
        if ($hiddenLines < 1) {
            return $frameStyle->apply(str_repeat('─', max(0, $columns)));
        }

        // The indicator has a width of its own, and a pane can be narrower
        // than that. Padding it out to the frame is only half the job: what
        // does not fit has to come off, or the row runs past the box and the
        // Renderer rejects it.
        $indicator = \sprintf('─── %s %d more ', $arrow, $hiddenLines);
        $indicatorWidth = AnsiUtils::visibleWidth($indicator);

        if ($indicatorWidth > $columns) {
            return $frameStyle->apply(AnsiUtils::truncateToWidth($indicator, $columns, ''));
        }

        return $frameStyle->apply($indicator.str_repeat('─', $columns - $indicatorWidth));
    }

    /**
     * Render a logical line, possibly wrapped into multiple display lines.
     *
     * @return array{lines: string[], cursor_row: int|null} The display lines
     *                                                      (one or more if wrapped), and which of
     *                                                      them carries the cursor
     */
    private function renderLine(string $line, bool $isCursorLine, int $cursorCol, int $columns, CursorShape $cursorShape, bool $focused): array
    {
        $chunks = TextWrapper::wrapLineIntoChunks($line, $columns);

        $result = [];
        $cursorRow = null;
        $chunkCount = \count($chunks);

        foreach ($chunks as $i => $chunk) {
            $chunkText = $chunk['text'];
            $displayLine = rtrim($chunkText);
            $isLastChunk = $i === $chunkCount - 1;

            // Determine if the cursor is in this chunk
            $hasCursor = false;
            $cursorPosInChunk = 0;

            if ($isCursorLine) {
                if ($isLastChunk) {
                    if ($cursorCol >= $chunk['start_index']) {
                        $hasCursor = true;
                        $cursorPosInChunk = $cursorCol - $chunk['start_index'];
                    }
                } elseif ($cursorCol >= $chunk['start_index'] && $cursorCol < $chunk['end_index']) {
                    $hasCursor = true;
                    $cursorPosInChunk = $cursorCol - $chunk['start_index'];
                }
            }

            if ($hasCursor) {
                $cursorRow ??= \count($result);
                $displayLine = $this->renderCursorInChunk($chunkText, $cursorPosInChunk, $columns, $cursorShape, $focused);
            }

            // A grapheme cannot be split, so a chunk still overflows when one
            // character is wider than the whole box (a CJK glyph in a
            // one-column pane, a joined emoji in four). The Renderer rejects
            // an over-wide line, so cut what does not fit.
            $visibleWidth = AnsiUtils::visibleWidth($displayLine);
            if ($visibleWidth > $columns) {
                $displayLine = AnsiUtils::truncateToWidth($displayLine, $columns, '');
                $visibleWidth = AnsiUtils::visibleWidth($displayLine);
            }

            // Pad to width
            $padding = max(0, $columns - $visibleWidth);

            $result[] = $displayLine.str_repeat(' ', $padding);
        }

        return ['lines' => $result, 'cursor_row' => $cursorRow];
    }

    /**
     * Render a chunk of text with the cursor marker inserted at the given byte position.
     */
    private function renderCursorInChunk(string $chunkText, int $cursorPosInChunk, int $columns, CursorShape $cursorShape, bool $focused): string
    {
        $atCursor = '';
        $afterCursor = '';
        $beforeCursor = '';
        $cursorCharIndex = 0;

        if (false !== $graphemes = grapheme_str_split($chunkText)) {
            $bytePos = 0;
            $found = false;
            foreach ($graphemes as $index => $grapheme) {
                $graphemeBytes = \strlen($grapheme);
                if ($cursorPosInChunk < $bytePos) {
                    $cursorCharIndex = $index;
                    $found = true;
                    break;
                }
                if ($cursorPosInChunk < $bytePos + $graphemeBytes) {
                    $cursorCharIndex = $index;
                    $found = true;
                    break;
                }
                $bytePos += $graphemeBytes;
            }
            if (!$found || !isset($graphemes[$cursorCharIndex])) {
                $cursorCharIndex = \count($graphemes);
            }

            $beforeCursor = implode('', \array_slice($graphemes, 0, $cursorCharIndex));
            if (isset($graphemes[$cursorCharIndex])) {
                $atCursor = $graphemes[$cursorCharIndex];
                $afterCursor = implode('', \array_slice($graphemes, $cursorCharIndex + 1));
            }
        } else {
            $beforeCursor = substr($chunkText, 0, $cursorPosInChunk);
            $afterCursor = $cursorPosInChunk < \strlen($chunkText) ? substr($chunkText, $cursorPosInChunk + 1) : '';
            $atCursor = $chunkText[$cursorPosInChunk] ?? '';
        }

        $marker = $focused ? AnsiUtils::cursorMarker($cursorShape) : '';

        if ('' !== $afterCursor || '' !== $atCursor) {
            // Cursor is on a character
            return $beforeCursor.$marker.$atCursor.$afterCursor;
        }

        // Cursor is at the end of the line
        if ($columns > AnsiUtils::visibleWidth($beforeCursor)) {
            // Room for cursor after the text
            return $beforeCursor.$marker.' ';
        }

        // Full width, place cursor on the last grapheme
        if ($graphemesFallback = grapheme_str_split($beforeCursor)) {
            /** @var string $lastGrapheme */
            $lastGrapheme = array_pop($graphemesFallback);

            return implode('', $graphemesFallback).$marker.$lastGrapheme;
        }

        return $beforeCursor;
    }
}
