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
    private ?string $fgColor = null;
    private ?string $bgColor = null;
    private ?string $underlineColor = null;

    /**
     * Process an ANSI escape code and update tracking state.
     */
    public function process(string $ansiCode): void
    {
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
     * Reset all tracking state.
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
        $this->fgColor = null;
        $this->bgColor = null;
        $this->underlineColor = null;
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
        if ($this->underline) {
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
            return '';
        }
        $codes[-1] = 'm';

        return $codes;
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
            || null !== $this->fgColor
            || null !== $this->bgColor
            || null !== $this->underlineColor;
    }

    /**
     * Get reset codes for attributes that need to be turned off at line end.
     * Specifically underline which bleeds into padding.
     */
    public function getLineEndReset(): string
    {
        if ($this->underline || $this->doubleUnderline) {
            return "\x1b[24m";
        }

        return '';
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

        // Use preg_match_all to find all SGR sequences at once (C-level scan)
        if (!preg_match_all('/\x1b\[([\d;]*)m/', $text, $matches)) {
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
     * @param list<string> $parts
     */
    private function processParams(array $parts): void
    {
        $i = 0;
        $count = \count($parts);

        while ($i < $count) {
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
                4 => $this->underline = true,
                5 => $this->blink = true,
                7 => $this->inverse = true,
                8 => $this->hidden = true,
                9 => $this->strikethrough = true,
                21 => $this->doubleUnderline = true,
                22 => $this->bold = $this->dim = false,
                23 => $this->italic = false,
                24 => $this->underline = $this->doubleUnderline = false,
                25 => $this->blink = false,
                27 => $this->inverse = false,
                28 => $this->hidden = false,
                29 => $this->strikethrough = false,
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

    private function setColor(int $code, string $value): void
    {
        match ($code) {
            38 => $this->fgColor = $value,
            48 => $this->bgColor = $value,
            58 => $this->underlineColor = $value,
        };
    }
}
