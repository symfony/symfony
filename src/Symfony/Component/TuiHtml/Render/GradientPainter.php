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

use Symfony\Component\TuiHtml\Style\Color;
use Symfony\Component\TuiHtml\Style\Gradient;
use Symfony\Component\TuiHtml\Style\RasterImage;

/**
 * Paints a gradient over the cells of a box that have its background color, or over their glyphs.
 *
 * The element's own background is where nothing over it has a background of
 * its own: those cells take the color of the gradient there, the others,
 * like a child with its own background, are left as they are.
 *
 * @internal
 */
final class GradientPainter
{
    /**
     * @param list<string>                                                $lines      The lines of the element, its margins included
     * @param Gradient|Color                                              $paint      What is painted: a gradient, or a color for the glyphs of background-clip: text
     * @param Color|null                                                  $background The background of the cells to paint, null for none
     * @param int                                                         $top        The first row painted, the clip box
     * @param int                                                         $left       The first column painted
     * @param (\Closure(int, int): ?array{int, int, int, int})|null       $sample     The cell of the image a cell of the clip box shows, and the size of the image; null for none, the clip box being the image by default
     * @param bool                                                        $text       Whether the glyphs are painted, not the cells, like background-clip: text
     * @param (\Closure(array{int, int, int}): array{int, int, int})|null $blend      How a color painted blends with the background color, like background-blend-mode; null to paint it as it is
     * @param (\Closure(int): ?int)|null                                  $rowOf      The row of the image a row of the clip box shows, null for none: two rows that show the same one, with the same text, are painted the same; null when it is not known
     *
     * @return list<string>
     */
    public static function paint(array $lines, Gradient|Color|RasterImage $paint, ?Color $background, int $top, int $left, int $columns, int $rows, ?\Closure $sample = null, bool $text = false, ?\Closure $blend = null, ?\Closure $rowOf = null): array
    {
        if ($columns <= 0 || $rows <= 0) {
            return $lines;
        }
        $base = null !== $background ? self::backgroundKey(self::parameters(substr($background->toBackgroundCode(), 2, -1)), null) : null;
        $sample ??= static fn (int $x, int $y) => [$x, $y, $columns, $rows];
        // The colors of the cells, by where they are on a gradient, the cells of a diagonal of a box thousands of rows
        // tall sharing theirs, or else by the cell of the image
        $colors = [];
        // A horizontal gradient is the same on every row
        $horizontal = $paint instanceof Gradient && $paint->isHorizontal();
        $gradient = $paint instanceof Gradient;
        $colorAt = static function (int $x, int $y) use ($paint, $sample, $text, $blend, $background, $gradient, &$colors): string|array|null {
            $cell = $sample($x, $y);
            $key = null === $cell ? 'none' : ($gradient ? $paint->placeAt($cell[0], $cell[1], $cell[2], $cell[3]) : $cell[0].':'.$cell[1].':'.$cell[2].':'.$cell[3]);
            if (\array_key_exists($key, $colors)) {
                return $colors[$key];
            }
            // Bounded, a box of thousands of rows painted too
            if (\count($colors) >= 65536) {
                $colors = [];
            }
            if (null !== $cell && $paint instanceof RasterImage) {
                // The two pixels of the cell, or for the glyphs their average
                [$top, $bottom] = $paint->cellAt(max(1, $cell[2]), max(1, $cell[3]), $cell[0], $cell[1]);
                if (null !== $blend) {
                    $top = null !== $top ? $blend($top) : null;
                    $bottom = null !== $bottom ? $blend($bottom) : null;
                }
                if ($text) {
                    return $colors[$key] = null === ($average = self::average($top, $bottom)) ? null : '38;2;'.implode(';', $average);
                }

                return $colors[$key] = null === $top && null === $bottom ? null : [$top, $bottom];
            }
            $color = null === $cell || $paint instanceof RasterImage ? null : ($paint instanceof Gradient ? $paint->paintAt(...[...$cell, $paint->backdrop ? null : $background]) : $paint);
            // Transparent over a box without a background color of its own: none, what is behind it showing
            if (null === $color && null !== $cell && $paint instanceof Gradient && null !== $paint->backdrop && !$text) {
                return $colors[$key] = '49';
            }
            if (null !== $color && null !== $blend) {
                $rgb = $color->toRgb();
                $color = Color::rgb(...$blend([$rgb['r'], $rgb['g'], $rgb['b']]));
            }

            return $colors[$key] = null === $color ? null : substr($text ? $color->toForegroundCode() : $color->toBackgroundCode(), 2, -1);
        };
        // A row like one painted before, the same text over the same row of the image, painted like it: the rows of a
        // tall box with a horizontal gradient, or with an image repeated down it, painted once for each row of the image
        $painted = [];
        for ($row = 0; $row < $rows && isset($lines[$top + $row]); ++$row) {
            $line = $lines[$top + $row];
            // A narrow box, like a float of a column: by the colors of its cells, rows past the circle of a radial gradient
            // of the same, a box thousands of rows tall painted once for each of its colors
            if ($columns <= 8) {
                $key = 'cells';
                for ($x = 0; $x < $columns; ++$x) {
                    $color = $colorAt($x, $row);
                    $key .= ':'.(\is_array($color) ? json_encode($color) : $color);
                }
                $key .= '|'.$line;
            } else {
                $key = null === $rowOf ? null : (null !== ($imageRow = $rowOf($row)) && $horizontal ? 'shown' : $imageRow).'|'.$line;
            }
            if (null !== $key && isset($painted[$key])) {
                $lines[$top + $row] = $painted[$key];
                continue;
            }
            $lines[$top + $row] = self::paintLine($line, $colorAt, $base, $left, $columns, $row, $text);
            if (null !== $key) {
                // Bounded, a box of thousands of rows of text painted too
                if (\count($painted) >= 1024) {
                    $painted = [];
                }
                $painted[$key] = $lines[$top + $row];
            }
        }

        return $lines;
    }

    /**
     * Paints the cells of a box that have its background with the two pixels a callable gives for each, like those of an
     * image: half blocks in the spaces, the glyphs over the average of the two.
     *
     * @param list<string>                                                                 $lines  The lines of the element, its margins included
     * @param \Closure(int, int): (array{array{int, int, int}, array{int, int, int}}|null) $pixels The two pixels of a cell of the area, by its column and row in it, null for none
     * @param (\Closure(int): string)|null                                                 $rowKey What the pixels of a row of the area depend on: two rows of the same key, with the same text, are painted the same; null when it is not known
     *
     * @return list<string>
     */
    public static function paintPixels(array $lines, \Closure $pixels, ?Color $background, int $top, int $left, int $columns, int $rows, ?\Closure $rowKey = null): array
    {
        if ($columns <= 0 || $rows <= 0) {
            return $lines;
        }
        $base = null !== $background ? self::backgroundKey(self::parameters(substr($background->toBackgroundCode(), 2, -1)), null) : null;
        $colors = [];
        $colorAt = static function (int $x, int $y) use ($pixels, &$colors): ?array {
            if (\count($colors) >= 65536) {
                $colors = [];
            }

            return $colors[$x.':'.$y] ??= $pixels($x, $y);
        };
        $painted = [];
        for ($row = 0; $row < $rows && isset($lines[$top + $row]); ++$row) {
            $line = $lines[$top + $row];
            $key = null === $rowKey ? null : $rowKey($row).'|'.$line;
            if (null !== $key && isset($painted[$key])) {
                $lines[$top + $row] = $painted[$key];
                continue;
            }
            $lines[$top + $row] = self::paintLine($line, $colorAt, $base, $left, $columns, $row, false);
            if (null !== $key) {
                // Bounded, a box of thousands of rows of text painted too
                if (\count($painted) >= 1024) {
                    $painted = [];
                }
                $painted[$key] = $lines[$top + $row];
            }
        }

        return $lines;
    }

    /**
     * @param \Closure(int, int): (string|array{?array{int, int, int}, ?array{int, int, int}}|null) $colorAt
     */
    private static function paintLine(string $line, \Closure $colorAt, ?string $base, int $left, int $columns, int $row, bool $text): string
    {
        $out = '';
        $column = 0;
        $current = null;
        // The background of the source at this point, as a key, and the sequences that set it and the foreground back
        $restore = '49';
        $foreground = '39';
        $painted = null;
        foreach (preg_split('/(\e\[[0-9;:]*m|\e\][^\a\e]*(?:\a|\e\\\\)|\e_[^\a]*\a|\e\[[0-9;?]*[A-Za-z])/', $line, -1, \PREG_SPLIT_DELIM_CAPTURE | \PREG_SPLIT_NO_EMPTY) ?: [] as $part) {
            if ("\x1b" === $part[0]) {
                $out .= $part;
                if (preg_match('/^\e\[([0-9;:]*)m$/', $part, $m)) {
                    $parameters = self::parameters($m[1]);
                    $foreground = self::foregroundSequence($parameters) ?? $foreground;
                    $key = self::backgroundKey($parameters, $current);
                    if ($key !== $current) {
                        $current = $key;
                        $restore = self::backgroundSequence($parameters) ?? '49';
                        $painted = null;
                    } elseif (null !== $painted && self::touchesBackground($parameters)) {
                        $painted = null;
                    }
                }
                continue;
            }
            // Printable ASCII, like the spaces of a background, a cell a byte: not split into graphemes
            $ascii = '' === trim($part, ' ..~');
            foreach (($ascii ? str_split($part) : grapheme_str_split($part)) ?: [] as $grapheme) {
                $x = $column - $left;
                $width = $ascii ? 1 : max(1, mb_strwidth($grapheme));
                $wanted = null;
                // Glyphs are painted whatever is behind them: those of what the box holds too
                if ($x >= 0 && $x < $columns && ($text || $current === $base)) {
                    $wanted = $colorAt($x, $row);
                }
                $column += $width;
                if (\is_array($wanted)) {
                    // A cell of an image: a half block in a space, else the glyph over the average of the two pixels
                    if (null !== $painted) {
                        $out .= "\x1b[".$restore.'m';
                        $painted = null;
                    }
                    [$top, $bottom] = $wanted;
                    if (' ' === $grapheme) {
                        $out .= match (true) {
                            null === $bottom => "\x1b[38;2;".implode(';', $top).'m▀',
                            null === $top => "\x1b[38;2;".implode(';', $bottom).'m▄',
                            default => "\x1b[38;2;".implode(';', $top).';48;2;'.implode(';', $bottom).'m▀',
                        }."\x1b[".$foreground.';'.$restore.'m';
                    } else {
                        $out .= "\x1b[48;2;".implode(';', self::average($top, $bottom)).'m'.$grapheme."\x1b[".$restore.'m';
                    }
                    continue;
                }
                if ($text) {
                    // The glyph in the color of the background there, the cell as it is
                    $out .= null !== $wanted && '' !== trim($grapheme) ? "\x1b[".$wanted.'m'.$grapheme."\x1b[".$foreground.'m' : $grapheme;
                    continue;
                }
                if ($wanted !== $painted) {
                    $out .= "\x1b[".($wanted ?? $restore).'m';
                    $painted = $wanted;
                }
                $out .= $grapheme;
            }
        }

        return null !== $painted ? $out."\x1b[".$restore.'m' : $out;
    }

    /**
     * The average of two pixels, either of them when the other is transparent; null when both are.
     *
     * @param array{int, int, int}|null $top
     * @param array{int, int, int}|null $bottom
     *
     * @return array{int, int, int}|null
     */
    private static function average(?array $top, ?array $bottom): ?array
    {
        if (null === $top || null === $bottom) {
            return $top ?? $bottom;
        }

        return [intdiv($top[0] + $bottom[0], 2), intdiv($top[1] + $bottom[1], 2), intdiv($top[2] + $bottom[2], 2)];
    }

    /**
     * @return list<string>
     */
    private static function parameters(string $parameters): array
    {
        return '' === $parameters ? ['0'] : explode(';', str_replace(':', ';', $parameters));
    }

    /**
     * The background a sequence leaves, as a key: null for the terminal's.
     *
     * @param list<string> $parameters
     */
    private static function backgroundKey(array $parameters, ?string $current): ?string
    {
        for ($i = 0, $count = \count($parameters); $i < $count; ++$i) {
            $code = (int) $parameters[$i];
            if (\in_array($code, [38, 58], true)) {
                $i += '2' === ($parameters[$i + 1] ?? null) ? 4 : 2;
            } elseif (48 === $code) {
                $current = '2' === ($parameters[$i + 1] ?? null) ? 'rgb:'.implode(',', \array_slice($parameters, $i + 2, 3)) : 'palette:'.($parameters[$i + 2] ?? '');
                $i += '2' === ($parameters[$i + 1] ?? null) ? 4 : 2;
            } elseif (0 === $code || 49 === $code) {
                $current = null;
            } elseif ($code >= 40 && $code <= 47 || $code >= 100 && $code <= 107) {
                $current = 'palette:'.($code >= 100 ? $code - 92 : $code - 40);
            }
        }

        return $current;
    }

    /**
     * The parameters that set the background in a sequence, null when none does.
     *
     * @param list<string> $parameters
     */
    private static function backgroundSequence(array $parameters): ?string
    {
        $sequence = null;
        for ($i = 0, $count = \count($parameters); $i < $count; ++$i) {
            $code = (int) $parameters[$i];
            if (\in_array($code, [38, 58], true)) {
                $i += '2' === ($parameters[$i + 1] ?? null) ? 4 : 2;
            } elseif (48 === $code) {
                $length = '2' === ($parameters[$i + 1] ?? null) ? 5 : 3;
                $sequence = implode(';', \array_slice($parameters, $i, $length));
                $i += $length - 1;
            } elseif (0 === $code || 49 === $code || $code >= 40 && $code <= 47 || $code >= 100 && $code <= 107) {
                $sequence = 0 === $code ? '49' : (string) $code;
            }
        }

        return $sequence;
    }

    /**
     * The parameters that set the foreground in a sequence, null when none does.
     *
     * @param list<string> $parameters
     */
    private static function foregroundSequence(array $parameters): ?string
    {
        $sequence = null;
        for ($i = 0, $count = \count($parameters); $i < $count; ++$i) {
            $code = (int) $parameters[$i];
            if (\in_array($code, [38, 48, 58], true)) {
                $length = '2' === ($parameters[$i + 1] ?? null) ? 5 : 3;
                if (38 === $code) {
                    $sequence = implode(';', \array_slice($parameters, $i, $length));
                }
                $i += $length - 1;
            } elseif (0 === $code || 39 === $code || $code >= 30 && $code <= 37 || $code >= 90 && $code <= 97) {
                $sequence = 0 === $code ? '39' : (string) $code;
            }
        }

        return $sequence;
    }

    /**
     * @param list<string> $parameters
     */
    private static function touchesBackground(array $parameters): bool
    {
        return null !== self::backgroundSequence($parameters);
    }
}
