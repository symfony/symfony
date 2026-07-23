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

use Symfony\Component\String\UnicodeString;
use Symfony\Component\Tui\Style\CursorShape;

/**
 * ANSI escape code utilities for terminal rendering.
 *
 * @experimental
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
final class AnsiUtils
{
    /**
     * Cursor position marker prefix.
     *
     * Widgets emit an APC marker at the cursor position when focused.
     * The full marker format is `ESC _ pi:c ; N BEL` where N is the
     * DECSCUSR parameter (cursor shape). The ScreenWriter finds this
     * marker, extracts the shape, positions the hardware cursor, and
     * sets the cursor style via `ESC [ N SP q`.
     *
     * @see cursorMarker()
     */
    public const CURSOR_MARKER_PREFIX = "\x1b_pi:c;";

    /**
     * Full SGR reset and OSC 8 reset sequence.
     */
    public const SEGMENT_RESET = "\x1b[0m\x1b]8;;\x07";

    /**
     * Combined pattern matching all ECMA-48 escape sequences in a single regex.
     * Alternation order: CSI (most common), string sequences, nF, two-byte.
     */
    private const ALL_ESC_PATTERN = '/\x1b(?:\[[\x30-\x3F]*[\x20-\x2F]*[\x40-\x7E]|[P\]_\^X][^\x07\x1b]*(?:\x07|\x1b\\\\)|[\x20-\x2F]+[\x30-\x7E]|[\x30-\x7E])/';

    /**
     * Character set for CSI parameter bytes (0x30-0x3F).
     */
    private const CSI_PARAM_CHARS = '0123456789:;<=>?';

    /**
     * Character set for CSI intermediate bytes (0x20-0x2F).
     */
    private const CSI_INTERMEDIATE_CHARS = " !\"#\$%&'()*+,-./";

    /**
     * Create a cursor marker embedding the given shape.
     *
     * The returned APC sequence is zero-width. The ScreenWriter strips
     * it, positions the hardware cursor, and sets the cursor style.
     */
    public static function cursorMarker(CursorShape $shape = CursorShape::Block): string
    {
        return self::CURSOR_MARKER_PREFIX.$shape->value."\x07";
    }

    /**
     * Calculate the visible width of a string in terminal columns.
     * ANSI escape codes are stripped before calculating width.
     */
    public static function visibleWidth(string $str): int
    {
        if ('' === $str) {
            return 0;
        }

        $len = \strlen($str);

        // Ultra-fast path: pure printable ASCII (0x20-0x7E) with no ESC, no tabs, no non-ASCII
        if (!str_contains($str, "\x1b") && !str_contains($str, "\t") && preg_match('/^[\x20-\x7E]*$/', $str)) {
            return $len;
        }

        // Fast path for ASCII + ANSI: jump between ESC sequences using strpos
        // instead of scanning byte-by-byte with ord()
        $fastWidth = 0;
        $fastPath = true;
        $i = 0;

        while ($i < $len) {
            // Find next ESC byte
            $escPos = strpos($str, "\x1b", $i);
            $segEnd = false === $escPos ? $len : $escPos;

            // Process the text segment before the ESC (or end of string)
            if ($segEnd > $i) {
                $segLen = $segEnd - $i;
                $segment = substr($str, $i, $segLen);
                if (preg_match('/^[\x20-\x7E]*$/', $segment)) {
                    // Pure printable ASCII (no tabs, no non-ASCII)
                    $fastWidth += $segLen;
                } elseif (str_contains($segment, "\t")) {
                    // Has tabs
                    $tabCount = substr_count($segment, "\t");
                    $fastWidth += $segLen - $tabCount + ($tabCount * 3);
                    $withoutTabs = str_replace("\t", '', $segment);
                    if ('' !== $withoutTabs && !preg_match('/^[\x20-\x7E]*$/', $withoutTabs)) {
                        $fastPath = false;
                        break;
                    }
                } else {
                    $fastPath = false;
                    break;
                }
            }

            if (false === $escPos) {
                break;
            }

            // Skip the ANSI escape sequence, inline CSI fast path
            if ($escPos + 1 < $len && '[' === $str[$escPos + 1]) {
                $j = $escPos + 2 + strspn($str, self::CSI_PARAM_CHARS, $escPos + 2);
                if ($j < $len && \ord($str[$j]) >= 0x40 && \ord($str[$j]) <= 0x7E) {
                    $i = $j + 1;
                    continue;
                }
            }
            if (null === $ansi = self::extractAnsiCode($str, $escPos)) {
                $fastPath = false;
                break;
            }
            $i = $escPos + $ansi['length'];
        }

        if ($fastPath) {
            return $fastWidth;
        }

        $clean = $str;

        if (str_contains($clean, "\t")) {
            $clean = str_replace("\t", '   ', $clean);
        }

        if (str_contains($clean, "\x1b")) {
            $clean = preg_replace(self::ALL_ESC_PATTERN, '', $clean) ?? $clean;
        }

        if ('' === $clean) {
            return 0;
        }

        if (false === preg_match('//u', $clean)) {
            $clean = @iconv('UTF-8', 'UTF-8//IGNORE', $clean) ?: '';
        }

        if ('' === $clean) {
            return 0;
        }

        return mb_strwidth($clean, 'UTF-8');
    }

    /**
     * Strip all ANSI escape codes from a string.
     */
    public static function stripAnsiCodes(string $str): string
    {
        if (!str_contains($str, "\x1b")) {
            return $str;
        }

        // Strip all ECMA-48 escape sequences using a single combined regex
        return preg_replace(self::ALL_ESC_PATTERN, '', $str) ?? $str;
    }

    /**
     * Extract ANSI escape sequence at the given position.
     *
     * Handles all ECMA-48 sequence types:
     * - CSI: ESC [ params intermediates final
     * - String sequences: OSC (ESC ]), DCS (ESC P), APC (ESC _), PM (ESC ^), SOS (ESC X)
     * - nF announced: ESC intermediates(0x20-0x2F)+ final(0x30-0x7E)
     * - Fe/Fp/Fs two-byte: ESC + byte in 0x30-0x7E
     *
     * @return array{code: string, length: int}|null
     */
    public static function extractAnsiCode(string $str, int $pos): ?array
    {
        $len = \strlen($str);
        if ($pos >= $len || "\x1b" !== $str[$pos]) {
            return null;
        }

        if ($pos + 1 >= $len) {
            return null;
        }

        $next = $str[$pos + 1];

        // CSI sequence: ESC [ <parameter bytes 0x30-0x3F>* <intermediate bytes 0x20-0x2F>* <final byte 0x40-0x7E>
        if ('[' === $next) {
            // Use strspn for C-level scanning of parameter bytes
            $j = $pos + 2 + strspn($str, self::CSI_PARAM_CHARS, $pos + 2);
            // Check final byte, skip intermediate scan if already in final range (common case)
            if ($j < $len && \ord($str[$j]) >= 0x40 && \ord($str[$j]) <= 0x7E) {
                return ['code' => substr($str, $pos, $j + 1 - $pos), 'length' => $j + 1 - $pos];
            }
            // Rare: scan intermediate bytes (0x20-0x2F) then check final byte
            if ($j < $len && \ord($str[$j]) >= 0x20 && \ord($str[$j]) <= 0x2F) {
                $j += strspn($str, self::CSI_INTERMEDIATE_CHARS, $j);
                if ($j < $len && \ord($str[$j]) >= 0x40 && \ord($str[$j]) <= 0x7E) {
                    return ['code' => substr($str, $pos, $j + 1 - $pos), 'length' => $j + 1 - $pos];
                }
            }

            return null;
        }

        // String sequences: OSC (ESC ]), DCS (ESC P), APC (ESC _), PM (ESC ^), SOS (ESC X)
        // All terminated by BEL (0x07) or ST (ESC \)
        if (\in_array($next, [']', 'P', '_', '^', 'X'], true)) {
            $j = $pos + 2;
            while ($j < $len) {
                // Skip ahead to next BEL or ESC using strcspn (C-level scan)
                $j += strcspn($str, "\x07\x1b", $j);
                if ($j >= $len) {
                    break;
                }
                if ("\x07" === $str[$j]) {
                    return ['code' => substr($str, $pos, $j + 1 - $pos), 'length' => $j + 1 - $pos];
                }
                if ("\x1b" === $str[$j] && isset($str[$j + 1]) && '\\' === $str[$j + 1]) {
                    return ['code' => substr($str, $pos, $j + 2 - $pos), 'length' => $j + 2 - $pos];
                }
                ++$j;
            }

            return null;
        }

        $nextOrd = \ord($next);

        // nF announced sequences: ESC + intermediate bytes (0x20-0x2F)+ + final byte (0x30-0x7E)
        // e.g., ESC ( B = G0 charset designation
        if ($nextOrd >= 0x20 && $nextOrd <= 0x2F) {
            $j = $pos + 2 + strspn($str, self::CSI_INTERMEDIATE_CHARS, $pos + 2);
            // Final byte must be in 0x30-0x7E
            if ($j < $len && \ord($str[$j]) >= 0x30 && \ord($str[$j]) <= 0x7E) {
                return ['code' => substr($str, $pos, $j + 1 - $pos), 'length' => $j + 1 - $pos];
            }

            return null;
        }

        // Fe (0x40-0x5F), Fp (0x30-0x3F), Fs (0x60-0x7E) two-byte sequences
        // e.g., ESC D = IND, ESC M = RI, ESC 7 = DECSC, ESC 8 = DECRC, ESC c = RIS
        if ($nextOrd >= 0x30 && $nextOrd <= 0x7E) {
            return ['code' => substr($str, $pos, 2), 'length' => 2];
        }

        return null;
    }

    /**
     * Truncate text to fit within a maximum visible width, adding ellipsis if needed.
     *
     * @param string $text     Text to truncate (may contain ANSI codes)
     * @param int    $maxWidth Maximum visible width
     * @param string $ellipsis Ellipsis string to append when truncating
     * @param bool   $pad      If true, pad result with spaces to exactly maxWidth
     */
    public static function truncateToWidth(string $text, int $maxWidth, string $ellipsis = '...', bool $pad = false): string
    {
        $textVisibleWidth = self::visibleWidth($text);

        if ($textVisibleWidth <= $maxWidth) {
            return $pad ? $text.str_repeat(' ', $maxWidth - $textVisibleWidth) : $text;
        }

        // Fast path: pure ASCII ellipsis width = strlen (avoids visibleWidth overhead)
        $ellipsisWidth = '' !== $ellipsis && !str_contains($ellipsis, "\x1b") && preg_match('/^[\x20-\x7E]*$/', $ellipsis)
            ? \strlen($ellipsis)
            : self::visibleWidth($ellipsis);
        $targetWidth = $maxWidth - $ellipsisWidth;

        if ($targetWidth <= 0) {
            return '' === $ellipsis || $maxWidth <= 0 ? '' : self::truncateToWidth($ellipsis, $maxWidth, '', $pad);
        }

        // Fast path: pure printable ASCII, direct substr avoids sliceByColumn overhead
        if ($textVisibleWidth === \strlen($text) && preg_match('/^[\x20-\x7E]*$/', $text)) {
            $truncated = substr($text, 0, $targetWidth).$ellipsis;

            if ($pad) {
                return $truncated.str_repeat(' ', max(0, $maxWidth - $targetWidth - $ellipsisWidth));
            }

            return $truncated;
        }

        $result = self::sliceByColumn($text, 0, $targetWidth);

        // Add reset code before ellipsis to prevent styling leaking into it
        $truncated = $result."\x1b[0m".$ellipsis;

        if ($pad) {
            $truncatedWidth = self::visibleWidth($truncated);

            return $truncated.str_repeat(' ', max(0, $maxWidth - $truncatedWidth));
        }

        return $truncated;
    }

    /**
     * Extract a range of visible columns from a line.
     * Handles ANSI codes and wide characters.
     *
     * @param bool $strict If true, exclude wide chars at boundary that would extend past the range
     */
    public static function sliceByColumn(string $line, int $startCol, int $length, bool $strict = false): string
    {
        // Optimized path for startCol=0 (prefix extraction), skip pendingAnsi tracking
        if (0 === $startCol && !$strict) {
            return self::slicePrefix($line, $length);
        }

        return self::sliceWithWidth($line, $startCol, $length, $strict)['text'];
    }

    /**
     * Extract a range of visible columns from a line, also returning actual width.
     *
     * @return array{text: string, width: int}
     */
    public static function sliceWithWidth(string $line, int $startCol, int $length, bool $strict = false): array
    {
        if ($length <= 0) {
            return ['text' => '', 'width' => 0];
        }

        $endCol = $startCol + $length;
        $result = '';
        $resultWidth = 0;
        $currentCol = 0;
        $i = 0;
        $pendingAnsi = '';
        $lineLen = \strlen($line);

        while ($i < $lineLen) {
            // Handle ANSI escape sequences
            if ("\x1b" === $line[$i]) {
                // Inline CSI fast path to avoid extractAnsiCode call overhead
                if ($i + 1 < $lineLen && '[' === $line[$i + 1]) {
                    $j = $i + 2 + strspn($line, self::CSI_PARAM_CHARS, $i + 2);
                    if ($j < $lineLen && \ord($line[$j]) >= 0x40 && \ord($line[$j]) <= 0x7E) {
                        $code = substr($line, $i, $j + 1 - $i);
                        if ($currentCol >= $startCol && $currentCol < $endCol) {
                            $result .= $code;
                        } elseif ($currentCol < $startCol) {
                            $pendingAnsi .= $code;
                        }
                        $i = $j + 1;
                        continue;
                    }
                }
                $ansi = self::extractAnsiCode($line, $i);
                if (null !== $ansi) {
                    if ($currentCol >= $startCol && $currentCol < $endCol) {
                        $result .= $ansi['code'];
                    } elseif ($currentCol < $startCol) {
                        $pendingAnsi .= $ansi['code'];
                    }
                    $i += $ansi['length'];
                    continue;
                }
            }

            // Find the next ESC byte or end of string
            $textEnd = strpos($line, "\x1b", $i + 1);
            if (false === $textEnd) {
                $textEnd = $lineLen;
            }

            // Process text segment between ANSI codes
            // Fast path: check if segment is pure printable ASCII (0x20-0x7E)
            $segLen = $textEnd - $i;
            $segment = substr($line, $i, $segLen);

            if ('' === $segment || preg_match('/^[\x20-\x7E]*$/', $segment)) {
                // ASCII fast path: each byte is exactly 1 column wide
                // Use substr for bulk extraction when possible
                $segEndCol = $currentCol + $segLen;

                if ($segEndCol <= $startCol) {
                    // Entire segment is before range, skip it
                    $currentCol = $segEndCol;
                } elseif ($currentCol >= $startCol && $segEndCol <= $endCol) {
                    // Entire segment is within range, take it all
                    if ('' !== $pendingAnsi) {
                        $result .= $pendingAnsi;
                        $pendingAnsi = '';
                    }
                    $result .= $segment;
                    $resultWidth += $segLen;
                    $currentCol = $segEndCol;
                } else {
                    // Segment partially overlaps, extract the overlap
                    $skipChars = (int) max(0, $startCol - $currentCol);
                    $takeChars = (int) min($segLen - $skipChars, $endCol - max($currentCol, $startCol));

                    if ($takeChars > 0) {
                        if ('' !== $pendingAnsi) {
                            $result .= $pendingAnsi;
                            $pendingAnsi = '';
                        }
                        $result .= substr($segment, $skipChars, $takeChars);
                        $resultWidth += $takeChars;
                    }
                    $currentCol = $segEndCol;
                }
            } else {
                // Unicode path
                $textPortion = substr($line, $i, $segLen);

                // Fast check: if the entire segment fits within range, use mb_strwidth
                // to skip expensive grapheme_str_split + per-grapheme iteration.
                // mb_strwidth may overcount for ZWJ sequences; conservative check.
                $segWidth = mb_strwidth($textPortion, 'UTF-8');
                if ($currentCol >= $startCol && $currentCol + $segWidth <= $endCol) {
                    if ('' !== $pendingAnsi) {
                        $result .= $pendingAnsi;
                        $pendingAnsi = '';
                    }
                    $result .= $textPortion;
                    $resultWidth += $segWidth;
                    $currentCol += $segWidth;
                } else {
                    // Per-grapheme path for boundary-spanning segments
                    $graphemes = grapheme_str_split($textPortion) ?: [];

                    foreach ($graphemes as $grapheme) {
                        $w = self::graphemeWidth($grapheme);
                        $inRange = $currentCol >= $startCol && $currentCol < $endCol;
                        $fits = !$strict || ($currentCol + $w <= $endCol);

                        if ($inRange && $fits) {
                            if ('' !== $pendingAnsi) {
                                $result .= $pendingAnsi;
                                $pendingAnsi = '';
                            }
                            $result .= $grapheme;
                            $resultWidth += $w;
                        }
                        $currentCol += $w;

                        if ($currentCol >= $endCol) {
                            break;
                        }
                    }
                }
            }

            $i = $textEnd;

            if ($currentCol >= $endCol) {
                break;
            }
        }

        /* @var int $resultWidth */
        return ['text' => $result, 'width' => $resultWidth];
    }

    /**
     * Calculate the display width of a single grapheme in terminal columns.
     *
     * Uses mb_strwidth() for single-codepoint graphemes (fast C-level call),
     * falling back to UnicodeString::width() for multi-codepoint graphemes
     * (ZWJ emoji sequences, skin tone modifiers, decomposed combining chars)
     * where mb_strwidth() overcounts by summing component widths.
     */
    public static function graphemeWidth(string $grapheme): int
    {
        if (1 === mb_strlen($grapheme, 'UTF-8')) {
            return mb_strwidth($grapheme, 'UTF-8');
        }

        return new UnicodeString($grapheme)->width(false);
    }

    /**
     * Check if a character is whitespace.
     */
    public static function isWhitespace(string $char): bool
    {
        return preg_match('/\s/', $char);
    }

    /**
     * Check if a character is punctuation.
     */
    public static function isPunctuation(string $char): bool
    {
        return preg_match('/[(){}[\]<>.,;:\'"!?+\-=*\/\\\\|&%^$#@~`]/', $char);
    }

    /**
     * Reapply a background SGR code after reset sequences.
     */
    public static function reapplyBackgroundAfterResets(string $text, string $backgroundCode): string
    {
        // Fast path: no escape sequences at all
        if (!str_contains($text, "\x1b")) {
            return $text;
        }

        return preg_replace_callback('/\x1b\[([\d;]*)m/', static function (array $m) use ($backgroundCode): string {
            $params = $m[1];

            // Fast path: common reset sequences
            if ('' === $params || '0' === $params) {
                return $m[0].$backgroundCode;
            }

            // Check for '49' (background reset) or '0' in compound sequences
            if (str_contains($params, '49') || str_contains($params, '0')) {
                $parts = explode(';', $params);
                if (\in_array('0', $parts, true) || \in_array('49', $parts, true)) {
                    return $m[0].$backgroundCode;
                }
            }

            return $m[0];
        }, $text) ?? $text;
    }

    /**
     * Check if a line contains image escape sequences.
     */
    public static function containsImage(string $line): bool
    {
        return str_contains($line, "\x1b_G") || str_contains($line, "\x1b]1337;File=");
    }

    /**
     * Extract a prefix of visible columns from a line (startCol=0 specialization).
     * Skips pendingAnsi tracking since all ANSI codes are in range from the start.
     */
    private static function slicePrefix(string $line, int $length): string
    {
        if ($length <= 0) {
            return '';
        }

        $result = '';
        $currentCol = 0;
        $i = 0;
        $lineLen = \strlen($line);

        while ($i < $lineLen && $currentCol < $length) {
            if ("\x1b" === $line[$i]) {
                // Inline CSI fast path
                if ($i + 1 < $lineLen && '[' === $line[$i + 1]) {
                    $j = $i + 2 + strspn($line, self::CSI_PARAM_CHARS, $i + 2);
                    if ($j < $lineLen && \ord($line[$j]) >= 0x40 && \ord($line[$j]) <= 0x7E) {
                        $result .= substr($line, $i, $j + 1 - $i);
                        $i = $j + 1;
                        continue;
                    }
                }
                if (null !== $ansi = self::extractAnsiCode($line, $i)) {
                    $result .= $ansi['code'];
                    $i += $ansi['length'];
                    continue;
                }
            }

            // Find next ESC or end of string
            if (false === $textEnd = strpos($line, "\x1b", $i + 1)) {
                $textEnd = $lineLen;
            }

            $segLen = $textEnd - $i;
            $segment = substr($line, $i, $segLen);

            if ('' === $segment || preg_match('/^[\x20-\x7E]*$/', $segment)) {
                // ASCII: take up to remaining columns
                $take = min($segLen, $length - $currentCol);
                if ($take === $segLen) {
                    $result .= $segment;
                } else {
                    $result .= substr($segment, 0, $take);
                }
                $currentCol += $take;
            } else {
                // Unicode path
                $segWidth = mb_strwidth($segment, 'UTF-8');
                if ($currentCol + $segWidth <= $length) {
                    $result .= $segment;
                    $currentCol += $segWidth;
                } else {
                    $graphemes = grapheme_str_split($segment) ?: [];
                    foreach ($graphemes as $grapheme) {
                        $w = self::graphemeWidth($grapheme);
                        if ($currentCol + $w > $length) {
                            break;
                        }
                        $result .= $grapheme;
                        $currentCol += $w;
                    }
                }
            }

            $i = $textEnd;
        }

        return $result;
    }
}
