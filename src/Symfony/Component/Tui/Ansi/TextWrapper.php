<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Tui\Ansi;

/**
 * Text wrapping with ANSI code preservation.
 *
 * @experimental
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
final class TextWrapper
{
    /**
     * Wrap a single line into chunks with position tracking.
     *
     * Unlike wrapTextWithAnsi(), each chunk carries its start/end position
     * in the original line, allowing callers to map cursor positions
     * accurately through word-wrap boundaries.
     *
     * The chunk text may include trailing whitespace; callers that need
     * trimmed display text can rtrim() themselves.
     *
     * @param string $line  A single line of text (no newlines)
     * @param int    $width Maximum visible width per chunk
     *
     * @return list<array{text: string, start_index: int, end_index: int}>
     */
    public static function wrapLineIntoChunks(string $line, int $width): array
    {
        if ('' === $line) {
            return [['text' => '', 'start_index' => 0, 'end_index' => 0]];
        }

        if ($width <= 0) {
            return [['text' => $line, 'start_index' => 0, 'end_index' => \strlen($line)]];
        }

        $lineWidth = AnsiUtils::visibleWidth($line);
        if ($lineWidth <= $width) {
            return [['text' => $line, 'start_index' => 0, 'end_index' => \strlen($line)]];
        }

        $chunks = [];
        if (false === $graphemes = grapheme_str_split($line)) {
            return [['text' => $line, 'start_index' => 0, 'end_index' => \strlen($line)]];
        }

        $currentWidth = 0;
        $chunkStart = 0;

        // Wrap opportunity: the byte position after the last whitespace
        // before a non-whitespace grapheme (where a line break is allowed).
        $wrapOppIndex = -1;
        $wrapOppWidth = 0;

        $byteOffset = 0;
        $count = \count($graphemes);

        for ($i = 0; $i < $count; ++$i) {
            $grapheme = $graphemes[$i];
            $graphemeBytes = \strlen($grapheme);
            $gWidth = AnsiUtils::visibleWidth($grapheme);
            $isWs = ' ' === $grapheme || "\t" === $grapheme;

            // Overflow: current grapheme would exceed the width limit.
            if ($currentWidth + $gWidth > $width) {
                if ($wrapOppIndex >= 0) {
                    // Backtrack to last wrap opportunity (word boundary).
                    $chunks[] = [
                        'text' => substr($line, $chunkStart, $wrapOppIndex - $chunkStart),
                        'start_index' => $chunkStart,
                        'end_index' => $wrapOppIndex,
                    ];
                    $chunkStart = $wrapOppIndex;
                    $currentWidth -= $wrapOppWidth;
                } elseif ($chunkStart < $byteOffset) {
                    // No word boundary available: force-break at current position.
                    $chunks[] = [
                        'text' => substr($line, $chunkStart, $byteOffset - $chunkStart),
                        'start_index' => $chunkStart,
                        'end_index' => $byteOffset,
                    ];
                    $chunkStart = $byteOffset;
                    $currentWidth = 0;
                }
                $wrapOppIndex = -1;
            }

            // Advance past this grapheme.
            $currentWidth += $gWidth;
            $byteOffset += $graphemeBytes;

            // Record wrap opportunity: whitespace followed by non-whitespace.
            // Multiple consecutive spaces group together; the break point is
            // after the last space, at the start of the next word.
            if ($isWs && $i + 1 < $count && ' ' !== $graphemes[$i + 1] && "\t" !== $graphemes[$i + 1]) {
                $wrapOppIndex = $byteOffset; // byte position of the next grapheme
                $wrapOppWidth = $currentWidth;
            }
        }

        // Push the final chunk.
        $chunks[] = [
            'text' => substr($line, $chunkStart),
            'start_index' => $chunkStart,
            'end_index' => \strlen($line),
        ];

        return $chunks;
    }

    /**
     * Wrap text with ANSI codes preserved.
     *
     * Only does word wrapping - no padding, no background colors.
     * Returns lines where each line is <= width visible chars.
     * Active ANSI codes are preserved across line breaks.
     *
     * @param string $text  Text to wrap (may contain ANSI codes and newlines)
     * @param int    $width Maximum visible width per line
     *
     * @return string[] Array of wrapped lines (not padded to width)
     */
    public static function wrapTextWithAnsi(string $text, int $width): array
    {
        if ('' === $text) {
            return [''];
        }

        // Guard against invalid width - return text as-is split by newlines
        if ($width <= 0) {
            return explode("\n", $text);
        }

        // Fast path: single line (no newlines), skip explode/tracker overhead
        if (!str_contains($text, "\n")) {
            return self::wrapSingleLine($text, $width);
        }

        // Handle newlines by processing each line separately
        // Track ANSI state across lines so styles carry over after literal newlines
        $inputLines = explode("\n", $text);
        $result = [];
        $tracker = new AnsiCodeTracker();

        foreach ($inputLines as $inputLine) {
            // Prepend active ANSI codes from previous lines (except for first line)
            $prefix = $result ? $tracker->getActiveCodes() : '';
            $wrapped = self::wrapSingleLine($prefix.$inputLine, $width);
            array_push($result, ...$wrapped);

            // Update tracker with codes from this line for next iteration
            // (skip the scan entirely when no escape sequences are present).
            if (str_contains($inputLine, "\x1b")) {
                $tracker->processText($inputLine);
            }
        }

        return $result ?: [''];
    }

    /**
     * Wrap a single line of text.
     *
     * @return string[]
     */
    private static function wrapSingleLine(string $line, int $width): array
    {
        if ('' === $line) {
            return [''];
        }

        $visibleLength = AnsiUtils::visibleWidth($line);
        if ($visibleLength <= $width) {
            return [$line];
        }

        $wrapped = [];
        $tracker = new AnsiCodeTracker();
        $tokens = self::splitIntoTokensWithAnsi($line);

        $currentLine = '';
        $currentVisibleLength = 0;

        foreach ($tokens as $token) {
            $tokenText = $token['text'];
            $tokenVisibleLength = $token['width'];
            $isWhitespace = $token['is_whitespace'];

            // Token itself is too long - break it character by character
            if ($tokenVisibleLength > $width && !$isWhitespace) {
                if ('' !== $currentLine) {
                    if ('' !== $lineEndReset = $tracker->getLineEndReset()) {
                        $currentLine .= $lineEndReset;
                    }
                    $wrapped[] = rtrim($currentLine);
                    $currentLine = '';
                    $currentVisibleLength = 0;
                }

                // Break long token
                $broken = self::breakLongWord($tokenText, $width, $tracker);
                $brokenLines = $broken['lines'];
                $lastIndex = \count($brokenLines) - 1;
                for ($i = 0; $i < $lastIndex; ++$i) {
                    $wrapped[] = rtrim($brokenLines[$i]);
                }
                $currentLine = $brokenLines[$lastIndex] ?? '';
                $currentVisibleLength = $broken['last_width'];
                continue;
            }

            // Check if adding this token would exceed width
            $totalNeeded = $currentVisibleLength + $tokenVisibleLength;

            if ($totalNeeded > $width && $currentVisibleLength > 0) {
                // Trim trailing whitespace, then add underline reset
                $lineToWrap = rtrim($currentLine);
                if ('' !== $lineEndReset = $tracker->getLineEndReset()) {
                    $lineToWrap .= $lineEndReset;
                }
                $wrapped[] = $lineToWrap;

                if ($isWhitespace) {
                    // Don't start new line with whitespace
                    $currentLine = $tracker->getActiveCodes();
                    $currentVisibleLength = 0;
                } else {
                    $currentLine = $tracker->getActiveCodes().$tokenText;
                    $currentVisibleLength = $tokenVisibleLength;
                }
            } else {
                // Add to current line
                $currentLine .= $tokenText;
                $currentVisibleLength += $tokenVisibleLength;
            }

            if ($token['has_ansi']) {
                $tracker->processText($tokenText);
            }
        }

        if ('' !== $currentLine) {
            $wrapped[] = rtrim($currentLine);
        }

        return $wrapped ?: [''];
    }

    /**
     * Split text into tokens (words and whitespace runs) while keeping ANSI codes attached.
     *
     * @return array<int, array{text: string, width: int, is_whitespace: bool, has_ansi: bool}>
     */
    private static function splitIntoTokensWithAnsi(string $text): array
    {
        $tokens = [];
        $current = '';
        $pendingAnsi = '';
        $inWhitespace = false;
        $currentWidth = 0;
        $needsUnicodeWidth = false;
        $currentHasAnsi = false;
        $i = 0;
        $len = \strlen($text);

        while ($i < $len) {
            $char = $text[$i];

            // Only check for ANSI codes when we see an ESC byte
            if ("\x1b" === $char) {
                // Inline CSI fast path with strspn
                if ($i + 1 < $len && '[' === $text[$i + 1]) {
                    $j = $i + 2 + strspn($text, '0123456789:;<=>?', $i + 2);
                    if ($j < $len && \ord($text[$j]) >= 0x40 && \ord($text[$j]) <= 0x7E) {
                        $pendingAnsi .= substr($text, $i, $j + 1 - $i);
                        $i = $j + 1;
                        continue;
                    }
                }
                $ansi = AnsiUtils::extractAnsiCode($text, $i);
                if (null !== $ansi) {
                    $pendingAnsi .= $ansi['code'];
                    $i += $ansi['length'];
                    continue;
                }
            }

            $charIsSpace = ' ' === $char || "\t" === $char;

            if ($charIsSpace !== $inWhitespace && '' !== $current) {
                // Switching between whitespace and non-whitespace, push current token
                /* @var int $currentWidth */
                $tokens[] = [
                    'text' => $current,
                    'width' => $needsUnicodeWidth ? AnsiUtils::visibleWidth($current) : $currentWidth,
                    'is_whitespace' => $inWhitespace,
                    'has_ansi' => $currentHasAnsi,
                ];
                $current = '';
                $currentWidth = 0;
                $needsUnicodeWidth = false;
                $currentHasAnsi = false;
            }

            // Attach any pending ANSI codes to this visible character
            if ('' !== $pendingAnsi) {
                $current .= $pendingAnsi;
                $pendingAnsi = '';
                $currentHasAnsi = true;
            }

            $inWhitespace = $charIsSpace;

            // Bulk-consume consecutive printable ASCII non-space chars or consecutive spaces
            if (!$needsUnicodeWidth && $char >= '!' && $char <= '~') {
                // Non-whitespace printable ASCII: scan ahead for a run
                $runStart = $i;
                ++$i;
                while ($i < $len && $text[$i] >= '!' && $text[$i] <= '~') {
                    ++$i;
                }
                $run = substr($text, $runStart, $i - $runStart);
                $current .= $run;
                $currentWidth += $i - $runStart;
                continue;
            }

            $current .= $char;

            if ("\t" === $char) {
                $currentWidth += 3;
            } elseif ($char >= ' ' && $char <= '~') {
                ++$currentWidth;
            } else {
                $needsUnicodeWidth = true;
            }

            ++$i;
        }

        // Handle any remaining pending ANSI codes (attach to last token)
        if ('' !== $pendingAnsi) {
            $current .= $pendingAnsi;
            $currentHasAnsi = true;
        }

        if ('' !== $current) {
            /* @var int $currentWidth */
            $tokens[] = [
                'text' => $current,
                'width' => $needsUnicodeWidth ? AnsiUtils::visibleWidth($current) : $currentWidth,
                'is_whitespace' => $inWhitespace,
                'has_ansi' => $currentHasAnsi,
            ];
        }

        return $tokens;
    }

    /**
     * Break a long word into multiple lines.
     *
     * @return array{lines: string[], last_width: int}
     */
    private static function breakLongWord(string $word, int $width, AnsiCodeTracker $tracker): array
    {
        $lines = [];
        $currentLine = $tracker->getActiveCodes();
        $currentWidth = 0;

        $i = 0;
        $wordLen = \strlen($word);
        $segments = [];

        // First, separate ANSI codes from visible content
        while ($i < $wordLen) {
            $byte = $word[$i];

            // Only check for ANSI when we see an ESC byte
            if ("\x1b" === $byte && null !== $ansi = AnsiUtils::extractAnsiCode($word, $i)) {
                $segments[] = ['type' => 'ansi', 'value' => $ansi['code']];
                $i += $ansi['length'];
                continue;
            }

            // Find the next ESC byte or end of string for the text portion
            if (false === $end = strpos($word, "\x1b", $i + 1)) {
                $end = $wordLen;
            }

            // Segment this non-ANSI portion into graphemes
            $textPortion = substr($word, $i, $end - $i);
            $graphemes = grapheme_str_split($textPortion);
            foreach ($graphemes ?: [] as $grapheme) {
                $segments[] = ['type' => 'grapheme', 'value' => $grapheme];
            }
            $i = $end;
        }

        // Process segments
        foreach ($segments as $seg) {
            if ('ansi' === $seg['type']) {
                $currentLine .= $seg['value'];
                $tracker->process($seg['value']);
                continue;
            }

            if ('' === $grapheme = $seg['value']) {
                continue;
            }

            $graphemeWidth = AnsiUtils::graphemeWidth($grapheme);

            if ($currentWidth + $graphemeWidth > $width) {
                // Add specific reset for underline only (preserves background)
                if ('' !== $lineEndReset = $tracker->getLineEndReset()) {
                    $currentLine .= $lineEndReset;
                }
                $lines[] = $currentLine;
                $currentLine = $tracker->getActiveCodes();
                $currentWidth = 0;
            }

            $currentLine .= $grapheme;
            $currentWidth += $graphemeWidth;
        }

        if ('' !== $currentLine) {
            $lines[] = $currentLine;
        }

        if (!$lines) {
            return ['lines' => [''], 'last_width' => 0];
        }

        return ['lines' => $lines, 'last_width' => $currentWidth];
    }
}
