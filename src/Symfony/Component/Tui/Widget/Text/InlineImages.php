<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Tui\Widget\Text;

use Symfony\Component\Tui\Ansi\AnsiCodeTracker;
use Symfony\Component\Tui\Style\Color;
use Symfony\Component\Tui\Style\ColorScheme;
use Symfony\Component\Tui\Style\RasterImage;
use Symfony\Component\Tui\Style\VectorImage;

/**
 * Images drawn in the line of a text, each standing for characters of a private use plane in it, one per cell.
 *
 * @internal
 */
final class InlineImages
{
    /** The most images of a text, and as many of its generated content */
    public const MAX = 256;
    /** The first character standing for a cell of an image, of the private use plane 16 */
    private const BASE = 0x100000;

    /** @var array<string, RasterImage|null> The shapes of the images drawn, by image, color and width */
    private array $vectorCells = [];

    /**
     * @param list<array{0: RasterImage|VectorImage, 1: int, 2?: string}> $images    Each image, its columns and its text alternative
     * @param list<array{0: RasterImage|VectorImage, 1: int, 2?: string}> $generated Those of generated content, see text()
     */
    public function __construct(
        private readonly array $images = [],
        private readonly array $generated = [],
    ) {
    }

    /**
     * The characters standing for the cells of an image: one per column, that its cells take the place of when drawn.
     */
    public static function text(int $index, int $columns, bool $generated = false): string
    {
        $index = max(0, min(self::MAX - 1, $index)) + ($generated ? self::MAX : 0);
        $text = '';
        for ($column = 0; $column < max(1, min(16, $columns)); ++$column) {
            $text .= mb_chr(self::BASE + $index * 16 + $column);
        }

        return $text;
    }

    /**
     * The text without characters standing for the cells of images, like that of a page, which cannot draw any.
     */
    public static function strip(string $text): string
    {
        return str_contains($text, "\xF4") ? preg_replace('/[\x{100000}-\x{10FFFF}]/u', '', $text) ?? $text : $text;
    }

    /**
     * The text with the characters standing for the cells of images as spaces, like a shadow of it.
     */
    public static function blank(string $text): string
    {
        return str_contains($text, "\xF4") ? preg_replace('/[\x{100000}-\x{10FFFF}]/u', ' ', $text) ?? $text : $text;
    }

    public function isEmpty(): bool
    {
        return !$this->images && !$this->generated;
    }

    /**
     * The text with the characters standing for images as their text alternatives.
     */
    public function plain(string $text): string
    {
        if ($this->isEmpty() || !str_contains($text, "\xF4")) {
            return $text;
        }

        return preg_replace_callback('/[\x{100000}-\x{10FFFF}]/u', fn (array $m): string => 0 === ($code = mb_ord($m[0]) - self::BASE) % 16 ? $this->of($code)[2] ?? '' : '', $text) ?? $text;
    }

    /**
     * The characters standing for the cells of images in the lines, drawn as those cells: half blocks in the colors of
     * their two pixels, without the underline of a link, the style of the text going on after them; the shapes of an
     * <svg> in the color of the text where it is, else that of the text of the color scheme.
     *
     * @param list<string> $lines
     *
     * @return list<string>
     */
    public function draw(array $lines, ?ColorScheme $scheme): array
    {
        if ($this->isEmpty()) {
            return $lines;
        }
        foreach ($lines as $i => $line) {
            // The private use plane 16, U+100000 to U+10FFFF, in UTF-8
            if (!str_contains($line, "\xF4")) {
                continue;
            }
            $tracker = new AnsiCodeTracker();
            $out = '';
            // The escape sequences as they are, those of hyperlinks too, whose addresses are not text
            foreach (preg_split('/(\e\[[0-9;:?]*[A-Za-z]|\e\][^\x07\e]*(?:\x07|\e\\\\)|\e_[^\x07]*\x07)/', $line, -1, \PREG_SPLIT_DELIM_CAPTURE | \PREG_SPLIT_NO_EMPTY) ?: [] as $part) {
                if ("\e" === $part[0]) {
                    if (preg_match('/^\e\[[0-9;:]*m$/', $part)) {
                        $tracker->process($part);
                    }
                    $out .= $part;
                    continue;
                }
                $out .= preg_replace_callback('/[\x{100000}-\x{10FFFF}]/u', function (array $m) use ($tracker, $scheme): string {
                    $code = mb_ord($m[0]) - self::BASE;
                    [$image, $columns] = $this->of($code) ?? [null, 0];
                    if ($image instanceof VectorImage) {
                        // The color its glyphs are drawn in: in reverse video, that of the background
                        $image = $tracker->isInverse()
                            ? $this->vectorCells($image, $columns, null !== ($background = $tracker->getBackground()) ? self::foregroundOf($background) : null, $scheme, true)
                            : $this->vectorCells($image, $columns, $tracker->getForeground(), $scheme);
                    }
                    if (null === $image || $code % 16 >= $columns) {
                        return ' ';
                    }
                    [$top, $bottom] = $image->cells($columns, 1)[0][$code % 16] ?? [null, null];
                    $cell = match (true) {
                        null === $top && null === $bottom => null,
                        null === $bottom => "\e[38;2;".implode(';', $top).'m▀',
                        null === $top => "\e[38;2;".implode(';', $bottom).'m▄',
                        default => "\e[38;2;".implode(';', $top).';48;2;'.implode(';', $bottom).'m▀',
                    };

                    // Transparent: a space in the style of the text; else the cell, then the style of the text again
                    return null === $cell ? ' ' : "\e[0m".$cell."\e[0m".$tracker->getActiveCodes();
                }, $part) ?? $part;
            }
            $lines[$i] = $out;
        }

        return $lines;
    }

    /**
     * @return array{0: RasterImage|VectorImage, 1: int, 2?: string}|null
     */
    private function of(int $code): ?array
    {
        $index = intdiv($code, 16);

        return $index >= self::MAX ? $this->generated[$index - self::MAX] ?? null : $this->images[$index] ?? null;
    }

    /**
     * The shapes of an image as pixels for a color, its currentColor: the foreground set, else that of the text of
     * the color scheme.
     */
    /**
     * The SGR parameters of a background color as those of the same foreground color, like 44 as 34.
     */
    private static function foregroundOf(string $background): string
    {
        return match (true) {
            str_starts_with($background, '48') => '38'.substr($background, 2),
            ctype_digit($background) && (int) $background >= 100 => (string) ((int) $background - 10),
            ctype_digit($background) => (string) ((int) $background - 10),
            default => $background,
        };
    }

    private function vectorCells(VectorImage $image, int $columns, ?string $foreground, ?ColorScheme $scheme, bool $inverse = false): ?RasterImage
    {
        $rgb = match (true) {
            null === $foreground => null,
            1 === preg_match('/^38[;:]2[;:]+(\d+)[;:](\d+)[;:](\d+)$/', $foreground, $m) => [(int) $m[1], (int) $m[2], (int) $m[3]],
            1 === preg_match('/^38[;:]5[;:](\d+)$/', $foreground, $m) => array_values(Color::palette(min(255, (int) $m[1]))->toRgb()),
            ctype_digit($foreground) => null !== ($color = Color::fromSgrForeground((int) $foreground)) ? array_values($color->toRgb()) : null,
            default => null,
        };
        // The text of the color scheme, or its background in reverse video
        $rgb ??= (ColorScheme::Light === $scheme) !== $inverse ? [0, 0, 0] : [229, 229, 229];
        $rgb = array_map(static fn (mixed $channel): int => max(0, min(255, (int) $channel)), \array_slice($rgb, 0, 3));
        $key = spl_object_id($image).'@'.implode(',', $rgb).'@'.$columns;
        if (!\array_key_exists($key, $this->vectorCells)) {
            if (\count($this->vectorCells) >= 64) {
                $this->vectorCells = [];
            }
            // Four pixels a column, two rows of them a cell
            $png = $image->toPng($columns * 4, 8, $rgb);
            $this->vectorCells[$key] = null !== $png ? new RasterImage($png) : null;
        }

        return $this->vectorCells[$key];
    }
}
