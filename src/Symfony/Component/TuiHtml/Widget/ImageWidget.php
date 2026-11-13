<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\TuiHtml\Widget;

use Symfony\Component\Tui\Ansi\AnsiUtils;
use Symfony\Component\Tui\Widget\AbstractWidget;
use Symfony\Component\TuiHtml\Internal\ContextBridge;
use Symfony\Component\TuiHtml\Internal\TextMarkers;
use Symfony\Component\TuiHtml\Render\RenderContext;
use Symfony\Component\TuiHtml\Style\RasterImage;

/**
 * Draws a raster image (PNG, JPEG, GIF, WebP...) with half blocks.
 *
 * Each cell shows two pixels, one above the other: the upper half block
 * takes the color of the top one, the background the color of the bottom
 * one. Terminal cells are about twice as tall as wide, so the pixels are
 * square. Transparent pixels show what is behind. It needs a terminal with
 * true colors and the GD extension; without it, or when the image cannot be
 * decoded, the alternative text is shown in brackets instead.
 *
 * @experimental
 */
class ImageWidget extends AbstractWidget implements ReplacedElementInterface
{
    /**
     * The largest image decoded, in pixels: what is larger shows its alternative text.
     */
    public const MAX_PIXELS = RasterImage::MAX_PIXELS;
    /**
     * The pixels of one column at the natural size, like a cell 8px wide.
     */
    private const CELL_PIXELS = 8;
    private ?RasterImage $image = null;
    private int $width = 0;
    private int $height = 0;
    /** @var array<string, list<string>> */
    protected array $rendered = [];
    private ?int $boxColumns = null;
    private ?int $boxRows = null;
    private ?string $fit = null;
    /** @var array{float, float, 2?: int, 3?: int} */
    private array $position = [0.5, 0.5];
    /** @var list<array{'rect'|'circle'|'poly'|'default', list<float>, string, 3?: string}> See setAreas() */
    protected array $areas = [];
    /** The area shown as the one with the focus, reversed, the cursor on its first cell; null for none */
    protected ?int $currentArea = null;
    /** @var array{float, float}|null */
    private ?array $areaPixels = null;
    private bool $fromImage = true;
    private bool $pixelated = false;

    /**
     * @param string|\Closure(): ?string $data        The encoded image, or what reads it when it is first drawn (null when it cannot)
     * @param string                     $alternative The text shown when the image cannot be drawn, like the alt attribute
     * @param int|null                   $columns     The width in columns (null = the image width, 8 pixels per column, within the room)
     * @param int|null                   $rows        The height in rows (null = following the width and the aspect ratio)
     */
    public function __construct(private readonly string|\Closure $data, private readonly string $alternative = '', private readonly ?int $columns = null, private readonly ?int $rows = null)
    {
        if ('' !== $alternative) {
            $this->setLabel($alternative);
        }
    }

    /**
     * Makes regions of the image links, like the <area> elements of its image map: the cells whose center is in an
     * area are drawn with its link, the first area there winning.
     *
     * @param list<array{'rect'|'circle'|'poly'|'default', list<float>, string, 3?: string}> $areas  The shape of each area, its coordinates in CSS pixels, the OSC 8 sequence opening its link, and its href
     * @param array{float, float}|null                                                       $pixels The size of the image the coordinates are of, in CSS pixels (null = its own pixels)
     */
    public function setAreas(array $areas, ?array $pixels = null): void
    {
        $this->areas = $areas;
        $this->areaPixels = $pixels;
        $this->rendered = [];
    }

    public function setBox(?int $columns, ?int $rows, ?string $fit, ?array $position = null): void
    {
        $this->boxColumns = null !== $columns ? max(1, $columns) : null;
        $this->boxRows = null !== $rows ? max(1, $rows) : null;
        $this->fit = $fit;
        $this->position = $position ?? [0.5, 0.5];
    }

    /**
     * Turns the image as its EXIF data says or draws it as stored, like image-orientation, and scales it smoothly or
     * keeping its pixels sharp, like image-rendering: pixelated.
     */
    public function setRendering(bool $fromImage = true, bool $pixelated = false): void
    {
        if ($fromImage !== $this->fromImage || $pixelated !== $this->pixelated) {
            $this->fromImage = $fromImage;
            $this->pixelated = $pixelated;
            $this->rendered = [];
            $this->invalidate();
        }
    }

    /**
     * Whether the image could be decoded: when not, its alternative text is shown.
     */
    public function isDrawable(): bool
    {
        return false !== $this->decode();
    }

    /**
     * The columns the image is drawn in within some room, without drawing it, like render() does; null when it cannot be
     * decoded.
     *
     * @internal
     */
    public function drawnColumns(int $room): ?int
    {
        if (false === $this->decode()) {
            return null;
        }
        $room = max(1, $room);
        $naturalColumns = max(1, min(1000, $this->columns ?? (null !== $this->rows ? (int) min(1000, round($this->rows * 2 * $this->width / $this->height)) : (int) min(1000, ceil($this->width / self::CELL_PIXELS)))));
        if (null !== $this->boxColumns) {
            return max(1, min($room, $this->boxColumns));
        }
        if (null !== $this->boxRows) {
            return max(1, min($room, 1000, (int) round(max(1, min(1000, $this->boxRows)) * 2 * $this->width / $this->height)));
        }

        return min($room, $naturalColumns);
    }

    /**
     * @return string[]
     */
    public function render(\Symfony\Component\Tui\Render\RenderContext|RenderContext $context): array
    {
        $context = ContextBridge::computed($context);
        $image = $this->decode();
        $room = max(1, $context->getColumns());
        if (false === $image) {
            return '' === $this->alternative ? [] : [mb_strimwidth('['.$this->alternative.']', 0, $room)];
        }
        // Its own size: the attributes, else its pixels
        // At most 1000 cells each way, like the box: an image of extreme proportions is not drawn in millions of rows
        $naturalColumns = max(1, min(1000, $this->columns ?? (null !== $this->rows ? (int) min(1000, round($this->rows * 2 * $this->width / $this->height)) : (int) min(1000, ceil($this->width / self::CELL_PIXELS)))));
        $naturalRows = max(1, min(1000, $this->rows ?? (int) min(1000, round($naturalColumns * $this->height / $this->width / 2))));
        // The box the style sets: a width or a height alone keeps the proportions, both make object-fit matter
        $boxColumns = null !== $this->boxColumns ? max(1, min($room, $this->boxColumns)) : null;
        $boxRows = null !== $this->boxRows ? max(1, min(1000, $this->boxRows)) : null;
        if (null === $boxColumns && null === $boxRows) {
            $columns = min($room, $naturalColumns);
            $rows = null !== $this->rows && $columns === $naturalColumns ? $naturalRows : max(1, min(1000, (int) round($columns * $this->height / $this->width / 2)));

            return $this->rendered[$columns.'x'.$rows] ??= $this->draw($image, $columns, $rows);
        }
        if (null === $boxRows) {
            return $this->rendered[$boxColumns.'x'] ??= $this->draw($image, $boxColumns, max(1, min(1000, (int) round($boxColumns * $this->height / $this->width / 2))));
        }
        if (null === $boxColumns) {
            $columns = max(1, min($room, (int) round($boxRows * 2 * $this->width / $this->height)));

            return $this->rendered['x'.$boxRows] ??= $this->draw($image, $columns, $boxRows);
        }

        return $this->rendered[$boxColumns.'x'.$boxRows.':'.$this->fit.':'.implode(',', $this->position)] ??= $this->fit($image, $boxColumns, $boxRows, $naturalColumns, $naturalRows);
    }

    /**
     * The image in a box of a given width and height, like CSS object-fit, placed like object-position.
     *
     * @param int<1, max> $columns
     * @param int<1, max> $rows
     *
     * @return list<string>
     */
    private function fit(RasterImage $image, int $columns, int $rows, int $naturalColumns, int $naturalRows): array
    {
        // The size it is drawn at, keeping its proportions but for fill
        $scaled = function (bool $cover) use ($columns, $rows): array {
            $byWidth = [$columns, max(1, (int) min(1000, round($columns * $this->height / $this->width / 2)))];
            $byHeight = [max(1, (int) min(1000, round($rows * 2 * $this->width / $this->height))), $rows];

            return $byWidth[1] >= $rows === $cover ? $byWidth : $byHeight;
        };
        [$drawColumns, $drawRows] = match ($this->fit) {
            'contain' => $scaled(false),
            'cover' => $scaled(true),
            'none' => [$naturalColumns, $naturalRows],
            'scale-down' => $naturalColumns <= $columns && $naturalRows <= $rows ? [$naturalColumns, $naturalRows] : $scaled(false),
            default => [$columns, $rows],
        };
        $drawColumns = max(1, min(1000, $drawColumns));
        $drawRows = max(1, min(1000, $drawRows));
        // Cut what is past the box, or put space around what is smaller, where object-position says: the room left
        // (negative when cut) times its fraction
        // and the cells further from the left and the top it gives
        [$x, $y] = $this->position;
        $offsetX = $this->position[2] ?? 0;
        $offsetY = $this->position[3] ?? 0;
        // Never further than the box or the image: what is past them is blank anyway
        $top = max(-$rows, min($drawRows, (int) (($drawRows - $rows) * $y) - $offsetY));
        $left = max(-$columns, min($drawColumns, (int) (($drawColumns - $columns) * $x) - $offsetX));
        // Only the cells in the box: an image drawn larger is not turned into cells where it is cut
        $window = [max(0, $top), max(0, $left), $rows, $columns];
        $lines = $this->draw($image, $drawColumns, $drawRows, $window);
        $out = [];
        for ($row = 0; $row < $rows; ++$row) {
            $line = $lines[$row + $top - $window[0]] ?? '';
            if ($left >= 0) {
                $out[] = AnsiUtils::sliceToWidth($line, 0, $columns)."\x1b[0m";
            } else {
                // Past the box on the right when it is further than the room: cut there too
                $out[] = AnsiUtils::sliceToWidth(str_repeat(' ', -$left).$line, 0, $columns)."\x1b[0m";
            }
        }

        return $out;
    }

    /**
     * @param int<1, max>                    $columns
     * @param int<1, max>                    $rows
     * @param array{int, int, int, int}|null $window  The rows and columns drawn, see RasterImage::cells()
     *
     * @return list<string>
     */
    private function draw(RasterImage $image, int $columns, int $rows, ?array $window = null): array
    {
        [$width, $height] = $this->areaPixels ?? [(float) $this->width, (float) $this->height];
        $cursor = false;
        $lines = [];
        foreach ($image->cellRows($columns, $rows, $window) as $row => $cells) {
            $row += $window[0] ?? 0;
            $line = '';
            $open = null;
            foreach ($cells as $column => [$top, $bottom]) {
                $column += $window[1] ?? 0;
                // The area of the center of the cell, its link around the cells in it
                $area = [] !== $this->areas ? $this->areaAt(($column + 0.5) * $width / $columns, ($row + 0.5) * $height / $rows) : null;
                if ($area !== $open) {
                    $line .= (null !== $open ? "\x1b]8;;\x07" : '').(null !== $area ? $this->areas[$area][2] : '');
                    if (null !== $area && $area === $this->currentArea && !$cursor) {
                        $line .= TextMarkers::cursor();
                        $cursor = true;
                    }
                    $open = $area;
                }
                $cell = self::cell($top, $bottom);
                // The area with the focus reversed, its colors the other way around
                $line .= null !== $area && $area === $this->currentArea ? preg_replace('/(.)$/su', "\x1b[7m\$1\x1b[27m", $cell) : $cell;
            }
            $lines[] = $line.(null !== $open ? "\x1b]8;;\x07" : '')."\x1b[0m";
        }

        return $lines;
    }

    /**
     * The first area a point is in, by its index, like the hit testing of an image map.
     */
    private function areaAt(float $x, float $y): ?int
    {
        foreach ($this->areas as $index => [$shape, $coords]) {
            $in = match ($shape) {
                'rect' => \count($coords) >= 4 && $x >= min($coords[0], $coords[2]) && $x < max($coords[0], $coords[2]) && $y >= min($coords[1], $coords[3]) && $y < max($coords[1], $coords[3]),
                'circle' => \count($coords) >= 3 && ($x - $coords[0]) ** 2 + ($y - $coords[1]) ** 2 <= $coords[2] ** 2,
                'poly' => self::inPolygon($coords, $x, $y),
                default => true,
            };
            if ($in) {
                return $index;
            }
        }

        return null;
    }

    /**
     * Whether a point is in a polygon of points x1,y1,x2,y2..., by the even-odd rule.
     *
     * @param list<float> $coords
     */
    private static function inPolygon(array $coords, float $x, float $y): bool
    {
        $points = intdiv(\count($coords), 2);
        if ($points < 3) {
            return false;
        }
        $in = false;
        for ($i = 0, $j = $points - 1; $i < $points; $j = $i++) {
            [$xi, $yi, $xj, $yj] = [$coords[2 * $i], $coords[2 * $i + 1], $coords[2 * $j], $coords[2 * $j + 1]];
            if ($yi > $y !== $yj > $y && $x < ($xj - $xi) * ($y - $yi) / ($yj - $yi) + $xi) {
                $in = !$in;
            }
        }

        return $in;
    }

    /**
     * @param array{int, int, int}|null $top
     * @param array{int, int, int}|null $bottom
     */
    private static function cell(?array $top, ?array $bottom): string
    {
        return match (true) {
            null === $top && null === $bottom => "\x1b[0m ",
            null === $bottom => "\x1b[0m\x1b[38;2;{$top[0]};{$top[1]};{$top[2]}m▀",
            null === $top => "\x1b[0m\x1b[38;2;{$bottom[0]};{$bottom[1]};{$bottom[2]}m▄",
            default => "\x1b[38;2;{$top[0]};{$top[1]};{$top[2]};48;2;{$bottom[0]};{$bottom[1]};{$bottom[2]}m▀",
        };
    }

    private function decode(): RasterImage|false
    {
        // Read when first drawn, like the candidates of a srcset not shown yet
        $this->image ??= new RasterImage(\is_string($this->data) ? $this->data : (string) ($this->data)());
        $image = $this->image->withRendering($this->fromImage, $this->pixelated);
        if (!$image->isDrawable()) {
            return false;
        }
        $this->width = $image->getWidth();
        $this->height = $image->getHeight();

        return $image;
    }
}
