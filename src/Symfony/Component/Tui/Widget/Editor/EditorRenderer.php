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
        if ($viewport['lines_above'] > 0) {
            $indicator = "─── ↑ {$viewport['lines_above']} more ";
            $remaining = $columns - AnsiUtils::visibleWidth($indicator);
            $result[] = $frameStyle->apply($indicator.str_repeat('─', max(0, $remaining)));
        } else {
            $result[] = $frameStyle->apply(str_repeat('─', $columns));
        }

        // Render visible lines
        $displayRowsRendered = 0;
        for ($i = 0; $i < $viewport['visible_line_count']; ++$i) {
            $lineIndex = $viewport['scroll_offset'] + $i;
            $line = $lines[$lineIndex] ?? '';
            $isCursorLine = $lineIndex === $cursorLine;

            $renderedLines = $this->renderLine($line, $isCursorLine, $cursorCol, $columns, $cursorShape, $focused);
            foreach ($renderedLines as $renderedLine) {
                $result[] = $renderedLine;
            }
            $displayRowsRendered += \count($renderedLines);
        }

        // In fill mode, pad with empty rows to fill the allocated space
        if ($verticallyExpanded && $displayRowsRendered < $maxDisplayRows) {
            $emptyLine = str_repeat(' ', $columns);
            for ($i = $displayRowsRendered; $i < $maxDisplayRows; ++$i) {
                $result[] = $emptyLine;
            }
        }

        // Bottom border (with scroll indicator if more content below)
        if ($viewport['lines_below'] > 0) {
            $indicator = "─── ↓ {$viewport['lines_below']} more ";
            $remaining = $columns - AnsiUtils::visibleWidth($indicator);
            $result[] = $frameStyle->apply($indicator.str_repeat('─', max(0, $remaining)));
        } else {
            $result[] = $frameStyle->apply(str_repeat('─', $columns));
        }

        return $result;
    }

    /**
     * Render a logical line, possibly wrapped into multiple display lines.
     *
     * @return string[] Array of display lines (one or more if wrapped)
     */
    private function renderLine(string $line, bool $isCursorLine, int $cursorCol, int $columns, CursorShape $cursorShape, bool $focused): array
    {
        $chunks = TextWrapper::wrapLineIntoChunks($line, $columns);

        $result = [];
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
                $displayLine = $this->renderCursorInChunk($chunkText, $cursorPosInChunk, $columns, $cursorShape, $focused);
            }

            // Pad to width
            $visibleWidth = AnsiUtils::visibleWidth($displayLine);
            $padding = max(0, $columns - $visibleWidth);

            $result[] = $displayLine.str_repeat(' ', $padding);
        }

        return $result;
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
