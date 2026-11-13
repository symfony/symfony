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

use Symfony\Component\Tui\Style\Color;
use Symfony\Component\Tui\Style\ColorScheme;

/**
 * Rewrites the colors of rendered lines, cell by cell, like a backdrop tint or a CSS filter.
 *
 * Each cell has a foreground, a background and an underline color: the
 * transform gets each of them as RGB, with what is behind it (the cell
 * background for the foreground and the underline, the terminal
 * background for the background), and returns the color drawn. The default
 * foreground is the one of the color scheme; the terminal background is
 * unknown, so cells on it keep it, unless a color is given to paint them.
 *
 * @internal
 */
final class ColorRewriter
{
    /** @var array<string, string> Rewritten sequences by colors, they repeat a lot */
    private array $cache = [];

    /** @var array<string, string> Rewritten lines, which repeat too, like the rows of a table or of a blank box */
    private array $lines = [];

    /** @var array{int, int, int} */
    private readonly array $defaultForeground;
    /** @var array{int, int, int} */
    private readonly array $defaultBackground;

    /**
     * @param \Closure(array{int, int, int}, array{int, int, int}): array{int, int, int} $transform A color, what is behind it, and the color drawn
     * @param array{int, int, int}|null                                                  $paint     The color the cells on the terminal background are painted, before the transform, null to keep it
     */
    public function __construct(
        private readonly \Closure $transform,
        ColorScheme $scheme,
        private readonly ?array $paint = null,
    ) {
        $this->defaultForeground = ColorScheme::Light === $scheme ? [0, 0, 0] : [229, 229, 229];
        $this->defaultBackground = ColorScheme::Light === $scheme ? [255, 255, 255] : [0, 0, 0];
    }

    public function line(string $line): string
    {
        if (isset($this->lines[$line])) {
            return $this->lines[$line];
        }
        // Bounded, a box of thousands of different lines rewritten too
        if (\count($this->lines) >= 1_024) {
            $this->lines = [];
        }

        return $this->lines[$line] = $this->rewrite($line);
    }

    private function rewrite(string $line): string
    {
        $foreground = $this->defaultForeground;
        $background = null;
        $underline = null;
        $out = $last = $this->colors($foreground, $background, $underline);
        foreach (preg_split('/(\e\[[0-9;:]*m)/', $line, -1, \PREG_SPLIT_DELIM_CAPTURE | \PREG_SPLIT_NO_EMPTY) ?: [] as $part) {
            if (!preg_match('/^\e\[([0-9;:]*)m$/', $part, $m)) {
                $out .= $part;
                continue;
            }
            // What the sequence sets, its colors replaced by the rewritten ones
            $kept = [];
            $parts = '' === $m[1] ? ['0'] : explode(';', $m[1]);
            for ($i = 0, $count = \count($parts); $i < $count; ++$i) {
                // The colon form keeps a color in one parameter: 38:2::255:0:0 or 38:2:255:0:0
                $sub = explode(':', $parts[$i]);
                $code = (int) $sub[0];
                if (\in_array($code, [38, 48, 58], true)) {
                    $rgb = null;
                    if (\count($sub) > 1) {
                        if ('2' === $sub[1] && \count($sub) >= 5) {
                            $last = \count($sub) - 1;
                            $rgb = [(int) $sub[$last - 2], (int) $sub[$last - 1], (int) $sub[$last]];
                        } elseif ('5' === $sub[1] && isset($sub[2])) {
                            $rgb = self::palette((int) $sub[2]);
                        }
                    } elseif ('2' === ($parts[$i + 1] ?? null) && isset($parts[$i + 4])) {
                        $rgb = [(int) $parts[$i + 2], (int) $parts[$i + 3], (int) $parts[$i + 4]];
                        $i += 4;
                    } elseif ('5' === ($parts[$i + 1] ?? null) && isset($parts[$i + 2])) {
                        $rgb = self::palette((int) $parts[$i + 2]);
                        $i += 2;
                    }
                    if (null !== $rgb) {
                        match ($code) {
                            38 => $foreground = $rgb,
                            48 => $background = $rgb,
                            default => $underline = $rgb,
                        };
                    }
                    continue;
                }
                match (true) {
                    0 === $code && 1 === \count($sub) => [$foreground, $background, $underline, $kept[]] = [$this->defaultForeground, null, null, '0'],
                    39 === $code => $foreground = $this->defaultForeground,
                    49 === $code => $background = null,
                    59 === $code => $underline = null,
                    $code >= 30 && $code <= 37 => $foreground = self::palette($code - 30),
                    $code >= 90 && $code <= 97 => $foreground = self::palette($code - 82),
                    $code >= 40 && $code <= 47 => $background = self::palette($code - 40),
                    $code >= 100 && $code <= 107 => $background = self::palette($code - 92),
                    default => $kept[] = $parts[$i],
                };
            }
            $colors = $this->colors($foreground, $background, $underline);
            $reset = \in_array('0', $kept, true);
            // The colors again only when they change, or after a reset undid them
            $out .= ($kept ? "\e[".implode(';', $kept).'m' : '').($reset || $colors !== $last ? $colors : '');
            $last = $colors;
        }

        return $out."\e[0m";
    }

    /**
     * @return array{int, int, int}
     */
    private static function palette(int $index): array
    {
        $rgb = Color::palette(max(0, min(255, $index)))->toRgb();

        return [$rgb['r'], $rgb['g'], $rgb['b']];
    }

    /**
     * @param array{int, int, int}      $foreground
     * @param array{int, int, int}|null $background Null for the terminal background
     * @param array{int, int, int}|null $underline  Null for the color of the text
     */
    private function colors(array $foreground, ?array $background, ?array $underline): string
    {
        $background ??= $this->paint;
        $key = implode(',', $foreground).'|'.implode(',', $background ?? []).'|'.implode(',', $underline ?? []);
        if (isset($this->cache[$key])) {
            return $this->cache[$key];
        }
        if (\count($this->cache) > 4_096) {
            $this->cache = [];
        }

        $behind = $background ?? $this->defaultBackground;
        $rgb = fn (array $color, array $behind): string => implode(';', array_map(static fn (int $c) => max(0, min(255, $c)), ($this->transform)($color, $behind)));
        $sequence = "\e[38;2;".$rgb($foreground, $behind).(null !== $background ? ';48;2;'.$rgb($background, $this->defaultBackground) : ';49');
        if (null !== $underline) {
            $sequence .= ';58;2;'.$rgb($underline, $behind);
        }

        return $this->cache[$key] = $sequence.'m';
    }
}
