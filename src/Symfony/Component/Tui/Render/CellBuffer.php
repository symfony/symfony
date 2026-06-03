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

    /* Row of the cursor marker, or null if not found */
    private ?int $cursorRow = null;

    /* Column (cell index) of the cursor marker, or null if not found */
    private ?int $cursorCol = null;

    public function __construct(
        private readonly int $width,
        private readonly int $height,
    ) {
        if ($width < 1 || $height < 1) {
            throw new InvalidArgumentException(\sprintf('CellBuffer dimensions must be at least 1x1, got %dx%d', $width, $height));
        }

        $size = $width * $height;
        $this->chars = array_fill(0, $size, ' ');
        $this->widths = array_fill(0, $size, 1);
        $this->fg = array_fill(0, $size, '');
        $this->bg = array_fill(0, $size, '');
        $this->attrs = array_fill(0, $size, 0);
    }

    public function getWidth(): int
    {
        return $this->width;
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
     * @param string[] $lines       ANSI-formatted lines
     * @param int      $startRow    Row offset to start writing
     * @param int      $startCol    Column offset to start writing
     * @param bool     $transparent When true, cells with no explicit background preserve
     *                              the existing buffer background (transparency). Cells that
     *                              are plain spaces with default fg/bg/attrs are fully transparent
     *                              and leave the buffer cell entirely unchanged.
     */
    public function writeAnsiLines(array $lines, int $startRow = 0, int $startCol = 0, bool $transparent = false): void
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

            $col = $startCol;
            $i = 0;
            $len = \strlen($line);
            $rowOffset = $row * $width;

            while ($i < $len && $col < $width) {
                $ord = \ord($line[$i]);

                // Fast path: ASCII printable (0x20-0x7E), most common case
                if ($ord >= 0x20 && $ord <= 0x7E) {
                    // In transparent mode, skip fully unstyled spaces (fully transparent cell)
                    if ($transparent && ' ' === $line[$i] && '' === $fgState && '' === $bgState && 0 === $attrState) {
                        ++$col;
                        ++$i;
                        continue;
                    }
                    $idx = $rowOffset + $col;
                    $this->chars[$idx] = $line[$i];
                    $this->widths[$idx] = 1;
                    $this->fg[$idx] = $fgState;
                    $this->bg[$idx] = $transparent && '' === $bgState ? $this->bg[$idx] : $bgState;
                    $this->attrs[$idx] = $attrState;
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
                            if ("\x07" === $line[$j]) {
                                $i = $j + 1;
                                break;
                            }
                            if ("\x1b" === $line[$j] && isset($line[$j + 1]) && '\\' === $line[$j + 1]) {
                                $i = $j + 2;
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
                if (0x09 === $ord) {
                    $spaces = 3; // Match AnsiUtils tab width
                    for ($s = 0; $s < $spaces && $col < $width; ++$s) {
                        if ($transparent && '' === $fgState && '' === $bgState && 0 === $attrState) {
                            ++$col;
                            continue;
                        }
                        $idx = $rowOffset + $col;
                        $this->chars[$idx] = ' ';
                        $this->widths[$idx] = 1;
                        $this->fg[$idx] = $fgState;
                        $this->bg[$idx] = $transparent && '' === $bgState ? $this->bg[$idx] : $bgState;
                        $this->attrs[$idx] = $attrState;
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

                // Check if it fits
                if ($col + $charWidth > $width) {
                    while ($col < $width) {
                        $idx = $rowOffset + $col;
                        $this->chars[$idx] = ' ';
                        $this->widths[$idx] = 1;
                        $this->fg[$idx] = $fgState;
                        $this->bg[$idx] = $transparent && '' === $bgState ? $this->bg[$idx] : $bgState;
                        $this->attrs[$idx] = $attrState;
                        ++$col;
                    }
                    $i = $nextPos;
                    continue;
                }

                // Place the character
                $idx = $rowOffset + $col;
                $this->chars[$idx] = $grapheme;
                $this->widths[$idx] = $charWidth;
                $this->fg[$idx] = $fgState;
                $this->bg[$idx] = $transparent && '' === $bgState ? $this->bg[$idx] : $bgState;
                $this->attrs[$idx] = $attrState;

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

                $line .= $chars[$idx];
            }

            // Reset at end of line
            if ('' !== $currentFg || '' !== $currentBg || 0 !== $currentAttrs) {
                $line .= "\x1b[0m";
            }

            $lines[] = $line;
        }

        return $lines;
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
        if ($start === $end || (1 === $end - $start && '0' === $line[$start])) {
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

        for ($p = $start; $p <= $end; ++$p) {
            $ch = $line[$p] ?? 'm';
            if ($ch >= '0' && $ch <= '9') {
                $num = $num * 10 + \ord($ch) - 48;
                $hasNum = true;
            } elseif (';' === $ch || 'm' === $ch) {
                $codes[] = $hasNum ? $num : 0;
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
