<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Tui\Terminal;

use Symfony\Component\Tui\Ansi\AnsiCodeTracker;
use Symfony\Component\Tui\Ansi\AnsiUtils;

/**
 * A simple terminal emulator that interprets ANSI escape sequences
 * and maintains a screen buffer representing what the user actually sees.
 *
 * This is used in tests to convert raw terminal output (with differential
 * updates, cursor movements, etc.) into the actual rendered screen state.
 * It preserves ANSI styling (colors, bold, etc.) for accurate visual comparison.
 *
 * @experimental
 */
final class ScreenBuffer
{
    /** Runs of well-formed UTF-8 sequences, in bytes, see write(): possessive, not a backtracking frame a character */
    private const VALID_UTF8 = '/(?:[\x00-\x7F]|[\xC2-\xDF][\x80-\xBF]|\xE0[\xA0-\xBF][\x80-\xBF]|[\xE1-\xEC\xEE\xEF][\x80-\xBF]{2}|\xED[\x80-\x9F][\x80-\xBF]|\xF0[\x90-\xBF][\x80-\xBF]{2}|[\xF1-\xF3][\x80-\xBF]{3}|\xF4[\x80-\x8F][\x80-\xBF]{2})++/';
    /** The C0 control characters and DEL, handled one by one, see write() */
    private const CONTROLS = "\x00\x01\x02\x03\x04\x05\x06\x07\x08\x09\x0a\x0b\x0c\x0d\x0e\x0f\x10\x11\x12\x13\x14\x15\x16\x17\x18\x19\x1a\x1b\x1c\x1d\x1e\x1f\x7f";

    /** @var array<int, array<int, array{char: string, style: string}>> */
    private array $cells = [];
    /** @var list<array<int, array{char: string, style: string}>> */
    private array $scrollback = [];
    private int $cursorRow = 0;
    private int $cursorCol = 0;
    private int $width;
    private int $height;
    private AnsiCodeTracker $styleTracker;

    public function __construct(int $width = 80, int $height = 24)
    {
        $this->width = $width;
        $this->height = $height;
        $this->styleTracker = new AnsiCodeTracker();
        $this->clear();
    }

    /**
     * Clear the screen buffer.
     */
    public function clear(): void
    {
        $this->cells = [];
        for ($row = 0; $row < $this->height; ++$row) {
            $this->cells[$row] = [];
        }
        $this->cursorRow = 0;
        $this->cursorCol = 0;
        $this->styleTracker->reset();
    }

    /**
     * Process terminal output and update the screen buffer.
     */
    public function write(string $data): void
    {
        $i = 0;
        $len = \strlen($data);

        while ($i < $len) {
            $char = $data[$i];

            // Handle escape sequences
            if ("\x1b" === $char) {
                $i += $this->parseEscapeSequence($data, $i);
                continue;
            }

            // Handle special characters
            if ("\r" === $char) {
                $this->cursorCol = 0;
                ++$i;
                continue;
            }

            if ("\n" === $char) {
                ++$this->cursorRow;
                $this->cursorCol = 0; // Newline also resets column
                if ($this->cursorRow >= $this->height) {
                    $this->scrollUp();
                    $this->cursorRow = $this->height - 1;
                }
                ++$i;
                continue;
            }

            // Handle tab
            if ("\t" === $char) {
                $spaces = 8 - ($this->cursorCol % 8);
                for ($j = 0; $j < $spaces && $this->cursorCol < $this->width; ++$j) {
                    $this->putChar(' ');
                }
                ++$i;
                continue;
            }

            // Handle backspace (move cursor back)
            if ("\x08" === $char) {
                if ($this->cursorCol > 0) {
                    --$this->cursorCol;
                }
                ++$i;
                continue;
            }

            // Handle DEL (delete character at cursor, move cursor back)
            if ("\x7f" === $char) {
                if ($this->cursorCol > 0) {
                    --$this->cursorCol;
                    // Clear the character at the new cursor position
                    $this->cells[$this->cursorRow][$this->cursorCol] = ['char' => ' ', 'style' => ''];
                }
                ++$i;
                continue;
            }

            // Skip other control characters
            if (\ord($char) < 32 && "\x1b" !== $char) {
                ++$i;
                continue;
            }

            // Regular characters, up to the next control character, which no grapheme cluster goes past: split at once,
            // not extracted one by one from the data, which the polyfill of grapheme_extract() does in a time that grows
            // with the rest of the data, quadratic for the output of many frames
            $end = $i + strcspn($data, self::CONTROLS, $i);
            $run = substr($data, $i, $end - $i);
            $i = $end;
            // Text that is no valid UTF-8: its valid parts split, the bytes in between skipped, the same with intl and
            // without (intl skipped a byte like \xFF, its polyfill the text before it)
            $valid = preg_match('//u', $run) ?: (\PREG_BAD_UTF8_ERROR === preg_last_error() ? 0 : false);
            foreach (1 === $valid ? [$run] : self::validParts($run, 0 === $valid) as $part) {
                // Valid UTF-8, which the polyfill extracts too, when splitting it failed
                if (false === $graphemes = grapheme_str_split($part)) {
                    $this->putGraphemes($part);
                    continue;
                }
                foreach ($graphemes as $grapheme) {
                    $this->putChar($grapheme);
                }
            }
        }
    }

    /**
     * The parts of a text that are valid UTF-8, the bytes in between left out: found by a pattern, or byte by byte when
     * it fails, like past a limit of PCRE.
     *
     * @return list<string>
     */
    private static function validParts(string $text, bool $pattern = true): array
    {
        if ($pattern && false !== preg_match_all(self::VALID_UTF8, $text, $matches)) {
            return $matches[0];
        }
        $parts = [];
        $start = null;
        for ($i = 0, $length = \strlen($text); $i < $length;) {
            $byte = \ord($text[$i]);
            $size = match (true) {
                $byte < 0x80 => 1,
                $byte >= 0xC2 && $byte <= 0xDF => 2,
                $byte >= 0xE0 && $byte <= 0xEF => 3,
                $byte >= 0xF0 && $byte <= 0xF4 => 4,
                default => 0,
            };
            $valid = 0 < $size && $i + $size <= $length;
            if ($valid && 1 < $size) {
                // The second byte of some sequences is narrower, like VALID_UTF8 says: no overlong form, no surrogate
                [$low, $high] = match ($byte) {
                    0xE0 => [0xA0, 0xBF],
                    0xED => [0x80, 0x9F],
                    0xF0 => [0x90, 0xBF],
                    0xF4 => [0x80, 0x8F],
                    default => [0x80, 0xBF],
                };
                $next = \ord($text[$i + 1]);
                $valid = $next >= $low && $next <= $high;
                for ($k = 2; $valid && $k < $size; ++$k) {
                    $next = \ord($text[$i + $k]);
                    $valid = $next >= 0x80 && $next <= 0xBF;
                }
            }
            if ($valid) {
                $start ??= $i;
                $i += $size;
                continue;
            }
            if (null !== $start) {
                $parts[] = substr($text, $start, $i - $start);
                $start = null;
            }
            ++$i;
        }
        if (null !== $start) {
            $parts[] = substr($text, $start);
        }

        return $parts;
    }

    /**
     * Puts the grapheme clusters of a text that is valid UTF-8 one by one.
     */
    private function putGraphemes(string $text): void
    {
        for ($at = 0, $length = \strlen($text); $at < $length;) {
            $grapheme = grapheme_extract($text, 1, \GRAPHEME_EXTR_COUNT, $at, $next);
            if (false !== $grapheme && '' !== $grapheme) {
                $this->putChar($grapheme);
                $at = $next;
            } else {
                ++$at;
            }
        }
    }

    /**
     * Get the current screen content as a string (without styles).
     */
    public function getScreen(): string
    {
        $result = [];
        $lastNonEmpty = -1;

        for ($row = 0; $row < $this->height; ++$row) {
            if ('' !== $trimmed = rtrim($this->getLineText($row))) {
                $lastNonEmpty = $row;
            }
            $result[] = $trimmed;
        }

        // Only include lines up to the last non-empty line
        if ($lastNonEmpty >= 0) {
            $result = \array_slice($result, 0, $lastNonEmpty + 1);
        } else {
            $result = [];
        }

        return implode("\n", $result);
    }

    /**
     * Get the current screen content with ANSI styles preserved.
     */
    public function getStyledScreen(): string
    {
        $result = [];
        $lastNonEmpty = -1;

        for ($row = 0; $row < $this->height; ++$row) {
            $line = $this->getLineStyled($row);
            if (isset($this->cells[$row])) {
                foreach ($this->cells[$row] as $cell) {
                    // A space with a style that shows on it, like a background, is drawn too
                    if ((' ' !== $cell['char'] && '' !== $cell['char']) || ('' !== $cell['style'] && self::showsOnSpace($cell['style']))) {
                        $lastNonEmpty = $row;
                        break;
                    }
                }
            }
            $result[] = rtrim($line);
        }

        // Only include lines up to the last non-empty line
        if ($lastNonEmpty >= 0) {
            $result = \array_slice($result, 0, $lastNonEmpty + 1);
        } else {
            $result = [];
        }

        return implode("\n", $result);
    }

    /**
     * Whether a style is seen on a space: a background, reverse video, an underline or a line through it.
     */
    private static function showsOnSpace(string $style): bool
    {
        preg_match_all('/\e\[([0-9;:]*)m/', $style, $sequences);
        foreach ($sequences[1] as $sequence) {
            $parameters = explode(';', str_replace(':', ';', $sequence));
            for ($i = 0, $count = \count($parameters); $i < $count; ++$i) {
                $code = (int) $parameters[$i];
                if (38 === $code || 58 === $code) {
                    // A color of the text or of the underline: its parameters are not codes
                    $i += '2' === ($parameters[$i + 1] ?? null) ? 4 : 2;
                } elseif (48 === $code || 4 === $code || 7 === $code || 9 === $code || ($code >= 40 && $code <= 47) || ($code >= 100 && $code <= 107)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Get screen lines as array (without styles).
     *
     * @return string[]
     */
    public function getLines(): array
    {
        $lines = [];
        for ($row = 0; $row < $this->height; ++$row) {
            $lines[] = $this->getLineText($row);
        }

        return $lines;
    }

    /**
     * Get the lines that scrolled off the top of the screen, oldest first.
     *
     * @return string[]
     */
    public function getScrollback(): array
    {
        return array_map($this->renderCells(...), $this->scrollback);
    }

    /**
     * Get the cell data for external processing (e.g., HTML conversion).
     *
     * @return array<int, array<int, array{char: string, style: string}>>
     */
    public function getCells(): array
    {
        return $this->cells;
    }

    /**
     * Get the screen height.
     */
    public function getHeight(): int
    {
        return $this->height;
    }

    /**
     * Get a single line's text content.
     */
    private function getLineText(int $row): string
    {
        return $this->renderCells($this->cells[$row] ?? []);
    }

    /**
     * @param array<int, array{char: string, style: string}> $cells
     */
    private function renderCells(array $cells): string
    {
        if (!$cells) {
            return '';
        }

        $line = '';
        $maxCol = max(array_keys($cells));

        for ($col = 0; $col <= $maxCol; ++$col) {
            $char = $cells[$col]['char'] ?? ' ';
            // Skip wide character continuation cells (empty string placeholders)
            if ('' === $char) {
                continue;
            }
            $line .= $char;
        }

        return $line;
    }

    /**
     * Get a single line with ANSI styles.
     */
    private function getLineStyled(int $row): string
    {
        if (!isset($this->cells[$row]) || !$this->cells[$row]) {
            return '';
        }

        $line = '';
        $maxCol = max(array_keys($this->cells[$row]));

        $lastStyle = '';
        for ($col = 0; $col <= $maxCol; ++$col) {
            $cell = $this->cells[$row][$col] ?? ['char' => ' ', 'style' => ''];

            // Skip wide character continuation cells (empty string placeholders)
            if ('' === $cell['char']) {
                continue;
            }

            $style = $cell['style'];

            if ($style !== $lastStyle) {
                if ('' !== $lastStyle) {
                    $line .= "\x1b[0m"; // Reset before changing
                }
                if ('' !== $style) {
                    $line .= $style;
                }
                $lastStyle = $style;
            }

            $line .= $cell['char'];
        }

        if ('' !== $lastStyle) {
            $line .= "\x1b[0m";
        }

        return $line;
    }

    /**
     * Put a character at the current cursor position.
     */
    private function putChar(string $char): void
    {
        if ($this->cursorRow < 0 || $this->cursorRow >= $this->height) {
            return;
        }

        if (!isset($this->cells[$this->cursorRow])) {
            $this->cells[$this->cursorRow] = [];
        }

        // Fill any gaps with spaces. A row that has as many cells as the
        // cursor has columns to its left is contiguous, and the common case
        // of appending to it needs no fill at all. Once the counts disagree
        // -- an erase unsets cells in the middle of the row -- the missing
        // columns have to be asked for one by one, because counting them
        // walks the fill straight over live characters.
        if (\count($this->cells[$this->cursorRow]) !== $this->cursorCol) {
            for ($col = 0; $col < $this->cursorCol; ++$col) {
                if (!isset($this->cells[$this->cursorRow][$col])) {
                    $this->cells[$this->cursorRow][$col] = ['char' => ' ', 'style' => ''];
                }
            }
        }

        $style = $this->styleTracker->getActiveCodes();
        $charWidth = AnsiUtils::graphemeWidth($char);

        // If the wide character doesn't fit at the right edge, skip it
        if ($charWidth > 1 && $this->cursorCol + $charWidth > $this->width) {
            return;
        }

        $row = &$this->cells[$this->cursorRow];

        // Clean up wide character fragments in cells being overwritten
        for ($w = 0; $w < $charWidth; ++$w) {
            $col = $this->cursorCol + $w;
            if (!isset($row[$col])) {
                continue;
            }
            if ('' === $row[$col]['char']) {
                // This is a continuation cell, clear the wide char to its left
                if ($col > 0 && isset($row[$col - 1]) && '' !== $row[$col - 1]['char'] && ' ' !== $row[$col - 1]['char']) {
                    $row[$col - 1] = ['char' => ' ', 'style' => ''];
                }
            } elseif (' ' !== $row[$col]['char']) {
                // This cell may be a wide char, clear its continuation cell to the right
                if (isset($row[$col + 1]) && '' === $row[$col + 1]['char']) {
                    $row[$col + 1] = ['char' => ' ', 'style' => ''];
                }
            }
        }

        $row[$this->cursorCol] = [
            'char' => $char,
            'style' => $style,
        ];

        // For wide characters (e.g. CJK), mark continuation cell(s) as placeholders
        for ($w = 1; $w < $charWidth; ++$w) {
            $row[$this->cursorCol + $w] = [
                'char' => '',
                'style' => $style,
            ];
        }

        $this->cursorCol += $charWidth;
    }

    /**
     * Scroll the screen up by one line.
     */
    private function scrollUp(): void
    {
        $this->scrollback[] = array_shift($this->cells) ?? [];
        $this->cells[] = [];
    }

    /**
     * Parse an escape sequence and return the number of bytes consumed.
     */
    private function parseEscapeSequence(string $data, int $start): int
    {
        $len = \strlen($data);
        if ($start + 1 >= $len) {
            return 1;
        }

        $next = $data[$start + 1];
        $nextOrd = \ord($next);

        // CSI sequence: ESC [
        if ('[' === $next) {
            return $this->parseCsiSequence($data, $start);
        }

        // String sequences: OSC (ESC ]), DCS (ESC P), APC (ESC _), PM (ESC ^), SOS (ESC X)
        // All terminated by BEL (0x07) or ST (ESC \)
        if (']' === $next || 'P' === $next || '_' === $next || '^' === $next || 'X' === $next) {
            return $this->parseStringSequence($data, $start);
        }

        // nF announced sequences: ESC + intermediate bytes (0x20-0x2F)+ + final byte (0x30-0x7E)
        if ($nextOrd >= 0x20 && $nextOrd <= 0x2F) {
            $j = $start + 2;
            while ($j < $len && \ord($data[$j]) >= 0x20 && \ord($data[$j]) <= 0x2F) {
                ++$j;
            }
            if ($j < $len && \ord($data[$j]) >= 0x30 && \ord($data[$j]) <= 0x7E) {
                return $j + 1 - $start;
            }

            return $len - $start;
        }

        // Fe (0x40-0x5F), Fp (0x30-0x3F), Fs (0x60-0x7E) two-byte sequences
        if ($nextOrd >= 0x30 && $nextOrd <= 0x7E) {
            return 2;
        }

        // Unknown: skip the ESC byte
        return 1;
    }

    /**
     * Parse string sequence (OSC, DCS, APC, PM, SOS).
     * Format: ESC <introducer> ... ST (where ST is ESC \ or BEL).
     */
    private function parseStringSequence(string $data, int $start): int
    {
        $len = \strlen($data);
        $i = $start + 2; // Skip ESC + introducer byte

        // Find terminator: ST (ESC \) or BEL (\x07)
        while ($i < $len) {
            if ("\x07" === $data[$i]) {
                // BEL terminator
                return $i - $start + 1;
            }
            if ("\x1b" === $data[$i] && $i + 1 < $len && '\\' === $data[$i + 1]) {
                // ST terminator (ESC \)
                return $i - $start + 2;
            }
            ++$i;
        }

        // No terminator found - consume what we have
        return $len - $start;
    }

    /**
     * Parse CSI (Control Sequence Introducer) sequence.
     */
    private function parseCsiSequence(string $data, int $start): int
    {
        $len = \strlen($data);
        $i = $start + 2; // Skip ESC [

        // Collect parameter bytes (0x30-0x3F)
        $params = '';
        while ($i < $len && \ord($data[$i]) >= 0x30 && \ord($data[$i]) <= 0x3F) {
            $params .= $data[$i];
            ++$i;
        }

        // Collect intermediate bytes (0x20-0x2F)
        while ($i < $len && \ord($data[$i]) >= 0x20 && \ord($data[$i]) <= 0x2F) {
            ++$i;
        }

        // Final byte (0x40-0x7E)
        if ($i >= $len) {
            return $i - $start;
        }

        $finalByte = $data[$i];
        $consumed = $i - $start + 1;

        // Strip private mode prefix ("?") before parsing numeric parameters
        $paramStr = str_starts_with($params, '?') ? substr($params, 1) : $params;
        $nums = '' !== $paramStr ? array_map('intval', explode(';', $paramStr)) : [];

        switch ($finalByte) {
            // The cursor moves take a repeat count whose default is 1, and a
            // count of 0 is read as 1 rather than as "stay put": an omitted
            // and an explicit zero parameter mean the same thing.
            case 'A': // Cursor Up
                $n = max(1, $nums[0] ?? 1);
                $this->cursorRow = max(0, $this->cursorRow - $n);
                break;

            case 'B': // Cursor Down
                $n = max(1, $nums[0] ?? 1);
                $this->cursorRow = min($this->height - 1, $this->cursorRow + $n);
                break;

            case 'C': // Cursor Forward
                $n = max(1, $nums[0] ?? 1);
                $this->cursorCol = min($this->width - 1, $this->cursorCol + $n);
                break;

            case 'D': // Cursor Back
                $n = max(1, $nums[0] ?? 1);
                $this->cursorCol = max(0, $this->cursorCol - $n);
                break;

            case 'G': // Cursor Horizontal Absolute
                $col = ($nums[0] ?? 1) - 1; // 1-indexed
                $this->cursorCol = max(0, min($this->width - 1, $col));
                break;

            case 'H': // Cursor Position
            case 'f':
                $row = ($nums[0] ?? 1) - 1;
                $col = ($nums[1] ?? 1) - 1;
                $this->cursorRow = max(0, min($this->height - 1, $row));
                $this->cursorCol = max(0, min($this->width - 1, $col));
                break;

            case 'J': // Erase in Display
                $mode = $nums[0] ?? 0;
                $this->eraseInDisplay($mode);
                break;

            case 'K': // Erase in Line
                $mode = $nums[0] ?? 0;
                $this->eraseInLine($mode);
                break;

            case 'm': // SGR (Select Graphic Rendition)
                $this->handleSgr($paramStr);
                break;

            case 'h': // Set Mode - ignore
            case 'l': // Reset Mode - ignore
                break;
        }

        return $consumed;
    }

    /**
     * Handle SGR (Select Graphic Rendition) - colors and styles.
     */
    private function handleSgr(string $params): void
    {
        $this->styleTracker->process("\x1b[".$params.'m');
    }

    /**
     * Erase in display.
     */
    private function eraseInDisplay(int $mode): void
    {
        switch ($mode) {
            case 0: // Erase from cursor to end of screen
                $this->eraseInLine(0);
                for ($i = $this->cursorRow + 1; $i < $this->height; ++$i) {
                    $this->cells[$i] = [];
                }
                break;

            case 1: // Erase from start to cursor
                for ($i = 0; $i < $this->cursorRow; ++$i) {
                    $this->cells[$i] = [];
                }
                $this->eraseInLine(1);
                break;

            case 3: // Erase the scrollback as well
                $this->scrollback = [];
                // no break
            case 2: // Erase entire screen (but don't move cursor)
                for ($i = 0; $i < $this->height; ++$i) {
                    $this->cells[$i] = [];
                }
                break;
        }
    }

    /**
     * Erase in line.
     */
    private function eraseInLine(int $mode): void
    {
        if (!isset($this->cells[$this->cursorRow])) {
            $this->cells[$this->cursorRow] = [];

            return;
        }

        $row = &$this->cells[$this->cursorRow];

        switch ($mode) {
            case 0: // Erase from cursor to end of line
                // If cursor is on a continuation cell, also clear the wide char's main cell
                if (isset($row[$this->cursorCol]) && '' === $row[$this->cursorCol]['char']
                    && $this->cursorCol > 0 && isset($row[$this->cursorCol - 1])) {
                    unset($row[$this->cursorCol - 1]);
                }
                foreach ($row as $col => $cell) {
                    if ($col >= $this->cursorCol) {
                        unset($row[$col]);
                    }
                }
                break;

            case 1: // Erase from start of line to cursor
                foreach ($row as $col => $cell) {
                    if ($col <= $this->cursorCol) {
                        unset($row[$col]);
                    }
                }
                // If the last erased cell was a wide char's main cell, also clear its continuation
                if (isset($row[$this->cursorCol + 1]) && '' === $row[$this->cursorCol + 1]['char']) {
                    unset($row[$this->cursorCol + 1]);
                }
                break;

            case 2: // Erase entire line
                $row = [];
                break;
        }
    }
}
