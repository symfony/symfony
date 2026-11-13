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

/**
 * Reduces the colors of rendered lines to what the terminal draws.
 *
 * True colors (SGR 38;2, 48;2 and 58;2) become the closest of the 256-color
 * palette, or of the 16 basic colors; palette colors become basic colors
 * when the terminal has only those.
 *
 * @internal
 */
final class ColorDepth
{
    /**
     * The 16 basic colors, as xterm draws them.
     */
    private const BASIC = [[0, 0, 0], [205, 0, 0], [0, 205, 0], [205, 205, 0], [0, 0, 238], [205, 0, 205], [0, 205, 205], [229, 229, 229], [127, 127, 127], [255, 0, 0], [0, 255, 0], [255, 255, 0], [92, 92, 255], [255, 0, 255], [0, 255, 255], [255, 255, 255]];
    private const CUBE = [0, 95, 135, 175, 215, 255];
    /** @var array<string, string> Reduced sequences by sequence, they repeat a lot */
    private array $sequences = [];

    public function __construct(public readonly int $colors)
    {
    }

    public function reduce(string $line): string
    {
        if (!str_contains($line, "\x1b[")) {
            return $line;
        }
        // Images bring many colors: the cache does not grow without bound
        if (\count($this->sequences) > 20000) {
            $this->sequences = [];
        }

        return preg_replace_callback('/\e\[([0-9;]*)m/', fn (array $m) => $this->sequences[$m[0]] ??= $this->reduceSequence($m[1]), $line) ?? $line;
    }

    private function reduceSequence(string $parameters): string
    {
        $parts = explode(';', $parameters);
        $out = [];
        for ($i = 0, $count = \count($parts); $i < $count; ++$i) {
            $code = $parts[$i];
            if (!\in_array($code, ['38', '48', '58'], true) || !isset($parts[$i + 1])) {
                $out[] = $code;
                continue;
            }
            if ('2' === $parts[$i + 1] && isset($parts[$i + 4])) {
                $rgb = [(int) $parts[$i + 2], (int) $parts[$i + 3], (int) $parts[$i + 4]];
                $i += 4;
            } elseif ('5' === $parts[$i + 1] && isset($parts[$i + 2])) {
                $index = (int) $parts[$i + 2];
                $i += 2;
                if ($this->colors >= 256 || $index < min(16, $this->colors)) {
                    array_push($out, $code, '5', (string) $index);
                    continue;
                }
                $rgb = self::paletteRgb($index);
            } else {
                $out[] = $code;
                continue;
            }
            if ($this->colors >= 256) {
                array_push($out, $code, '5', (string) self::nearest256($rgb));
            } else {
                $basic = self::nearestBasic($rgb, $this->colors >= 16 ? 16 : 8);
                // The underline color has no basic form: its palette index
                $out = [...$out, ...match ($code) {
                    '38' => [(string) ($basic < 8 ? 30 + $basic : 82 + $basic)],
                    '48' => [(string) ($basic < 8 ? 40 + $basic : 92 + $basic)],
                    default => ['58', '5', (string) $basic],
                }];
            }
        }

        return "\x1b[".implode(';', $out).'m';
    }

    /**
     * @param array{int, int, int} $rgb
     */
    private static function nearest256(array $rgb): int
    {
        $level = static function (int $value): int {
            for ($i = 0; $i < 5 && $value > self::CUBE[$i] + self::CUBE[$i + 1] >> 1; ++$i) {
            }

            return $i;
        };
        [$r, $g, $b] = array_map($level, $rgb);
        $cube = [self::CUBE[$r], self::CUBE[$g], self::CUBE[$b]];
        $gray = max(0, min(23, (int) round((array_sum($rgb) / 3 - 8) / 10)));
        $grayValue = 8 + 10 * $gray;

        return self::distance($rgb, $cube) <= self::distance($rgb, [$grayValue, $grayValue, $grayValue]) ? 16 + 36 * $r + 6 * $g + $b : 232 + $gray;
    }

    /**
     * @param array{int, int, int} $rgb
     */
    private static function nearestBasic(array $rgb, int $count): int
    {
        $best = 0;
        $bestDistance = \PHP_INT_MAX;
        for ($i = 0; $i < $count; ++$i) {
            if (($distance = self::distance($rgb, self::BASIC[$i])) < $bestDistance) {
                $best = $i;
                $bestDistance = $distance;
            }
        }

        return $best;
    }

    /**
     * @return array{int, int, int}
     */
    private static function paletteRgb(int $index): array
    {
        if ($index < 16) {
            return self::BASIC[max(0, $index)];
        }
        if ($index >= 232) {
            $value = 8 + 10 * (min(255, $index) - 232);

            return [$value, $value, $value];
        }
        $index -= 16;

        return [self::CUBE[intdiv($index, 36)], self::CUBE[intdiv($index, 6) % 6], self::CUBE[$index % 6]];
    }

    /**
     * Distance between two colors, weighted like the eye (redmean).
     *
     * @param array{int, int, int} $a
     * @param array{int, int, int} $b
     */
    private static function distance(array $a, array $b): int
    {
        $mean = $a[0] + $b[0] >> 1;
        [$dr, $dg, $db] = [$a[0] - $b[0], $a[1] - $b[1], $a[2] - $b[2]];

        return ((512 + $mean) * $dr * $dr >> 8) + 4 * $dg * $dg + ((767 - $mean) * $db * $db >> 8);
    }
}
