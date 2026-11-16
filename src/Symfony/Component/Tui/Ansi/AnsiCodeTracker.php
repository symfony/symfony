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
 * Tracks active ANSI SGR codes to preserve styling across line breaks.
 *
 * @experimental
 *
 * @internal
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
final class AnsiCodeTracker
{
    private bool $bold = false;
    private bool $dim = false;
    private bool $italic = false;
    private bool $underline = false;
    private bool $doubleUnderline = false;
    private bool $blink = false;
    private bool $inverse = false;
    private bool $hidden = false;
    private bool $strikethrough = false;
    private bool $overline = false;
    private ?string $fgColor = null;
    private ?string $bgColor = null;
    private ?string $underlineColor = null;
    /** A styled underline, like 4:3 (curly): kept as is */
    private ?string $underlineVariant = null;
    /** The OSC 8 sequence that opened the hyperlink being drawn, its id and parameters kept: null outside one */
    private ?string $link = null;

    /**
     * Process an ANSI escape code and update tracking state.
     */
    public function process(string $ansiCode): void
    {
        if (str_starts_with($ansiCode, "\x1b]8;")) {
            $this->link = self::linkOf($ansiCode);

            return;
        }
        // Fast direct parsing: skip regex, extract params between \x1b[ and m
        if (\strlen($ansiCode) < 3 || "\x1b" !== $ansiCode[0] || '[' !== $ansiCode[1] || 'm' !== $ansiCode[-1]) {
            return;
        }

        $params = substr($ansiCode, 2, -1);
        if ('' === $params || '0' === $params) {
            $this->reset();

            return;
        }

        $this->processParams(explode(';', $params));
    }

    /**
     * Reset the colors and attributes, like SGR 0: a hyperlink stays open, until an OSC 8 closes it.
     */
    public function reset(): void
    {
        $this->bold = false;
        $this->dim = false;
        $this->italic = false;
        $this->underline = false;
        $this->doubleUnderline = false;
        $this->blink = false;
        $this->inverse = false;
        $this->hidden = false;
        $this->strikethrough = false;
        $this->overline = false;
        $this->fgColor = null;
        $this->bgColor = null;
        $this->underlineColor = null;
        $this->underlineVariant = null;
    }

    /**
     * Get ANSI escape sequence to restore current active codes.
     */
    public function getActiveCodes(): string
    {
        $codes = "\x1b[";

        if ($this->bold) {
            $codes .= '1;';
        }
        if ($this->dim) {
            $codes .= '2;';
        }
        if ($this->italic) {
            $codes .= '3;';
        }
        if (null !== $this->underlineVariant) {
            $codes .= $this->underlineVariant.';';
        } elseif ($this->underline) {
            $codes .= '4;';
        }
        if ($this->doubleUnderline) {
            $codes .= '21;';
        }
        if ($this->blink) {
            $codes .= '5;';
        }
        if ($this->inverse) {
            $codes .= '7;';
        }
        if ($this->hidden) {
            $codes .= '8;';
        }
        if ($this->strikethrough) {
            $codes .= '9;';
        }
        if ($this->overline) {
            $codes .= '53;';
        }
        if (null !== $this->fgColor) {
            $codes .= $this->fgColor.';';
        }
        if (null !== $this->bgColor) {
            $codes .= $this->bgColor.';';
        }
        if (null !== $this->underlineColor) {
            $codes .= $this->underlineColor.';';
        }

        if ("\x1b[" === $codes) {
            return $this->link ?? '';
        }
        $codes[-1] = 'm';

        return $codes.$this->link;
    }

    /**
     * Check if any codes are currently active.
     */
    public function hasActiveCodes(): bool
    {
        return $this->bold
            || $this->dim
            || $this->italic
            || $this->underline
            || $this->doubleUnderline
            || $this->blink
            || $this->inverse
            || $this->hidden
            || $this->strikethrough
            || $this->overline
            || null !== $this->fgColor
            || null !== $this->bgColor
            || null !== $this->underlineColor
            || null !== $this->link;
    }

    /**
     * The foreground color set, as its SGR parameters, like 36 or 38;2;255;0;0; null for the default one.
     */
    public function getForeground(): ?string
    {
        return $this->fgColor;
    }

    /**
     * The background color set, as its SGR parameters, like 44 or 48;2;0;0;255; null for the default one.
     */
    public function getBackground(): ?string
    {
        return $this->bgColor;
    }

    /**
     * Whether the colors are swapped, like SGR 7: the glyphs drawn in the background color.
     */
    public function isInverse(): bool
    {
        return $this->inverse;
    }

    /**
     * Whether the characters drawn now are hidden, like SGR 8: drawn as spaces.
     */
    public function isHidden(): bool
    {
        return $this->hidden;
    }

    /**
     * Whether the cells drawn now are in a hyperlink (OSC 8).
     */
    public function inLink(): bool
    {
        return null !== $this->link;
    }

    /**
     * Whether the cells drawn now are painted even when they are spaces: a background, reverse video, or a line drawn
     * across them, like an underline, a line through or an overline.
     */
    public function paintsCells(): bool
    {
        return null !== $this->bgColor || $this->inverse || $this->underline || $this->doubleUnderline || $this->strikethrough || $this->overline;
    }

    /**
     * Get the codes that turn off the underline, strikethrough, overline, inverse, background and a hyperlink at line end, as they bleed into the padding.
     */
    public function getLineEndReset(): string
    {
        // A hyperlink is closed at the end of the line too, and opened again on the next one, like a style
        return ($this->underline || $this->doubleUnderline ? "\x1b[24m" : '')
            .($this->strikethrough ? "\x1b[29m" : '')
            .($this->overline ? "\x1b[55m" : '')
            .($this->inverse ? "\x1b[27m" : '')
            .(null !== $this->bgColor ? "\x1b[49m" : '')
            .(null !== $this->link ? "\x1b]8;;\x07" : '');
    }

    /**
     * Update tracker state from all ANSI codes in a text string.
     */
    public function processText(string $text): void
    {
        // Fast path: no escape sequences at all
        if (!str_contains($text, "\x1b")) {
            return;
        }
        // The last hyperlink opened or closed
        if (str_contains($text, "\x1b]8;") && preg_match_all('/\x1b\]8;[^;\x07\x1b]*;[^\x07\x1b]*(?:\x07|\x1b\\\\)/', $text, $links)) {
            $this->link = self::linkOf((string) end($links[0]));
        }

        // Use preg_match_all to find all SGR sequences at once (C-level scan)
        if (!preg_match_all('/\x1b\[([\d;:]*)m/', $text, $matches)) {
            return;
        }
        foreach ($matches[1] as $params) {
            if ('' === $params || '0' === $params) {
                $this->reset();
                continue;
            }

            $this->processParams(explode(';', $params));
        }
    }

    /**
     * The sequence of a hyperlink opened, null for one that closes it (an empty URI).
     */
    private static function linkOf(string $code): ?string
    {
        return preg_match('/^\x1b\]8;[^;\x07\x1b]*;(?:\x07|\x1b\\\\)$/', $code) ? null : $code;
    }

    /**
     * @param list<string> $parts
     */
    private function processParams(array $parts): void
    {
        $i = 0;
        $count = \count($parts);

        while ($i < $count) {
            // The colon forms, read like self::colonParameters() reads them: a color kept as it is, a styled underline
            // its shape
            if (str_contains($parts[$i], ':')) {
                $read = self::colonParameters(array_map(intval(...), explode(':', $parts[$i])));
                if (isset($read[2])) {
                    $this->setColor($read[0], $parts[$i]);
                } elseif ([24, 4] === $read) {
                    [$this->underline, $this->doubleUnderline, $this->underlineVariant] = [true, false, $parts[$i]];
                } elseif ([24] === $read) {
                    [$this->underline, $this->doubleUnderline, $this->underlineVariant] = [false, false, null];
                }
                ++$i;
                continue;
            }

            $code = (int) $parts[$i];

            // Handle 256-color and RGB codes which consume multiple parameters
            if (38 === $code || 48 === $code || 58 === $code) {
                if (isset($parts[$i + 1]) && '5' === $parts[$i + 1]) {
                    if (isset($parts[$i + 2])) {
                        // 256 color: 38;5;N, 48;5;N or 58;5;N
                        $this->setColor($code, $parts[$i].';'.$parts[$i + 1].';'.$parts[$i + 2]);
                        $i += 3;
                    } else {
                        // Malformed: 38;5 / 48;5 / 58;5 without color number, skip both
                        $i += 2;
                    }
                    continue;
                } elseif (isset($parts[$i + 1]) && '2' === $parts[$i + 1]) {
                    if (isset($parts[$i + 4])) {
                        // RGB color: 38;2;R;G;B, 48;2;R;G;B or 58;2;R;G;B
                        $this->setColor($code, $parts[$i].';'.$parts[$i + 1].';'.$parts[$i + 2].';'.$parts[$i + 3].';'.$parts[$i + 4]);
                        $i += 5;
                    } else {
                        // Malformed: insufficient RGB components, skip all remaining parts
                        $i = $count;
                    }
                    continue;
                }
                // 38/48/58 not followed by 5 or 2, ignore and move on
                ++$i;
                continue;
            }

            match ($code) {
                0 => $this->reset(),
                1 => $this->bold = true,
                2 => $this->dim = true,
                3 => $this->italic = true,
                // A single underline replaces a double one, like a styled one; a double one after it is drawn instead
                4 => [$this->underline, $this->doubleUnderline, $this->underlineVariant] = [true, false, null],
                5 => $this->blink = true,
                7 => $this->inverse = true,
                8 => $this->hidden = true,
                9 => $this->strikethrough = true,
                21 => $this->doubleUnderline = true,
                22 => $this->bold = $this->dim = false,
                23 => $this->italic = false,
                24 => [$this->underline, $this->doubleUnderline, $this->underlineVariant] = [false, false, null],
                25 => $this->blink = false,
                27 => $this->inverse = false,
                28 => $this->hidden = false,
                29 => $this->strikethrough = false,
                53 => $this->overline = true,
                55 => $this->overline = false,
                30, 31, 32, 33, 34, 35, 36, 37, 90, 91, 92, 93, 94, 95, 96, 97 => $this->fgColor = (string) $code,
                39 => $this->fgColor = null,
                40, 41, 42, 43, 44, 45, 46, 47, 100, 101, 102, 103, 104, 105, 106, 107 => $this->bgColor = (string) $code,
                49 => $this->bgColor = null,
                59 => $this->underlineColor = null,
                default => null,
            };

            ++$i;
        }
    }

    /**
     * The SGR parameters an SGR parameter given with colons stands for, like terminals read it: the colors 38:5:N and
     * 38:2:[ID:]R:G:B (48 and 58 too) as with semicolons, an empty parameter 0, what follows the color skipped; 4:0 as 24,
     * the other styled underlines as 24;4, a single underline replacing a double one; none for the others.
     *
     * @param list<int> $parameter The numbers between its colons
     *
     * @return list<int>
     */
    public static function colonParameters(array $parameter): array
    {
        $code = $parameter[0] ?? 0;
        if (38 === $code || 48 === $code || 58 === $code) {
            $count = \count($parameter);

            return match ($parameter[1] ?? null) {
                2 => $count >= 5 ? [$code, 2, ...\array_slice($parameter, $count >= 6 ? 3 : 2, 3)] : [],
                5 => $count >= 3 ? [$code, 5, $parameter[2]] : [],
                default => [],
            };
        }
        if (4 === $code) {
            return 0 === ($parameter[1] ?? 0) ? [24] : [24, 4];
        }

        return [];
    }

    private function setColor(int $code, string $value): void
    {
        match ($code) {
            38 => $this->fgColor = $value,
            48 => $this->bgColor = $value,
            58 => $this->underlineColor = $value,
            default => throw new \UnhandledMatchError(),
        };
    }
}
