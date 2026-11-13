<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\TuiHtml\Render;

use Symfony\Component\Tui\Ansi\AnsiUtils;
use Symfony\Component\Tui\Exception\InvalidArgumentException;

/**
 * A 2D grid of terminal cells for efficient compositing and rendering.
 *
 * Uses flat parallel arrays (not objects) for memory efficiency.
 * Each cell stores: character (grapheme), display width, foreground color,
 * background color, and text attributes (bold, italic, etc.).
 *
 * ## Usage
 *
 * Create a buffer, write ANSI-styled lines into regions, then serialize
 * back to ANSI strings.
 *
 * @experimental
 *
 * @internal
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
final class CellBuffer
{
    // Attribute bitmask constants
    public const ATTR_BOLD = 1;
    public const ATTR_DIM = 2;
    public const ATTR_ITALIC = 4;
    public const ATTR_UNDERLINE = 8;
    public const ATTR_BLINK = 16;
    public const ATTR_REVERSE = 32;
    public const ATTR_STRIKETHROUGH = 64;
    /**
     * Flat arrays indexed by (row * width + col).
     *
     * @var string[] Character/grapheme at each cell
     */
    private array $chars;
    /** @var int[] Display width of each cell (1 for normal, 2 for CJK, 0 for continuation) */
    private array $widths;
    /** @var string[] Foreground color code (e.g., "38;2;255;0;0") or "" for default */
    private array $fg;
    /** @var string[] Background color code (e.g., "48;2;30;30;46") or "" for default */
    private array $bg;
    /** @var int[] Attribute bitmask (bold|dim|italic|underline|blink|reverse|strikethrough) */
    private array $attrs;
    /** @var string[] The OSC 8 hyperlink of each cell, its parameters and URI ("params;uri"), or "" for none */
    private array $links;
    /* Whether a wide character has been placed, so writes have to repair cell pairs */
    private bool $hasWideChars = false;
    /* Row of the cursor marker, or null if not found */
    private ?int $cursorRow = null;
    /* Column (cell index) of the cursor marker, or null if not found */
    private ?int $cursorCol = null;

    public function __construct(private readonly int $width, private readonly int $height)
    {
        if ($width < 1 || $height < 1) {
            throw new InvalidArgumentException(\sprintf('CellBuffer dimensions must be at least 1x1, got %dx%d', $width, $height));
        }
        $size = $width * $height;
        $this->chars = array_fill(0, $size, ' ');
        $this->widths = array_fill(0, $size, 1);
        $this->fg = array_fill(0, $size, '');
        $this->bg = array_fill(0, $size, '');
        $this->attrs = array_fill(0, $size, 0);
        $this->links = array_fill(0, $size, '');
    }

    public function getWidth(): int
    {
        return $this->width;
    }

    /**
     * The cells turned for a vertical writing mode: each row a column, one character a row, the first row the
     * rightmost column (or the leftmost, left to right), each column as wide as its widest character.
     *
     * Their colors, attributes and links are kept; a row's trailing blank cells are left out.
     */
    public function transpose(bool $rightToLeft = true): self
    {
        // The characters of each row, without the halves of wide ones and the blank end
        $columns = [];
        for ($row = 0; $row < $this->height; ++$row) {
            $cells = [];
            for ($col = 0; $col < $this->width; ++$col) {
                $i = $row * $this->width + $col;
                if (0 !== $this->widths[$i]) {
                    $cells[] = $i;
                }
            }
            while ($cells && ' ' === $this->chars[end($cells)] && '' === $this->bg[end($cells)]) {
                array_pop($cells);
            }
            $columns[] = $cells;
        }
        $pitches = [];
        foreach ($columns as $index => $cells) {
            $pitches[$index] = 1;
            foreach ($cells as $i) {
                $pitches[$index] = max($pitches[$index], $this->widths[$i]);
            }
        }
        $width = max(1, array_sum($pitches));
        $height = max(1, ...array_map('count', $columns));
        $turned = new self($width, $height);
        $turned->hasWideChars = $this->hasWideChars;
        $x = $rightToLeft ? $width : 0;
        foreach ($columns as $index => $cells) {
            $pitch = $pitches[$index];
            if ($rightToLeft) {
                $x -= $pitch;
            }
            foreach ($cells as $y => $i) {
                $j = $y * $width + $x;
                $turned->chars[$j] = $this->chars[$i];
                $turned->widths[$j] = $this->widths[$i];
                $turned->fg[$j] = $this->fg[$i];
                $turned->bg[$j] = $this->bg[$i];
                $turned->attrs[$j] = $this->attrs[$i];
                $turned->links[$j] = $this->links[$i];
                if (2 === $this->widths[$i] && $pitch > 1) {
                    $turned->widths[$j + 1] = 0;
                    $turned->bg[$j + 1] = $this->bg[$i];
                    $turned->links[$j + 1] = $this->links[$i];
                } elseif ($pitch > 1) {
                    // A narrow character in a column of wide ones: the column's other cell in its background
                    $turned->bg[$j + 1] = $this->bg[$i];
                }
            }
            if (!$rightToLeft) {
                $x += $pitch;
            }
        }

        return $turned;
    }

    public function getHeight(): int
    {
        return $this->height;
    }

    /**
     * Write ANSI-formatted lines into the buffer at the given position.
     *
     * Lines are parsed: ANSI escape codes are interpreted and stored as
     * cell attributes; visible characters are placed into the grid.
     *
     * @param array<int, string> $lines       ANSI-formatted lines
     * @param int                $startRow    Row offset to start writing
     * @param int                $startCol    Column offset to start writing
     * @param bool               $transparent When true, cells with no explicit background preserve
     *                                        the existing buffer background (transparency). Cells that
     *                                        are spaces with default bg/attrs, whatever their fg, are fully transparent
     *                                        and leave the buffer cell entirely unchanged.
     * @param string|null        $seeThrough  A background, as SGR parameters like 48;2;255;255;255, that a transparent layer
     *                                        treats as none: the one it inherited from what it is drawn in
     */
    public function writeAnsiLines(array $lines, int $startRow = 0, int $startCol = 0, bool $transparent = false, ?string $seeThrough = null): void
    {
        $width = $this->width;
        $height = $this->height;
        $startCol = max(0, $startCol);
        foreach ($lines as $lineIndex => $line) {
            $row = $startRow + $lineIndex;
            if ($row < 0 || $row >= $height) {
                continue;
            }
            // Reset SGR state at the start of each line.
            // Widget render methods produce independent lines, each with their
            // own SGR codes; state must not leak between lines.
            $fgState = '';
            $bgState = '';
            $attrState = 0;
            $linkState = '';
            $col = $startCol;
            // Where the last cell skipped as see-through ends: no character of this line before it
            $skippedTo = -1;
            $i = 0;
            $len = \strlen($line);
            $rowOffset = $row * $width;
            $hasHighByte = (bool) preg_match('/[\x80-\xFF]/', $line);
            while ($i < $len && $col < $width) {
                $ord = \ord($line[$i]);
                // Fast path: ASCII printable (0x20-0x7E), most common case.
                // A following high byte may be a combining mark that belongs
                // to this character, so leave that case to grapheme_extract().
                if ($ord >= 0x20 && $ord <= 0x7E && (!$hasHighByte || !isset($line[$i + 1]) || \ord($line[$i + 1]) < 0x80)) {
                    // In transparent mode, skip the spaces that draw nothing: no background nor attribute, a foreground
                    // color alone not being seen (fully transparent cell)
                    if ($transparent && ' ' === $line[$i] && '' === $bgState && 0 === $attrState) {
                        $skippedTo = ++$col;
                        ++$i;
                        continue;
                    }
                    if ($this->hasWideChars) {
                        $this->blankWideCharAt($rowOffset, $col);
                    }
                    $idx = $rowOffset + $col;
                    $this->chars[$idx] = $line[$i];
                    $this->widths[$idx] = 1;
                    $this->fg[$idx] = $fgState;
                    $this->bg[$idx] = $transparent && '' === $bgState ? $this->bg[$idx] : $bgState;
                    $this->attrs[$idx] = $attrState;
                    $this->links[$idx] = $linkState;
                    ++$col;
                    ++$i;
                    continue;
                }
                // Escape sequence
                if (0x1B === $ord) {
                    // Inline escape sequence parsing (avoids AnsiUtils::extractAnsiCode overhead)
                    $next = $line[$i + 1] ?? '';
                    if ('[' === $next) {
                        // CSI sequence: ESC [ <param bytes 0x30-0x3F>* <intermediate bytes 0x20-0x2F>* <final byte 0x40-0x7E>
                        $j = $i + 2;
                        while ($j < $len && \ord($line[$j]) >= 0x30 && \ord($line[$j]) <= 0x3F) {
                            ++$j;
                        }
                        while ($j < $len && \ord($line[$j]) >= 0x20 && \ord($line[$j]) <= 0x2F) {
                            ++$j;
                        }
                        if ($j >= $len || \ord($line[$j]) < 0x40 || \ord($line[$j]) > 0x7E) {
                            // Malformed CSI, skip ESC and [ entirely
                            $i = $j;
                            continue;
                        }
                        $seqEnd = $j + 1;
                        // Only parse SGR (ends with 'm')
                        if ('m' === $line[$j]) {
                            $this->parseSgrInline($line, $i + 2, $j, $fgState, $bgState, $attrState);
                            if ($transparent && $bgState === $seeThrough) {
                                $bgState = '';
                            }
                        }
                        $i = $seqEnd;
                        continue;
                    }
                    if ('_' === $next) {
                        // APC sequence: ESC _ ... BEL or ESC _ ... ST
                        $j = $i + 2;
                        $apcEnd = null;
                        while ($j < $len) {
                            if ("\x07" === $line[$j]) {
                                $apcEnd = $j + 1;
                                break;
                            }
                            if ("\x1b" === $line[$j] && isset($line[$j + 1]) && '\\' === $line[$j + 1]) {
                                $apcEnd = $j + 2;
                                break;
                            }
                            ++$j;
                        }
                        if (null === $apcEnd) {
                            ++$i;
                            continue;
                        }
                        // Check for cursor marker: ESC _ p i : c
                        if ($i + 5 < $len && 'p' === $line[$i + 2] && 'i' === $line[$i + 3] && ':' === $line[$i + 4] && 'c' === $line[$i + 5]) {
                            $this->cursorRow = $row;
                            $this->cursorCol = $col;
                        }
                        $i = $apcEnd;
                        continue;
                    }
                    // String sequences: OSC (ESC ]), DCS (ESC P), PM (ESC ^), SOS (ESC X)
                    if (']' === $next || 'P' === $next || '^' === $next || 'X' === $next) {
                        $j = $i + 2;
                        while ($j < $len) {
                            $end = "\x07" === $line[$j] ? 1 : ("\x1b" === $line[$j] && isset($line[$j + 1]) && '\\' === $line[$j + 1] ? 2 : 0);
                            if ($end > 0) {
                                // An OSC 8 hyperlink: the cells after it link to its URI, until one without a URI
                                if (']' === $next && str_starts_with($body = substr($line, $i + 2, $j - $i - 2), '8;') && \count($parts = explode(';', substr($body, 2), 2)) > 1) {
                                    $linkState = '' === $parts[1] ? '' : substr($body, 2);
                                }
                                $i = $j + $end;
                                break;
                            }
                            ++$j;
                        }
                        if ($j >= $len) {
                            ++$i;
                        }
                        continue;
                    }
                    if ('' === $next) {
                        ++$i;
                        continue;
                    }
                    $nextOrd = \ord($next);
                    // nF announced sequences: ESC + intermediate bytes (0x20-0x2F)+ + final byte (0x30-0x7E)
                    if ($nextOrd >= 0x20 && $nextOrd <= 0x2F) {
                        $j = $i + 2;
                        while ($j < $len && \ord($line[$j]) >= 0x20 && \ord($line[$j]) <= 0x2F) {
                            ++$j;
                        }
                        if ($j < $len && \ord($line[$j]) >= 0x30 && \ord($line[$j]) <= 0x7E) {
                            $i = $j + 1;
                        } else {
                            ++$i;
                        }
                        continue;
                    }
                    // Fe (0x40-0x5F), Fp (0x30-0x3F), Fs (0x60-0x7E) two-byte sequences
                    if ($nextOrd >= 0x30 && $nextOrd <= 0x7E) {
                        $i += 2;
                        continue;
                    }
                    // Unknown escape, skip ESC byte
                    ++$i;
                    continue;
                }
                // Tab
                if (0x9 === $ord) {
                    for ($s = 0; $s < AnsiUtils::TAB_WIDTH && $col < $width; ++$s) {
                        // Like a space, whatever its foreground
                        if ($transparent && '' === $bgState && 0 === $attrState) {
                            $skippedTo = ++$col;
                            continue;
                        }
                        if ($this->hasWideChars) {
                            $this->blankWideCharAt($rowOffset, $col);
                        }
                        $idx = $rowOffset + $col;
                        $this->chars[$idx] = ' ';
                        $this->widths[$idx] = 1;
                        $this->fg[$idx] = $fgState;
                        $this->bg[$idx] = $transparent && '' === $bgState ? $this->bg[$idx] : $bgState;
                        $this->attrs[$idx] = $attrState;
                        $this->links[$idx] = $linkState;
                        ++$col;
                    }
                    ++$i;
                    continue;
                }
                // Other control characters, skip
                if ($ord < 0x20) {
                    ++$i;
                    continue;
                }
                // Multi-byte / Unicode: use grapheme_extract for correctness
                $grapheme = grapheme_extract($line, 1, \GRAPHEME_EXTR_COUNT, $i, $nextPos);
                if (false === $grapheme || '' === $grapheme) {
                    ++$i;
                    continue;
                }
                // Calculate display width
                $charWidth = AnsiUtils::graphemeWidth($grapheme);
                // A zero-width grapheme gets no cell of its own: it joins the character before it, and is dropped at the start of a row
                // or of the line written, or after a cell it skipped, which hold no character of it before it: the one under it has its own
                if (0 === $charWidth) {
                    if ($col > $startCol && $col !== $skippedTo) {
                        $lead = $rowOffset + $col - 1;
                        while ($lead > $rowOffset && 0 === $this->widths[$lead]) {
                            --$lead;
                        }
                        $this->chars[$lead] .= $grapheme;
                    }
                    $i = $nextPos;
                    continue;
                }
                // Check if it fits
                if ($col + $charWidth > $width) {
                    while ($col < $width) {
                        if ($this->hasWideChars) {
                            $this->blankWideCharAt($rowOffset, $col);
                        }
                        $idx = $rowOffset + $col;
                        $this->chars[$idx] = ' ';
                        $this->widths[$idx] = 1;
                        $this->fg[$idx] = $fgState;
                        $this->bg[$idx] = $transparent && '' === $bgState ? $this->bg[$idx] : $bgState;
                        $this->attrs[$idx] = $attrState;
                        $this->links[$idx] = $linkState;
                        ++$col;
                    }
                    $i = $nextPos;
                    continue;
                }
                // Place the character
                if ($this->hasWideChars) {
                    for ($w = 0; $w < $charWidth && $col + $w < $width; ++$w) {
                        $this->blankWideCharAt($rowOffset, $col + $w);
                    }
                }
                $this->hasWideChars = $this->hasWideChars || $charWidth > 1;
                $idx = $rowOffset + $col;
                $this->chars[$idx] = $grapheme;
                $this->widths[$idx] = $charWidth;
                $this->fg[$idx] = $fgState;
                $this->bg[$idx] = $transparent && '' === $bgState ? $this->bg[$idx] : $bgState;
                $this->attrs[$idx] = $attrState;
                $this->links[$idx] = $linkState;
                // For wide characters, mark continuation cell(s)
                for ($w = 1; $w < $charWidth; ++$w) {
                    if ($col + $w < $width) {
                        $contIdx = $rowOffset + $col + $w;
                        $this->chars[$contIdx] = '';
                        $this->widths[$contIdx] = 0;
                        $this->fg[$contIdx] = $fgState;
                        $this->bg[$contIdx] = $transparent && '' === $bgState ? $this->bg[$contIdx] : $bgState;
                        $this->attrs[$contIdx] = $attrState;
                    }
                }
                $col += $charWidth;
                $i = $nextPos;
            }
        }
    }

    /**
     * Sets the background of a range of cells of a row, keeping their characters and their other attributes.
     *
     * @param string $background A background color code, like "48;2;30;30;46"
     */
    public function tint(int $row, int $col, int $columns, string $background): void
    {
        if ($row < 0 || $row >= $this->height) {
            return;
        }
        for ($i = max(0, $col), $end = min($this->width, $col + $columns); $i < $end; ++$i) {
            $this->bg[$row * $this->width + $i] = $background;
        }
    }

    /**
     * Get the cursor position found during parsing, if any.
     *
     * @return array{row: int, col: int}|null
     */
    public function getCursorPosition(): ?array
    {
        if (null === $this->cursorRow || null === $this->cursorCol) {
            return null;
        }

        return ['row' => $this->cursorRow, 'col' => $this->cursorCol];
    }

    /**
     * Clear the cursor position.
     */
    public function clearCursorPosition(): void
    {
        $this->cursorRow = null;
        $this->cursorCol = null;
    }

    /**
     * Joins the lines of boxes on a row with those that were under them, like the border of a tab drawn over the line
     * under the tabs: each glyph takes the lines of the one under it too, then drops a line to its left or right that
     * nothing goes on with there, or that goes on in a glyph drawn in the color of its background, which shows nothing.
     *
     * @param array<int, string> $under      The glyphs of boxes that were under the row, by column
     * @param string|null        $background The background the cells without one are drawn on, as SGR parameters
     */
    public function joinBoxRow(int $row, array $under, ?string $background = null): void
    {
        $background ??= '';
        $arms = LayoutEngine::BOX_ARMS;
        $offset = $row * $this->width;
        // The lines each cell shows: none for a glyph in the color of its background
        $shown = [];
        $joined = [];
        for ($col = 0; $col < $this->width; ++$col) {
            $idx = $offset + $col;
            $glyph = $this->chars[$idx];
            if (!isset($arms[$glyph])) {
                continue;
            }
            $joined[$col] = $arms[$glyph] | (isset($under[$col], $arms[$under[$col]]) ? $arms[$under[$col]] : 0);
            $shown[$col] = !self::sameColor($this->fg[$idx], '' !== $this->bg[$idx] ? $this->bg[$idx] : $background);
            // A corner drawn in the color of a hidden side, under a side shown: in the color of that side, which goes
            // down to the line, like CSS splits a corner between its sides
            $above = $idx - $this->width;
            if (!$shown[$col] && $row > 0 && 0 !== ($arms[$glyph] & 0b1000) && 0 !== (($arms[$this->chars[$above]] ?? 0) & 0b100) && !self::sameColor($this->fg[$above], '' !== $this->bg[$above] ? $this->bg[$above] : $background)) {
                $this->fg[$idx] = $this->fg[$above];
                $shown[$col] = true;
            }
        }
        foreach ($joined as $col => $lines) {
            // Drawn in the color of its background, it shows nothing: a space, which shows nothing without colors too
            if (!$shown[$col]) {
                $this->chars[$offset + $col] = ' ';
                continue;
            }
            if (!($shown[$col - 1] ?? false) || 0 === (($joined[$col - 1] ?? 0) & 0b1)) {
                $lines &= ~0b10;
            }
            if (!($shown[$col + 1] ?? false) || 0 === (($joined[$col + 1] ?? 0) & 0b10)) {
                $lines &= ~0b1;
            }
            // A line going one way only is drawn across the cell
            $lines = match ($lines) {
                0b1000, 0b100 => 0b1100,
                0b10, 0b1 => 0b11,
                default => $lines,
            };
            if (false !== $glyph = array_search($lines, $arms, true)) {
                $this->chars[$offset + $col] = $glyph;
            }
        }
    }

    /**
     * Serialize the buffer back to ANSI-formatted strings.
     *
     * Produces optimized output: only emits SGR changes when the style
     * actually changes between cells.
     *
     * @return string[]
     */
    public function toLines(): array
    {
        $lines = [];
        $width = $this->width;
        $chars = $this->chars;
        $widths = $this->widths;
        $fg = $this->fg;
        $bg = $this->bg;
        $attrs = $this->attrs;
        for ($row = 0; $row < $this->height; ++$row) {
            $line = '';
            $currentFg = '';
            $currentBg = '';
            $currentAttrs = 0;
            $currentLink = '';
            $rowOffset = $row * $width;
            for ($col = 0; $col < $width; ++$col) {
                $idx = $rowOffset + $col;
                // Skip continuation cells (part of a wide character)
                if (0 === $widths[$idx]) {
                    continue;
                }
                $cellFg = $fg[$idx];
                $cellBg = $bg[$idx];
                $cellAttrs = $attrs[$idx];
                // Emit SGR change if needed
                if ($cellFg !== $currentFg || $cellBg !== $currentBg || $cellAttrs !== $currentAttrs) {
                    $line .= $this->buildSgr($cellFg, $cellBg, $cellAttrs);
                    $currentFg = $cellFg;
                    $currentBg = $cellBg;
                    $currentAttrs = $cellAttrs;
                }
                if ($this->links[$idx] !== $currentLink) {
                    $currentLink = $this->links[$idx];
                    $line .= "\x1b]8;".('' === $currentLink ? ';' : $currentLink)."\x07";
                }
                $line .= $chars[$idx];
            }
            // Reset at end of line
            if ('' !== $currentLink) {
                $line .= "\x1b]8;;\x07";
            }
            if ('' !== $currentFg || '' !== $currentBg || 0 !== $currentAttrs) {
                $line .= "\x1b[0m";
            }
            $lines[] = $line;
        }

        return $lines;
    }

    /**
     * Turn the wide character covering the given cell into blanks.
     *
     * A wide character owns a lead cell and one continuation cell. Writing
     * into either of them without clearing the other leaves a lead without
     * its continuation, or a continuation without its lead, and the row then
     * renders with the wrong number of columns.
     */
    private function blankWideCharAt(int $rowOffset, int $col): void
    {
        if (1 === $this->widths[$rowOffset + $col]) {
            return;
        }
        $start = $col;
        while ($start > 0 && 0 === $this->widths[$rowOffset + $start]) {
            --$start;
        }
        $end = $start + max(1, $this->widths[$rowOffset + $start]);
        for ($c = $start; $c < $end && $c < $this->width; ++$c) {
            $this->chars[$rowOffset + $c] = ' ';
            $this->widths[$rowOffset + $c] = 1;
        }
    }

    /**
     * Whether a foreground and a background SGR state are the same color, like white on white.
     */
    private static function sameColor(string $fg, string $bg): bool
    {
        if ('' === $fg || '' === $bg) {
            return false;
        }
        if (str_starts_with($fg, '38;') && str_starts_with($bg, '48;')) {
            return substr($fg, 3) === substr($bg, 3);
        }

        return is_numeric($fg) && is_numeric($bg) && (int) $fg + 10 === (int) $bg;
    }

    /**
     * Build an SGR escape sequence from cell attributes.
     *
     * Always emits a full reset + set to avoid state accumulation issues.
     */
    private function buildSgr(string $fg, string $bg, int $attrs): string
    {
        // Fast path: reset to default (no style)
        if ('' === $fg && '' === $bg && 0 === $attrs) {
            return "\x1b[0m";
        }
        $sgr = "\x1b[0";
        if ($attrs & self::ATTR_BOLD) {
            $sgr .= ';1';
        }
        if ($attrs & self::ATTR_DIM) {
            $sgr .= ';2';
        }
        if ($attrs & self::ATTR_ITALIC) {
            $sgr .= ';3';
        }
        if ($attrs & self::ATTR_UNDERLINE) {
            $sgr .= ';4';
        }
        if ($attrs & self::ATTR_BLINK) {
            $sgr .= ';5';
        }
        if ($attrs & self::ATTR_REVERSE) {
            $sgr .= ';7';
        }
        if ($attrs & self::ATTR_STRIKETHROUGH) {
            $sgr .= ';9';
        }
        if ('' !== $fg) {
            $sgr .= ';'.$fg;
        }
        if ('' !== $bg) {
            $sgr .= ';'.$bg;
        }

        return $sgr.'m';
    }

    /**
     * Parse SGR parameters directly from the string (avoids regex, explode, array_map).
     *
     * @param string $line  The full line string
     * @param int    $start Start of parameter chars (after "\x1b[")
     * @param int    $end   Position of the 'm' terminator
     */
    private function parseSgrInline(string $line, int $start, int $end, string &$fg, string &$bg, int &$attrs): void
    {
        // Fast path: \x1b[0m or \x1b[m, pure reset
        if ($start === $end || 1 === $end - $start && '0' === $line[$start]) {
            $fg = '';
            $bg = '';
            $attrs = 0;

            return;
        }
        // Parse semicolon-delimited integers directly from the string
        $num = 0;
        $hasNum = false;
        /** @var int[] $codes */
        $codes = [];
        // The sub-parameters after a colon, like the style of 4:3 for a curly underline: 4:0 turns it off, the
        // others are an underline; the colors given with colons, like 58:2::r:g:b, are skipped
        $sub = null;
        for ($p = $start; $p <= $end; ++$p) {
            $ch = $line[$p] ?? 'm';
            if ($ch >= '0' && $ch <= '9') {
                $num = $num * 10 + \ord($ch) - 48;
                $hasNum = true;
            } elseif (':' === $ch) {
                if (null === $sub) {
                    $sub = [$hasNum ? $num : 0];
                } else {
                    $sub[] = $hasNum ? $num : 0;
                }
                $num = 0;
                $hasNum = false;
            } elseif (';' === $ch || 'm' === $ch) {
                if (null !== $sub) {
                    $sub[] = $hasNum ? $num : 0;
                    // The colors given with colons as with semicolons: 38:5:n, and 38:2:id:r:g:b, its color space id empty
                    // or not, its tolerance after it; an unknown code ignored
                    if ((38 === $sub[0] || 48 === $sub[0]) && 2 === ($sub[1] ?? null) && \count($sub) >= 5) {
                        array_push($codes, $sub[0], 2, ...\array_slice($sub, \count($sub) >= 6 ? 3 : 2, 3));
                    } elseif ((38 === $sub[0] || 48 === $sub[0]) && 5 === ($sub[1] ?? null) && isset($sub[2])) {
                        array_push($codes, $sub[0], 5, $sub[2]);
                    } elseif (4 === $sub[0]) {
                        $codes[] = 0 === $sub[1] ? 24 : 4;
                    }
                    $sub = null;
                } else {
                    $codes[] = $hasNum ? $num : 0;
                }
                $num = 0;
                $hasNum = false;
            }
        }
        $i = 0;
        $count = \count($codes);
        while ($i < $count) {
            $c = $codes[$i];
            if (0 === $c) {
                $fg = '';
                $bg = '';
                $attrs = 0;
            } elseif ($c >= 1 && $c <= 9) {
                // Attributes
                $attrs |= match ($c) {
                    1 => self::ATTR_BOLD,
                    2 => self::ATTR_DIM,
                    3 => self::ATTR_ITALIC,
                    4 => self::ATTR_UNDERLINE,
                    5 => self::ATTR_BLINK,
                    7 => self::ATTR_REVERSE,
                    9 => self::ATTR_STRIKETHROUGH,
                    default => 0,
                };
            } elseif ($c >= 22 && $c <= 29) {
                // Attribute off
                $attrs &= match ($c) {
                    22 => ~(self::ATTR_BOLD | self::ATTR_DIM),
                    23 => ~self::ATTR_ITALIC,
                    24 => ~self::ATTR_UNDERLINE,
                    25 => ~self::ATTR_BLINK,
                    27 => ~self::ATTR_REVERSE,
                    29 => ~self::ATTR_STRIKETHROUGH,
                    default => ~0,
                };
            } elseif ($c >= 30 && $c <= 37) {
                $fg = (string) $c;
            } elseif (39 === $c) {
                $fg = '';
            } elseif ($c >= 40 && $c <= 47) {
                $bg = (string) $c;
            } elseif (49 === $c) {
                $bg = '';
            } elseif ($c >= 90 && $c <= 97) {
                $fg = (string) $c;
            } elseif ($c >= 100 && $c <= 107) {
                $bg = (string) $c;
            } elseif (38 === $c && $i + 1 < $count) {
                if (5 === $codes[$i + 1] && $i + 2 < $count) {
                    $fg = '38;5;'.$codes[$i + 2];
                    $i += 2;
                } elseif (2 === $codes[$i + 1] && $i + 4 < $count) {
                    $fg = '38;2;'.$codes[$i + 2].';'.$codes[$i + 3].';'.$codes[$i + 4];
                    $i += 4;
                }
            } elseif (58 === $c && $i + 1 < $count) {
                // The color of the underline, not kept: its parameters skipped
                $i += 5 === $codes[$i + 1] ? 2 : (2 === $codes[$i + 1] ? 4 : 0);
            } elseif (48 === $c && $i + 1 < $count) {
                if (5 === $codes[$i + 1] && $i + 2 < $count) {
                    $bg = '48;5;'.$codes[$i + 2];
                    $i += 2;
                } elseif (2 === $codes[$i + 1] && $i + 4 < $count) {
                    $bg = '48;2;'.$codes[$i + 2].';'.$codes[$i + 3].';'.$codes[$i + 4];
                    $i += 4;
                }
            }
            ++$i;
        }
    }
}
