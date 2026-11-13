<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Tui\Style;

use Symfony\Component\Tui\Exception\InvalidArgumentException;

/**
 * Represents a terminal color.
 *
 * Supports multiple color formats:
 * - Basic 16 ANSI colors (named: 'black', 'red', 'green', etc.)
 * - 256-color palette (integers 0-255)
 * - True color RGB (hex strings like '#ff5500' or '#f50')
 *
 * @experimental
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
final class Color
{
    // Basic 16 ANSI color codes (foreground)
    private const BASIC_COLORS = [
        'black' => 30,
        'red' => 31,
        'green' => 32,
        'yellow' => 33,
        'blue' => 34,
        'magenta' => 35,
        'cyan' => 36,
        'white' => 37,
        'default' => 39,
        'bright_black' => 90,
        'bright_red' => 91,
        'bright_green' => 92,
        'bright_yellow' => 93,
        'bright_blue' => 94,
        'bright_magenta' => 95,
        'bright_cyan' => 96,
        'bright_white' => 97,
        // Aliases
        'gray' => 90,
        'grey' => 90,
    ];

    /** Standard RGB values for named ANSI colors (xterm defaults). */
    private const NAMED_RGB = [
        'black' => [0, 0, 0],
        'red' => [205, 0, 0],
        'green' => [0, 205, 0],
        'yellow' => [205, 205, 0],
        'blue' => [0, 0, 238],
        'magenta' => [205, 0, 205],
        'cyan' => [0, 205, 205],
        'white' => [229, 229, 229],
        'default' => [229, 229, 229],
        'bright_black' => [127, 127, 127],
        'bright_red' => [255, 0, 0],
        'bright_green' => [0, 255, 0],
        'bright_yellow' => [255, 255, 0],
        'bright_blue' => [92, 92, 255],
        'bright_magenta' => [255, 0, 255],
        'bright_cyan' => [0, 255, 255],
        'bright_white' => [255, 255, 255],
        'gray' => [127, 127, 127],
        'grey' => [127, 127, 127],
    ];

    /**
     * Create a color from a named ANSI color.
     */
    public static function named(string $name): self
    {
        $name = strtolower($name);
        if (!isset(self::BASIC_COLORS[$name])) {
            throw new InvalidArgumentException(\sprintf('Unknown color name: "%s".', $name));
        }

        return new self(ColorType::Named, $name);
    }

    /**
     * Create a color from the 256-color palette.
     */
    public static function palette(int $index): self
    {
        if ($index < 0 || $index > 255) {
            throw new InvalidArgumentException(\sprintf('Color palette index must be 0-255, got: %d', $index));
        }

        return new self(ColorType::Palette, $index);
    }

    /**
     * Create a color from RGB hex string.
     *
     * @param string $hex Hex color like '#ff5500', 'ff5500', '#f50', or 'f50'
     */
    public static function hex(string $hex): self
    {
        $hex = ltrim($hex, '#');

        // Expand short form (#f50 -> #ff5500)
        if (3 === \strlen($hex)) {
            $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
        }

        if (6 !== \strlen($hex) || !ctype_xdigit($hex)) {
            throw new InvalidArgumentException(\sprintf('Invalid hex color: "%s".', $hex));
        }

        return new self(ColorType::Hex, $hex);
    }

    /**
     * Create a color from RGB values.
     */
    public static function rgb(int $r, int $g, int $b): self
    {
        if ($r < 0 || $r > 255 || $g < 0 || $g > 255 || $b < 0 || $b > 255) {
            throw new InvalidArgumentException(\sprintf('RGB values must be 0-255, got: %d, %d, %d', $r, $g, $b));
        }

        return new self(ColorType::Hex, \sprintf('%02x%02x%02x', $r, $g, $b));
    }

    /**
     * Create a Color from various input types.
     *
     * @param string|int|self $color Color specification:
     *                               - Color instance (returned as-is)
     *                               - string starting with '#' -> hex color
     *                               - string -> named color
     *                               - int -> 256-palette index
     */
    public static function from(string|int|self $color): self
    {
        if ($color instanceof self) {
            return $color;
        }

        if (\is_int($color)) {
            return self::palette($color);
        }

        if (str_starts_with($color, '#')) {
            return self::hex($color);
        }

        return self::named($color);
    }

    /**
     * Get the RGB components of this color.
     *
     * Named and palette colors are converted using standard xterm defaults.
     *
     * @return array{r: int, g: int, b: int}
     */
    public function toRgb(): array
    {
        return match ($this->type) {
            ColorType::Named => self::namedToRgb((string) $this->value),
            ColorType::Palette => self::paletteToRgb((int) $this->value),
            ColorType::Hex => self::hexToRgb((string) $this->value),
        };
    }

    /**
     * Mix this color with another by a given percentage.
     *
     * At 0 % the result is this color; at 100 % it is the other color.
     *
     * @param self|string $color      The color to mix with
     * @param int         $percentage 0–100
     */
    public function mix(self|string $color, int $percentage): self
    {
        if ($percentage < 0 || $percentage > 100) {
            throw new InvalidArgumentException(\sprintf('Percentage must be 0-100, got: %d', $percentage));
        }

        if (\is_string($color)) {
            $color = self::from($color);
        }

        $base = $this->toRgb();
        $other = $color->toRgb();
        $factor = $percentage / 100;

        return self::rgb(
            (int) round($base['r'] * (1 - $factor) + $other['r'] * $factor),
            (int) round($base['g'] * (1 - $factor) + $other['g'] * $factor),
            (int) round($base['b'] * (1 - $factor) + $other['b'] * $factor),
        );
    }

    /**
     * Lighten this color by mixing it with white.
     *
     * @param int $percentage 0 (unchanged) to 100 (pure white)
     */
    public function tint(int $percentage): self
    {
        return $this->mix('#ffffff', $percentage);
    }

    /**
     * Darken this color by mixing it with black.
     *
     * @param int $percentage 0 (unchanged) to 100 (pure black)
     */
    public function shade(int $percentage): self
    {
        return $this->mix('#000000', $percentage);
    }

    /**
     * Lighten or darken this color.
     *
     * Positive values darken (shade), negative values lighten (tint).
     *
     * @param int $percentage -100 (white) to 100 (black)
     */
    public function scale(int $percentage): self
    {
        return $percentage > 0 ? $this->shade($percentage) : $this->tint(-$percentage);
    }

    /**
     * Convert an SGR foreground color code (30-37, 90-97) to a Color.
     *
     * Returns null if the code is not a basic/bright foreground color.
     */
    public static function fromSgrForeground(int $code): ?self
    {
        return match (true) {
            $code >= 30 && $code <= 37 => self::palette($code - 30),
            $code >= 90 && $code <= 97 => self::palette($code - 90 + 8),
            default => null,
        };
    }

    /**
     * Convert an SGR background color code (40-47, 100-107) to a Color.
     *
     * Returns null if the code is not a basic/bright background color.
     */
    public static function fromSgrBackground(int $code): ?self
    {
        return match (true) {
            $code >= 40 && $code <= 47 => self::palette($code - 40),
            $code >= 100 && $code <= 107 => self::palette($code - 100 + 8),
            default => null,
        };
    }

    /**
     * Get the hex representation of this color (e.g. '#ff5500').
     */
    public function toHex(): string
    {
        $rgb = $this->toRgb();

        return \sprintf('#%02x%02x%02x', $rgb['r'], $rgb['g'], $rgb['b']);
    }

    /**
     * Get the ANSI escape code for this color as foreground.
     */
    public function toForegroundCode(): string
    {
        return match ($this->type) {
            ColorType::Named => \sprintf("\x1b[%dm", self::BASIC_COLORS[(string) $this->value]),
            ColorType::Palette => \sprintf("\x1b[38;5;%dm", (int) $this->value),
            ColorType::Hex => \sprintf(
                "\x1b[38;2;%d;%d;%dm",
                hexdec(substr((string) $this->value, 0, 2)),
                hexdec(substr((string) $this->value, 2, 2)),
                hexdec(substr((string) $this->value, 4, 2))
            ),
        };
    }

    /**
     * Get the ANSI escape code for this color as background.
     */
    public function toBackgroundCode(): string
    {
        return match ($this->type) {
            ColorType::Named => \sprintf("\x1b[%dm", self::BASIC_COLORS[(string) $this->value] + 10),
            ColorType::Palette => \sprintf("\x1b[48;5;%dm", (int) $this->value),
            ColorType::Hex => \sprintf(
                "\x1b[48;2;%d;%d;%dm",
                hexdec(substr((string) $this->value, 0, 2)),
                hexdec(substr((string) $this->value, 2, 2)),
                hexdec(substr((string) $this->value, 4, 2))
            ),
        };
    }

    /**
     * Get the ANSI reset code for foreground color.
     */
    public static function resetForeground(): string
    {
        return "\x1b[39m";
    }

    /**
     * Get the ANSI reset code for background color.
     */
    public static function resetBackground(): string
    {
        return "\x1b[49m";
    }

    private function __construct(
        private readonly ColorType $type,
        private readonly int|string $value,
    ) {
    }

    /**
     * @return array{r: int, g: int, b: int}
     */
    private static function namedToRgb(string $name): array
    {
        $rgb = self::NAMED_RGB[$name];

        return ['r' => $rgb[0], 'g' => $rgb[1], 'b' => $rgb[2]];
    }

    /**
     * @return array{r: int, g: int, b: int}
     */
    private static function paletteToRgb(int $index): array
    {
        // 0–15: basic 16 colors
        if ($index < 16) {
            $names = [
                'black', 'red', 'green', 'yellow', 'blue', 'magenta', 'cyan', 'white',
                'bright_black', 'bright_red', 'bright_green', 'bright_yellow',
                'bright_blue', 'bright_magenta', 'bright_cyan', 'bright_white',
            ];

            return self::namedToRgb($names[$index]);
        }

        // 16–231: 6×6×6 color cube
        if ($index < 232) {
            $i = $index - 16;
            $levels = [0, 95, 135, 175, 215, 255];

            return [
                'r' => $levels[(int) ($i / 36)],
                'g' => $levels[(int) (($i % 36) / 6)],
                'b' => $levels[$i % 6],
            ];
        }

        // 232–255: grayscale ramp
        $level = 8 + 10 * ($index - 232);

        return ['r' => $level, 'g' => $level, 'b' => $level];
    }

    /**
     * @return array{r: int, g: int, b: int}
     */
    private static function hexToRgb(string $hex): array
    {
        return [
            'r' => (int) hexdec(substr($hex, 0, 2)),
            'g' => (int) hexdec(substr($hex, 2, 2)),
            'b' => (int) hexdec(substr($hex, 4, 2)),
        ];
    }
}
