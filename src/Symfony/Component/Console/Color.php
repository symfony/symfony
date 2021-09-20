<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Console;

use Symfony\Component\Console\Exception\InvalidArgumentException;

/**
 * @author Fabien Potencier <fabien@symfony.com>
 */
final class Color
{
    private const COLORS = [
        'black' => 0,
        'red' => 1,
        'green' => 2,
        'yellow' => 3,
        'blue' => 4,
        'magenta' => 5,
        'cyan' => 6,
        'white' => 7,
        'default' => 9,
    ];

    private const BRIGHT_COLORS = [
        'gray' => 0,
        'bright-red' => 1,
        'bright-green' => 2,
        'bright-yellow' => 3,
        'bright-blue' => 4,
        'bright-magenta' => 5,
        'bright-cyan' => 6,
        'bright-white' => 7,
    ];

    private const AVAILABLE_OPTIONS = [
        'bold' => ['set' => 1, 'unset' => 22],
        'underscore' => ['set' => 4, 'unset' => 24],
        'blink' => ['set' => 5, 'unset' => 25],
        'reverse' => ['set' => 7, 'unset' => 27],
        'conceal' => ['set' => 8, 'unset' => 28],
    ];

    private const RGB_FUNCTIONAL_NOTATION_REGEX = '/^rgb\(\s*(\d+)\s*,\s*(\d+)\s*,\s*(\d+)\s*\)$/';
    private const HSL_FUNCTIONAL_NOTATION_REGEX = '/^hsl\(\s*(\d+)\s*,\s*(\d+)%\s*,\s*(\d+)%\s*\)$/';

    private $foreground;
    private $background;
    private $options = [];

    public function __construct(string $foreground = '', string $background = '', array $options = [])
    {
        $this->foreground = $this->parseColor($foreground);
        $this->background = $this->parseColor($background, true);

        foreach ($options as $option) {
            if (!isset(self::AVAILABLE_OPTIONS[$option])) {
                throw new InvalidArgumentException(sprintf('Invalid option specified: "%s". Expected one of (%s).', $option, implode(', ', array_keys(self::AVAILABLE_OPTIONS))));
            }

            $this->options[$option] = self::AVAILABLE_OPTIONS[$option];
        }
    }

    public function apply(string $text): string
    {
        return $this->set().$text.$this->unset();
    }

    public function set(): string
    {
        $setCodes = [];
        if ('' !== $this->foreground) {
            $setCodes[] = $this->foreground;
        }
        if ('' !== $this->background) {
            $setCodes[] = $this->background;
        }
        foreach ($this->options as $option) {
            $setCodes[] = $option['set'];
        }
        if (0 === \count($setCodes)) {
            return '';
        }

        return sprintf("\033[%sm", implode(';', $setCodes));
    }

    public function unset(): string
    {
        $unsetCodes = [];
        if ('' !== $this->foreground) {
            $unsetCodes[] = 39;
        }
        if ('' !== $this->background) {
            $unsetCodes[] = 49;
        }
        foreach ($this->options as $option) {
            $unsetCodes[] = $option['unset'];
        }
        if (0 === \count($unsetCodes)) {
            return '';
        }

        return sprintf("\033[%sm", implode(';', $unsetCodes));
    }

    private function parseColor(string $color, bool $background = false): string
    {
        if ('' === $color) {
            return '';
        }

        if (str_starts_with($color, 'rgb(')) {
            $color = $this->rgbToHex($color);
        } elseif (str_starts_with($color, 'hsl(')) {
            $color = $this->hslToHex($color);
        }

        if ('#' === $color[0]) {
            $color = substr($color, 1);

            if (3 === \strlen($color)) {
                $color = $color[0].$color[0].$color[1].$color[1].$color[2].$color[2];
            }

            if (6 !== \strlen($color)) {
                throw new InvalidArgumentException(sprintf('Invalid "%s" color.', $color));
            }

            return ($background ? '4' : '3').$this->convertHexColorToAnsi(hexdec($color));
        }

        if (isset(self::COLORS[$color])) {
            return ($background ? '4' : '3').self::COLORS[$color];
        }

        if (isset(self::BRIGHT_COLORS[$color])) {
            return ($background ? '10' : '9').self::BRIGHT_COLORS[$color];
        }

        throw new InvalidArgumentException(sprintf('Invalid "%s" color; expected one of (%s).', $color, implode(', ', array_merge(array_keys(self::COLORS), array_keys(self::BRIGHT_COLORS)))));
    }

    private function convertHexColorToAnsi(int $color): string
    {
        $r = ($color >> 16) & 255;
        $g = ($color >> 8) & 255;
        $b = $color & 255;

        // see https://github.com/termstandard/colors/ for more information about true color support
        if ('truecolor' !== getenv('COLORTERM')) {
            return (string) $this->degradeHexColorToAnsi($r, $g, $b);
        }

        return sprintf('8;2;%d;%d;%d', $r, $g, $b);
    }

    private function degradeHexColorToAnsi(int $r, int $g, int $b): int
    {
        if (0 === round($this->getSaturation($r, $g, $b) / 50)) {
            return 0;
        }

        return (round($b / 255) << 2) | (round($g / 255) << 1) | round($r / 255);
    }

    private function getSaturation(int $r, int $g, int $b): int
    {
        $r = $r / 255;
        $g = $g / 255;
        $b = $b / 255;
        $v = max($r, $g, $b);

        if (0 === $diff = $v - min($r, $g, $b)) {
            return 0;
        }

        return (int) $diff * 100 / $v;
    }

    private function rgbToHex(string $color): string
    {
        if (!preg_match(self::RGB_FUNCTIONAL_NOTATION_REGEX, $color, $matches)) {
            throw new InvalidArgumentException(sprintf('Invalid RGB functional notation; should be of the form "rgb(r, g, b)", got "%s".', $color));
        }

        $rgb = \array_slice($matches, 1);

        $hexString = array_map(function ($element) {
            if ($element > 255) {
                throw new InvalidArgumentException(sprintf('Invalid color component; value should be between 0 and 255, got %d.', $element));
            }

            return str_pad(dechex((int) $element), 2, '0', \STR_PAD_LEFT);
        }, $rgb);

        return '#'.implode('', $hexString);
    }

    /**
     * Explained formula can be found here: {@see https://en.wikipedia.org/wiki/HSL_and_HSV#HSL_to_RGB}.
     */
    private function hslToHex(string $color): string
    {
        if (!preg_match(self::HSL_FUNCTIONAL_NOTATION_REGEX, $color, $matches)) {
            throw new InvalidArgumentException(sprintf('Invalid HSL functional notation; should be of the form "hsl(h, s%%, l%%)", got "%s".', $color));
        }

        [$hue, $saturation, $lightness] = \array_slice($matches, 1);

        if ($hue > 359) {
            throw new InvalidArgumentException(sprintf('Invalid hue; value should be between 0 and 359, got %d.', $hue));
        }

        if ($saturation > 100) {
            throw new InvalidArgumentException(sprintf('Invalid saturation; value should be between 0 and 100, got %d.', $saturation));
        }

        if ($lightness > 100) {
            throw new InvalidArgumentException(sprintf('Invalid lightness; value should be between 0 and 100, got %d.', $lightness));
        }

        $hue = fmod($hue / 60, 6);
        $saturation /= 100;
        $lightness /= 100;

        $chroma = (1 - abs((2 * $lightness) - 1)) * $saturation;
        $x = $chroma * (1 - abs(fmod($hue, 2) - 1));

        if ($hue < 1) {
            [$r, $g, $b] = [$chroma, $x, 0];
        } elseif ($hue < 2) {
            [$r, $g, $b] = [$x, $chroma, 0];
        } elseif ($hue < 3) {
            [$r, $g, $b] = [0, $chroma, $x];
        } elseif ($hue < 4) {
            [$r, $g, $b] = [0, $x, $chroma];
        } elseif ($hue < 5) {
            [$r, $g, $b] = [$x, 0, $chroma];
        } else {
            [$r, $g, $b] = [$chroma, 0, $x];
        }

        $m = $lightness - $chroma / 2;
        $rgb = [
            ($r + $m) * 255,
            ($g + $m) * 255,
            ($b + $m) * 255,
        ];

        $hexString = array_map(function ($element) {
            return str_pad(dechex((int) $element), 2, '0', \STR_PAD_LEFT);
        }, $rgb);

        return '#'.implode('', $hexString);
    }
}
