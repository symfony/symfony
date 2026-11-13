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
    // Unicode line breaking allows a break between any two characters of these scripts
    private const CJK_PATTERN = '/[\p{Han}\p{Hiragana}\p{Katakana}\p{Hangul}\p{Bopomofo}]/u';
    // SGR codes that only turn attributes off, like \e[0m, \e[24m or \e[39;49m
    private const SGR_OFF_PATTERN = '/^(?:\e\[(?:0|2[2-57-9]|[345]9|4:0)?(?:;(?:0|2[2-57-9]|[345]9|4:0)?)*m)+/';

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
        $hasCjk = preg_match(self::CJK_PATTERN, $line);

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
                }

                if ($currentWidth + $gWidth > $width && $chunkStart < $byteOffset) {
                    // No word boundary available, or the word still does not fit on its own line: force-break at current position.
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
            } elseif ($hasCjk && !$isWs && $i + 1 < $count && ' ' !== $graphemes[$i + 1] && "\t" !== $graphemes[$i + 1]
                && (preg_match(self::CJK_PATTERN, $grapheme) || preg_match(self::CJK_PATTERN, $graphemes[$i + 1]))) {
                // Or between two graphemes when either is CJK
                $wrapOppIndex = $byteOffset;
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
     * @param string $text   Text to wrap (may contain ANSI codes and newlines)
     * @param int    $width  Maximum visible width per line
     * @param string $hyphen What ends a line broken at a soft hyphen
     *
     * @return string[] Array of wrapped lines (not padded to width)
     */
    public static function wrapTextWithAnsi(string $text, int $width, string $hyphen = '-'): array
    {
        if ('' === $text) {
            return [''];
        }

        // Guard against invalid width - return text as-is split by newlines
        if ($width <= 0) {
            return self::splitLines($text);
        }

        // Fast path: single line (no newlines), skip explode/tracker overhead
        if (!str_contains($text, "\n") && !str_contains($text, "\r")) {
            return self::wrapSingleLine($text, $width, $hyphen);
        }

        // Handle newlines by processing each line separately
        // Track ANSI state across lines so styles carry over after literal newlines
        $inputLines = self::splitLines($text);
        $result = [];
        $tracker = new AnsiCodeTracker();

        foreach ($inputLines as $inputLine) {
            // The previous line gets the underline reset, like a wrapped line
            if ($result && '' !== $lineEndReset = $tracker->getLineEndReset()) {
                $result[array_key_last($result)] .= $lineEndReset;
            }

            // Prepend active ANSI codes from previous lines (except for first line)
            $prefix = $result ? $tracker->getActiveCodes() : '';
            $wrapped = self::wrapSingleLine($prefix.$inputLine, $width, $hyphen);
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
     * Wrap text with ANSI codes preserved, each line in a width of its own, like text flowing around a float.
     *
     * @param \Closure(int): int $widthAt The width of a line, by its index from 0
     * @param string             $hyphen  What ends a line broken at a soft hyphen
     *
     * @return string[]
     */
    public static function wrapTextWithAnsiWidths(string $text, \Closure $widthAt, string $hyphen = '-'): array
    {
        if ('' === $text) {
            return [''];
        }

        // A row without room, like one beside floats as wide as the line, stays empty: the text goes on below it, like
        // the line boxes of CSS moving down past the floats, however tall; the rows only go forward, so that each is
        // asked once, a hundred thousand at most
        $rows = [];
        $rowAt = static function (int $index) use (&$rows, $widthAt): int {
            while (\count($rows) <= $index) {
                $row = [] === $rows ? 0 : end($rows) + 1;
                for ($skipped = 0; $skipped < 100_000 && $widthAt($row) <= 0; ++$skipped) {
                    ++$row;
                }
                $rows[] = $row;
            }

            return $rows[$index];
        };

        $result = [];
        $placed = 0;
        $tracker = new AnsiCodeTracker();
        foreach (self::splitLines($text) as $inputLine) {
            $prefix = $placed > 0 ? $tracker->getActiveCodes() : '';
            $offset = $placed;
            foreach (self::wrapSingleLine($prefix.$inputLine, static fn (int $index): int => max(1, $widthAt($rowAt($offset + $index))), $hyphen) as $index => $line) {
                $row = $rowAt($offset + $index);
                while (\count($result) < $row) {
                    $result[] = '';
                }
                $result[] = $line;
                ++$placed;
            }
            if (str_contains($inputLine, "\x1b")) {
                $tracker->processText($inputLine);
            }
        }

        return $result ?: [''];
    }

    /**
     * Wrap a single line of text.
     *
     * @param int|\Closure(int): int $widths The width of the lines, or of each line by its index
     * @param string                 $hyphen What ends a line broken at a soft hyphen
     *
     * @return string[]
     */
    private static function wrapSingleLine(string $line, int|\Closure $widths, string $hyphen = '-'): array
    {
        if ('' === $line) {
            return [''];
        }

        $wrapped = [];
        // The width of each line asked once: a long word broken asks for those of the lines after it
        if ($widths instanceof \Closure) {
            $known = [];
            $widths = static function (int $line) use ($widths, &$known): int {
                return $known[$line] ??= $widths($line);
            };
        }
        $width = \is_int($widths) ? $widths : $widths(0);

        // A soft hyphen is drawn only where the line breaks: elsewhere it takes no room
        $softHyphens = str_contains($line, "\u{00AD}");
        $visibleLength = AnsiUtils::visibleWidth($softHyphens ? str_replace("\u{00AD}", '', $line) : $line);
        if ($visibleLength <= $width) {
            return [$softHyphens ? str_replace("\u{00AD}", '', $line) : $line];
        }

        $tracker = new AnsiCodeTracker();
        $tokens = self::splitIntoTokensWithAnsi($line);
        // Words joined by no-break spaces too wide for a line, like the words of an inline block or of a nowrap element:
        // broken at those spaces rather than inside a word
        if (str_contains($line, "\u{00A0}")) {
            $tokens = self::splitWideAtNoBreakSpaces($tokens, self::narrowest($widths, 0, $width));
        }

        $currentLine = '';
        $currentVisibleLength = 0;
        $afterSoftHyphen = false;

        // The rest of the word after each piece of it, which a piece before a soft hyphen goes with unless the hyphen fits
        $rest = [];
        if ($softHyphens) {
            $after = 0;
            for ($i = \count($tokens) - 1; $i >= 0; --$i) {
                $rest[$i] = $after;
                $after = !$tokens[$i]['is_whitespace'] || ($tokens[$i]['soft_hyphen'] ?? false) ? $tokens[$i]['width'] + $after : 0;
            }
        }
        $hyphenWidth = AnsiUtils::visibleWidth($hyphen);

        foreach ($tokens as $index => $token) {
            $tokenText = $token['text'];
            $tokenVisibleLength = $token['width'];
            $isWhitespace = $token['is_whitespace'];
            $hyphenate = $afterSoftHyphen;
            $afterSoftHyphen = $token['soft_hyphen'] ?? false;

            // Token itself is too long - break it character by character
            if ($tokenVisibleLength > $width && !$isWhitespace) {
                if ('' !== $currentLine) {
                    if ('' !== $lineEndReset = $tracker->getLineEndReset()) {
                        $currentLine .= $lineEndReset;
                    }
                    $wrapped[] = rtrim($currentLine);
                    $width = \is_int($widths) ? $widths : $widths(\count($wrapped));
                    $currentLine = '';
                    $currentVisibleLength = 0;
                }

                // Break long token: in pieces as wide as the narrowest of the lines it goes on
                $broken = self::breakLongWord($tokenText, self::narrowest($widths, \count($wrapped), $width), $tracker);
                $brokenLines = $broken['lines'];
                $lastIndex = \count($brokenLines) - 1;
                for ($i = 0; $i < $lastIndex; ++$i) {
                    $wrapped[] = rtrim($brokenLines[$i]);
                }
                $width = \is_int($widths) ? $widths : $widths(\count($wrapped));
                $currentLine = $brokenLines[$lastIndex] ?? '';
                $currentVisibleLength = $broken['last_width'];
                continue;
            }

            // Check if adding this token would exceed width
            $totalNeeded = $currentVisibleLength + $tokenVisibleLength;
            // A piece of a word before a soft hyphen: the line can break after it only with room for the hyphen
            if (!$isWhitespace && ($tokens[$index + 1]['soft_hyphen'] ?? false) && $totalNeeded <= $width && $totalNeeded + $rest[$index] > $width && $totalNeeded + $hyphenWidth > $width) {
                $totalNeeded = $width + 1;
            }

            if (($token['break_before'] ?? false) && $currentVisibleLength > 0) {
                $totalNeeded = $width + 1;
            }

            if ($totalNeeded > $width && $currentVisibleLength > 0) {
                // The codes of this whitespace can end the style of the previous word, so they end this line
                $ending = '';
                if ($isWhitespace && $token['has_ansi']) {
                    $tracker->processText($tokenText);
                    $token['has_ansi'] = false;
                    $ending = str_replace([' ', "\t"], '', $tokenText);
                }
                // Trim trailing whitespace, then add underline reset; a break at a soft hyphen shows it
                $lineToWrap = (str_contains($currentLine, "\u{200B}") ? preg_replace('/[ \t\x{200B}]+$/u', '', $currentLine) ?? rtrim($currentLine) : rtrim($currentLine)).$ending.($hyphenate && !$isWhitespace && $currentVisibleLength + AnsiUtils::visibleWidth($hyphen) <= $width ? $hyphen : '');
                if ('' !== $lineEndReset = $tracker->getLineEndReset()) {
                    $lineToWrap .= $lineEndReset;
                }
                $wrapped[] = $lineToWrap;
                $width = \is_int($widths) ? $widths : $widths(\count($wrapped));

                // A word too long for the narrower line it goes on: broken there
                if (!$isWhitespace && $tokenVisibleLength > $width) {
                    $broken = self::breakLongWord($tokenText, self::narrowest($widths, \count($wrapped), $width), $tracker);
                    $brokenLines = $broken['lines'];
                    $lastIndex = \count($brokenLines) - 1;
                    for ($i = 0; $i < $lastIndex; ++$i) {
                        $wrapped[] = rtrim($brokenLines[$i]);
                    }
                    $width = \is_int($widths) ? $widths : $widths(\count($wrapped));
                    $currentLine = $brokenLines[$lastIndex] ?? '';
                    $currentVisibleLength = $broken['last_width'];
                    continue;
                }

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

        // Trailing whitespace kept before an ANSI reset (e.g. from a highlighted
        // blank line) can push a line past the requested width; clamp each line.
        if (!$wrapped) {
            return [''];
        }

        return \is_int($widths)
            ? array_map(static fn (string $line): string => AnsiUtils::truncateToWidth($line, $widths, ''), $wrapped)
            : array_map(static fn (string $line, int $index): string => AnsiUtils::truncateToWidth($line, $widths($index), ''), $wrapped, array_keys($wrapped));
    }

    /**
     * Splits the words wider than a line at their no-break spaces, each run of them a whitespace token.
     *
     * @param array<int, array{text: string, width: int, is_whitespace: bool, has_ansi: bool, soft_hyphen?: bool}> $tokens
     *
     * @return array<int, array{text: string, width: int, is_whitespace: bool, has_ansi: bool, soft_hyphen?: bool, break_before?: bool}>
     */
    private static function splitWideAtNoBreakSpaces(array $tokens, int $width): array
    {
        $split = [];
        foreach ($tokens as $token) {
            if ($token['is_whitespace'] || $token['width'] <= $width || !str_contains($token['text'], "\u{00A0}")) {
                $split[] = $token;
                continue;
            }
            // On a line of its own, like an inline block too wide for the rest of the line
            $first = true;
            foreach (preg_split('/((?:\x{00A0})+)/u', $token['text'], -1, \PREG_SPLIT_DELIM_CAPTURE | \PREG_SPLIT_NO_EMPTY) ?: [$token['text']] as $piece) {
                $split[] = [
                    'text' => $piece,
                    'width' => AnsiUtils::visibleWidth($piece),
                    'is_whitespace' => '' === str_replace("\u{00A0}", '', $piece),
                    'has_ansi' => str_contains($piece, "\x1b"),
                    'break_before' => $first,
                ];
                $first = false;
            }
        }

        return $split;
    }

    /**
     * The narrowest of the next lines, from one: the width the pieces of a broken word fit in, whatever line they land on.
     *
     * @param int|\Closure(int): int $widths
     */
    private static function narrowest(int|\Closure $widths, int $from, int $width): int
    {
        if (\is_int($widths)) {
            return $widths;
        }
        for ($line = $from; $line < $from + 64; ++$line) {
            $width = min($width, $widths($line));
        }

        return max(1, $width);
    }

    /**
     * Split text into tokens (words and whitespace runs) while keeping ANSI codes attached.
     *
     * @return array<int, array{text: string, width: int, is_whitespace: bool, has_ansi: bool, soft_hyphen?: bool}>
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

        // A zero-width space (<wbr>) or a soft hyphen is where a word can break: a whitespace token that takes no room
        if (str_contains($text, "\u{200B}") || str_contains($text, "\u{00AD}")) {
            $split = [];
            foreach ($tokens as $token) {
                if ($token['is_whitespace'] || (!str_contains($token['text'], "\u{200B}") && !str_contains($token['text'], "\u{00AD}"))) {
                    $split[] = $token;
                    continue;
                }
                $pieces = preg_split("/(\u{200B}|\u{00AD})/u", $token['text'], -1, \PREG_SPLIT_DELIM_CAPTURE) ?: [$token['text']];
                foreach ($pieces as $k => $piece) {
                    if (1 === $k % 2) {
                        // The soft hyphen itself is not drawn: a hyphen is added where the line breaks
                        $split[] = "\u{00AD}" === $piece
                            ? ['text' => '', 'width' => 0, 'is_whitespace' => true, 'has_ansi' => false, 'soft_hyphen' => true]
                            : ['text' => "\u{200B}", 'width' => 0, 'is_whitespace' => true, 'has_ansi' => false];
                        continue;
                    }
                    if ('' !== $piece) {
                        $split[] = ['text' => $piece, 'width' => AnsiUtils::visibleWidth($piece), 'is_whitespace' => false, 'has_ansi' => str_contains($piece, "\x1b")];
                    }
                }
            }
            $tokens = $split;
        }

        if (!preg_match(self::CJK_PATTERN, $text)) {
            return $tokens;
        }

        $splitTokens = [];
        foreach ($tokens as $token) {
            array_push($splitTokens, ...($token['is_whitespace'] || !preg_match(self::CJK_PATTERN, $token['text']) ? [$token] : self::splitCjkToken($token['text'])));
        }

        return $splitTokens;
    }

    /**
     * Split a word into one token per CJK grapheme, as a line can break between any two of them, but around a word
     * joiner.
     *
     * @return list<array{text: string, width: int, is_whitespace: bool, has_ansi: bool}>
     */
    private static function splitCjkToken(string $word): array
    {
        $pieces = [];
        $current = '';
        $pendingAnsi = '';
        $glue = false;
        $length = \strlen($word);

        for ($i = 0; $i < $length;) {
            if ("\x1b" === $word[$i] && null !== $ansi = AnsiUtils::extractAnsiCode($word, $i)) {
                $pendingAnsi .= $ansi['code'];
                $i += $ansi['length'];
                continue;
            }

            $end = strpos($word, "\x1b", $i + 1);
            $end = false === $end ? $length : $end;
            $text = substr($word, $i, $end - $i);
            $i = $end;

            foreach (grapheme_str_split($text) ?: [$text] as $grapheme) {
                // A word joiner (U+2060) keeps the characters on both sides of it together, like a ruby base
                $joined = "\u{2060}" === $grapheme || $glue;
                $glue = "\u{2060}" === $grapheme;
                if ($joined && '' === $current && $pieces) {
                    $pieces[array_key_last($pieces)] .= $pendingAnsi.$grapheme;
                } elseif (!$joined && preg_match(self::CJK_PATTERN, $grapheme)) {
                    if ('' !== $current) {
                        $pieces[] = $current;
                        $current = '';
                    }
                    $pieces[] = $pendingAnsi.$grapheme;
                } else {
                    $current .= $pendingAnsi.$grapheme;
                }
                $pendingAnsi = '';
            }
        }

        // Trailing ANSI codes stay attached to the last piece
        if ('' !== $current || !$pieces) {
            $pieces[] = $current.$pendingAnsi;
        } else {
            $pieces[array_key_last($pieces)] .= $pendingAnsi;
        }

        // A style that ends between two pieces ends with the first one, as a line can break between them
        for ($k = 1, $count = \count($pieces); $k < $count; ++$k) {
            if (preg_match(self::SGR_OFF_PATTERN, $pieces[$k], $m)) {
                $pieces[$k - 1] .= $m[0];
                $pieces[$k] = substr($pieces[$k], \strlen($m[0]));
            }
        }

        return array_map(static fn (string $piece): array => [
            'text' => $piece,
            'width' => AnsiUtils::visibleWidth($piece),
            'is_whitespace' => false,
            'has_ansi' => str_contains($piece, "\x1b"),
        ], $pieces);
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
        $pendingAnsi = '';
        foreach ($segments as $seg) {
            if ('ansi' === $seg['type']) {
                $pendingAnsi .= $seg['value'];
                continue;
            }

            if ('' === $grapheme = $seg['value']) {
                continue;
            }

            $graphemeWidth = AnsiUtils::graphemeWidth($grapheme);

            if ($currentWidth + $graphemeWidth > $width) {
                // Codes that only turn styles off end this line, the others start the next one
                if (preg_match(self::SGR_OFF_PATTERN, $pendingAnsi, $m)) {
                    $currentLine .= $m[0];
                    $tracker->processText($m[0]);
                    $pendingAnsi = substr($pendingAnsi, \strlen($m[0]));
                }
                // Add specific reset for underline only (preserves background)
                if ('' !== $lineEndReset = $tracker->getLineEndReset()) {
                    $currentLine .= $lineEndReset;
                }
                $lines[] = $currentLine;
                $currentLine = $tracker->getActiveCodes();
                $currentWidth = 0;
            }

            $currentLine .= $pendingAnsi.$grapheme;
            $currentWidth += $graphemeWidth;
            $tracker->processText($pendingAnsi);
            $pendingAnsi = '';
        }

        $currentLine .= $pendingAnsi;
        $tracker->processText($pendingAnsi);

        if ('' !== $currentLine) {
            $lines[] = $currentLine;
        }

        if (!$lines) {
            return ['lines' => [''], 'last_width' => 0];
        }

        return ['lines' => $lines, 'last_width' => $currentWidth];
    }

    /**
     * Split text into lines at CRLF, CR and LF line endings.
     *
     * A CR left in a line would move the cursor back to the start of the row and let the rest of the line overwrite it.
     *
     * @return string[]
     */
    private static function splitLines(string $text): array
    {
        return preg_split('/\r\n|\r|\n/', $text) ?: [$text];
    }
}
