<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Console\Output;

use Symfony\Component\Console\Exception\InvalidArgumentException;

/**
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Julien Boudry <julien@condorcet.vote>
 */
enum AnsiColorMode
{
    /*
     * Classical 4-bit Ansi colors, including 8 classical colors and 8 bright color. Output syntax is "ESC[${foreGroundColorcode};${backGroundColorcode}m"
     * Must be compatible with all terminals and it's the minimal version supported.
     */
    case Ansi4;

    /*
     * 8-bit Ansi colors (240 different colors + 16 duplicate color codes, ensuring backward compatibility).
     * Output syntax is: "ESC[38;5;${foreGroundColorcode};48;5;${backGroundColorcode}m"
     * Should be compatible with most terminals.
     */
    case Ansi8;

    /*
     * 24-bit Ansi colors (RGB).
     * Output syntax is: "ESC[38;2;${foreGroundColorcodeRed};${foreGroundColorcodeGreen};${foreGroundColorcodeBlue};48;2;${backGroundColorcodeRed};${backGroundColorcodeGreen};${backGroundColorcodeBlue}m"
     * May be compatible with many modern terminals.
     */
    case Ansi24;

    /**
     * Converts an RGB hexadecimal color to the corresponding Ansi code.
     */
    public function convertFromHexToAnsiColorCode(string $hexColor): string
    {
        $hexColor = str_replace('#', '', $hexColor);

        if (3 === \strlen($hexColor)) {
            $hexColor = $hexColor[0].$hexColor[0].$hexColor[1].$hexColor[1].$hexColor[2].$hexColor[2];
        }

        if (6 !== \strlen($hexColor)) {
            throw new InvalidArgumentException(\sprintf('Invalid "#%s" color.', $hexColor));
        }

        $color = hexdec($hexColor);

        $r = ($color >> 16) & 255;
        $g = ($color >> 8) & 255;
        $b = $color & 255;

        return match ($this) {
            self::Ansi4 => (string) $this->convertFromRGB($r, $g, $b),
            self::Ansi8 => '8;5;'.$this->convertFromRGB($r, $g, $b),
            self::Ansi24 => \sprintf('8;2;%d;%d;%d', $r, $g, $b),
        };
    }

    private function convertFromRGB(int $r, int $g, int $b): int
    {
        return match ($this) {
            self::Ansi4 => $this->degradeHexColorToAnsi4($r, $g, $b),
            self::Ansi8 => $this->degradeHexColorToAnsi8($r, $g, $b),
            default => throw new InvalidArgumentException("RGB cannot be converted to {$this->name}."),
        };
    }

    /**
     * Picks the 4-bit Ansi color with the closest hue.
     *
     * Greys and nearly grey colors (channels less than 32 apart) have no visible hue: they are mapped
     * to black or white depending on their brightness.
     * Other colors are mapped to the nearest of the 6 primary/secondary hues.
     *
     * Returns the index of the SGR color (30-37, 40-47). ECMA-48 only names these colors: the hues
     * target the pure colors #ff0000, #ffff00, #00ff00, #00ffff, #0000ff and #ff00ff, while the RGB
     * actually displayed depends on the terminal (e.g. xterm's defaults in its XTerm-col.ad file).
     */
    private function degradeHexColorToAnsi4(int $r, int $g, int $b): int
    {
        $max = max($r, $g, $b);
        $delta = $max - min($r, $g, $b);

        // an absolute threshold, unlike the HSV saturation, also catches near-blacks such as #010000
        if ($delta < 32) {
            return $max < 128 ? 0 : 7;
        }

        // hue sector (60 degrees each) between -1 and 5, both meaning magenta
        $hue = match ($max) {
            $r => ($g - $b) / $delta,
            $g => ($b - $r) / $delta + 2,
            default => ($r - $g) / $delta + 4,
        };

        // magenta, red, yellow, green, cyan, blue, magenta
        return [5, 1, 3, 2, 6, 4, 5][(int) round($hue) + 1];
    }

    /**
     * Inspired from https://github.com/ajalt/colormath/blob/e464e0da1b014976736cf97250063248fc77b8e7/colormath/src/commonMain/kotlin/com/github/ajalt/colormath/model/Ansi256.kt code (MIT license).
     */
    private function degradeHexColorToAnsi8(int $r, int $g, int $b): int
    {
        if ($r === $g && $g === $b) {
            if ($r < 8) {
                return 16;
            }

            if ($r > 248) {
                return 231;
            }

            return (int) round(($r - 8) / 247 * 24) + 232;
        }

        return 16 +
            (36 * (int) round($r / 255 * 5)) +
            (6 * (int) round($g / 255 * 5)) +
            (int) round($b / 255 * 5);
    }
}
