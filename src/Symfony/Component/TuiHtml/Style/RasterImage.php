<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\TuiHtml\Style;

use Symfony\Component\Tui\Exception\RenderException;

/**
 * A raster image (PNG, JPEG, GIF, WebP...), decoded with the GD extension, resampled to cells of two pixels each.
 *
 * A cell shows two pixels, one above the other, like a half block: terminal cells are about twice as tall as wide,
 * so the pixels are square.
 *
 * @experimental
 */
final class RasterImage
{
    /**
     * The largest image decoded, in pixels: what is larger cannot be drawn.
     */
    public const MAX_PIXELS = 4194304;
    private \GdImage|false|null $image = null;
    private int $width = 0;
    private int $height = 0;
    /** @var array<string, list<list<array{?array{int, int, int}, ?array{int, int, int}}>>> */
    private array $cells = [];
    /** @var array<string, \GdImage> The image resampled to the large sizes asked for by cell, see cellAt() */
    private array $scaled = [];
    private float $resolution = 1.0;
    /** Whether the orientation of its EXIF data is applied, like image-orientation: from-image */
    private bool $fromImage = true;
    /** Whether it is scaled without smoothing, its pixels kept sharp, like image-rendering: pixelated */
    private bool $pixelated = false;
    /** Whether it is a shape whose thin lines are kept when it is made smaller, like an icon drawn by a mask */
    private bool $strokes = false;
    /** @var array<string, self> */
    private array $variants = [];

    /**
     * @param string $data The encoded image
     */
    public function __construct(public readonly string $data)
    {
    }

    /**
     * The same image at a resolution, its pixels per CSS pixel, like the 2x of image-set(): its size is divided by it.
     */
    public function withResolution(float $resolution): self
    {
        $image = clone $this;
        $image->resolution = max(0.01, $resolution);
        // Its own variants, at its resolution
        $image->variants = [];

        return $image;
    }

    /**
     * The same image, a shape whose lines thinner than a pixel once made smaller are drawn still, rather than nothing,
     * like an icon of strokes drawn by mask-image.
     */
    public function withStrokesKept(): self
    {
        $image = clone $this;
        $image->strokes = true;
        $image->cells = [];
        $image->scaled = [];

        return $image;
    }

    /**
     * The same image oriented by its EXIF data or not, like image-orientation, and scaled smoothly or keeping its
     * pixels sharp, like image-rendering: pixelated; the variants are kept, each turned into cells once.
     */
    public function withRendering(bool $fromImage = true, bool $pixelated = false): self
    {
        if ($fromImage === $this->fromImage && $pixelated === $this->pixelated) {
            return $this;
        }
        $key = ($fromImage ? 'o' : '-').($pixelated ? 'p' : '-');
        if (!isset($this->variants[$key])) {
            $image = clone $this;
            $image->fromImage = $fromImage;
            $image->pixelated = $pixelated;
            $image->variants = [];
            $image->cells = [];
            // Oriented otherwise: decoded again
            if ($fromImage !== $this->fromImage) {
                $image->image = null;
            }
            $this->variants[$key] = $image;
        }

        return $this->variants[$key];
    }

    public function getResolution(): float
    {
        return $this->resolution;
    }

    /**
     * The size of the image, in pixels, as its header says, turned like it is drawn, without decoding it; null when it
     * is not an image GD reads, or one too large to draw.
     *
     * @return array{int, int}|null
     */
    public function peekSize(): ?array
    {
        if (null !== $this->image) {
            return false !== $this->image ? [$this->width, $this->height] : null;
        }
        if (!\function_exists('imagecreatefromstring') || '' === $this->data) {
            return null;
        }
        $size = @getimagesizefromstring($this->data);
        if (false === $size || $size[0] < 1 || $size[1] < 1 || $size[0] * $size[1] > self::MAX_PIXELS || !\in_array($size[2], [\IMAGETYPE_GIF, \IMAGETYPE_JPEG, \IMAGETYPE_PNG, \IMAGETYPE_WEBP, \IMAGETYPE_BMP, \IMAGETYPE_AVIF], true)) {
            return null;
        }

        return $this->fromImage && self::exifOrientation($this->data) >= 5 ? [$size[1], $size[0]] : [$size[0], $size[1]];
    }

    /**
     * Whether the image could be decoded.
     */
    public function isDrawable(): bool
    {
        return false !== $this->decode();
    }

    /**
     * The width of the image, in pixels; 0 when it cannot be drawn.
     */
    public function getWidth(): int
    {
        $this->decode();

        return $this->width;
    }

    /**
     * The height of the image, in pixels; 0 when it cannot be drawn.
     */
    public function getHeight(): int
    {
        $this->decode();

        return $this->height;
    }

    /**
     * The image resampled to a number of columns and rows, with its transparency: the top and bottom pixel of each
     * cell, by row then column, null for a mostly transparent one.
     *
     * Only the cells of a window of them when it is given, from a row and a column, so that an image drawn larger than
     * the box it shows in is not turned into cells where it is cut.
     *
     * @param int<1, max>                    $columns
     * @param int<1, max>                    $rows
     * @param array{int, int, int, int}|null $window  The first row and column, the number of rows and columns
     *
     * @return list<list<array{?array{int, int, int}, ?array{int, int, int}}>>
     */
    public function cells(int $columns, int $rows, ?array $window = null): array
    {
        [$fromRow, $fromColumn, $height, $width] = $window ?? [0, 0, $rows, $columns];
        $fromRow = max(0, min($rows, $fromRow));
        $fromColumn = max(0, min($columns, $fromColumn));
        $toRow = min($rows, $fromRow + max(0, $height));
        $toColumn = min($columns, $fromColumn + max(0, $width));
        if (isset($this->cells[$key = $columns.'x'.$rows.(null !== $window ? ':'.$fromRow.','.$fromColumn.','.$toRow.','.$toColumn : '')])) {
            return $this->cells[$key];
        }
        if (false === $image = $this->decode()) {
            return [];
        }
        // Sizes in use are kept, a few of them: a box resized keeps asking for new ones
        if (\count($this->cells) >= 8) {
            array_shift($this->cells);
        }
        $scaled = $this->resample($image, $columns, $rows);
        $cells = [];
        for ($row = $fromRow; $row < $toRow; ++$row) {
            $line = [];
            for ($column = $fromColumn; $column < $toColumn; ++$column) {
                $line[] = [self::pixel($scaled, $column, 2 * $row), self::pixel($scaled, $column, 2 * $row + 1)];
            }
            $cells[] = $line;
        }

        return $this->cells[$key] = $cells;
    }

    /**
     * The rows of cells of the image resampled, like cells(), one after the other: for a large size, like an image a
     * thousand columns wide, the rows read as they are drawn, not all of its cells kept at once.
     *
     * @param int<1, max>                    $columns
     * @param int<1, max>                    $rows
     * @param array{int, int, int, int}|null $window  The first row and column, the number of rows and columns
     *
     * @return iterable<int, list<array{?array{int, int, int}, ?array{int, int, int}}>>
     */
    public function cellRows(int $columns, int $rows, ?array $window = null): iterable
    {
        [$fromRow, $fromColumn, $height, $width] = $window ?? [0, 0, $rows, $columns];
        if (min($rows, max(0, $height)) * min($columns, max(0, $width)) <= 65536) {
            yield from $this->cells($columns, $rows, $window);

            return;
        }
        $fromRow = max(0, min($rows, $fromRow));
        $fromColumn = max(0, min($columns, $fromColumn));
        $toRow = min($rows, $fromRow + max(0, $height));
        $toColumn = min($columns, $fromColumn + max(0, $width));
        if (false === $image = $this->decode()) {
            return;
        }
        $scaled = $this->resample($image, $columns, $rows);
        for ($row = $fromRow; $row < $toRow; ++$row) {
            $line = [];
            for ($column = $fromColumn; $column < $toColumn; ++$column) {
                $line[] = [self::pixel($scaled, $column, 2 * $row), self::pixel($scaled, $column, 2 * $row + 1)];
            }
            yield $row - $fromRow => $line;
        }
    }

    /**
     * The top and bottom pixel of a cell of the image resampled to a number of columns and rows, like cells(), null
     * for a mostly transparent one: the resampled image is kept, its pixels read as they are asked for, not turned
     * into cells all at once, like the border of a box thousands of rows tall painted by an image.
     *
     * @param int<1, max> $columns
     * @param int<1, max> $rows
     *
     * @return array{?array{int, int, int}, ?array{int, int, int}}
     */
    public function cellAt(int $columns, int $rows, int $column, int $row): array
    {
        if ($column < 0 || $row < 0 || $column >= $columns || $row >= $rows) {
            return [null, null];
        }
        // Small enough: its cells, all of them, like the other sizes in use
        if ($columns * $rows <= 65536) {
            return $this->cells($columns, $rows)[$row][$column] ?? [null, null];
        }
        $key = $columns.'x'.$rows;
        if (!isset($this->scaled[$key])) {
            if (false === $image = $this->decode()) {
                return [null, null];
            }
            if (\count($this->scaled) >= 2) {
                array_shift($this->scaled);
            }
            $scaled = $this->resample($image, $columns, $rows);
            $this->scaled[$key] = $scaled;
        }

        return [self::pixel($this->scaled[$key], $column, 2 * $row), self::pixel($this->scaled[$key], $column, 2 * $row + 1)];
    }

    /**
     * @return array{int, int, int}|null The color, null when mostly transparent
     */
    /**
     * The columns of each row of cells where the image is more opaque than a threshold, from 0 to 1, at a size in
     * cells: the first and the last one, null for a row where it is not; like shape-image-threshold.
     *
     * @param int<1, max> $columns
     * @param int<1, max> $rows
     *
     * @return list<array{int, int}|null>
     */
    public function opaqueSpans(int $columns, int $rows, float $threshold = 0.0): array
    {
        if (false === $image = $this->decode()) {
            return array_fill(0, max(1, $rows), null);
        }
        $columns = max(1, min(1000, $columns));
        $rows = max(1, min(1000, $rows));
        $scaled = imagecreatetruecolor($columns, $rows * 2);
        if (false === $scaled) {
            throw new RenderException(\sprintf('Unable to allocate a %dx%d image.', $columns, $rows * 2));
        }
        imagealphablending($scaled, false);
        imagesavealpha($scaled, true);
        imagefill($scaled, 0, 0, imagecolorallocatealpha($scaled, 0, 0, 0, 127) ?: 0);
        imagecopyresampled($scaled, $image, 0, 0, 0, 0, $columns, $rows * 2, $this->width, $this->height);
        // More opaque than the threshold: alpha goes from 0, opaque, to 127, transparent
        $limit = 127 * (1 - max(0.0, min(1.0, $threshold)));
        $spans = [];
        for ($row = 0; $row < $rows; ++$row) {
            $span = null;
            for ($column = 0; $column < $columns; ++$column) {
                foreach ([2 * $row, 2 * $row + 1] as $y) {
                    $color = imagecolorat($scaled, $column, $y);
                    if (false !== $color && ($color >> 24 & 0x7F) < $limit) {
                        $span = [$span[0] ?? $column, $column];
                    }
                }
            }
            $spans[] = $span;
        }

        return $spans;
    }

    /**
     * The image resampled to a number of columns and twice as many rows of pixels, two a cell.
     */
    private function resample(\GdImage $image, int $columns, int $rows): \GdImage
    {
        $scaled = imagecreatetruecolor($columns, $rows * 2);
        if (false === $scaled) {
            throw new RenderException(\sprintf('Unable to allocate a %dx%d image.', $columns, $rows * 2));
        }
        imagealphablending($scaled, false);
        imagesavealpha($scaled, true);
        imagefill($scaled, 0, 0, imagecolorallocatealpha($scaled, 0, 0, 0, 127) ?: 0);
        $this->pixelated ? imagecopyresized($scaled, $image, 0, 0, 0, 0, $columns, $rows * 2, $this->width, $this->height) : imagecopyresampled($scaled, $image, 0, 0, 0, 0, $columns, $rows * 2, $this->width, $this->height);
        // Lines thinner than a pixel fade out when made smaller: when nothing is left, each pixel as opaque as the part of
        // a line through it, its faded opacity times the pixels it stands for across, like the strokes of a chevron
        $factor = min($this->width / max(1, $columns), $this->height / max(1, 2 * $rows));
        // Not in a cell or two, where a line would be a speck, nothing of its shape left
        if (!$this->strokes || $factor <= 1.0 || $columns < 2 || $rows < 2 || $columns * $rows > 65536) {
            return $scaled;
        }
        $pixels = [];
        for ($y = 0; $y < 2 * $rows; ++$y) {
            for ($x = 0; $x < $columns; ++$x) {
                $color = imagecolorat($scaled, $x, $y);
                if (false === $color) {
                    continue;
                }
                if (($color >> 24 & 0x7F) <= 63) {
                    return $scaled;
                }
                $pixels[] = [$x, $y, $color];
            }
        }
        foreach ($pixels as [$x, $y, $color]) {
            $opacity = min(1.0, (127 - ($color >> 24 & 0x7F)) / 127 * $factor);
            imagesetpixel($scaled, $x, $y, $color & 0xFFFFFF | 127 - (int) round($opacity * 127) << 24);
        }

        return $scaled;
    }

    private static function pixel(\GdImage $image, int $x, int $y): ?array
    {
        $color = imagecolorat($image, $x, $y);
        if (false === $color || ($color >> 24 & 0x7F) > 63) {
            return null;
        }

        return [$color >> 16 & 0xFF, $color >> 8 & 0xFF, $color & 0xFF];
    }

    private function decode(): \GdImage|false
    {
        if (null !== $this->image) {
            return $this->image;
        }
        $this->image = false;
        if (!\function_exists('imagecreatefromstring') || '' === $this->data) {
            return false;
        }
        // The size first: a small file can decode to a huge image
        $size = @getimagesizefromstring($this->data);
        if (false === $size || $size[0] < 1 || $size[1] < 1 || $size[0] * $size[1] > self::MAX_PIXELS) {
            return false;
        }
        $image = @imagecreatefromstring($this->data);
        if (false === $image) {
            return false;
        }
        imagepalettetotruecolor($image);
        // Turned and flipped as its EXIF data says, like a browser: a photo taken sideways stands up
        if ($this->fromImage && 1 < $orientation = self::exifOrientation($this->data)) {
            $image = self::orient($image, $orientation) ?: $image;
        }
        $this->width = imagesx($image);
        $this->height = imagesy($image);

        return $this->image = $image;
    }

    /**
     * The Orientation tag of the EXIF data of a JPEG, 1 to 8; 1, upright, without one.
     */
    private static function exifOrientation(string $data): int
    {
        if (!str_starts_with($data, "\xff\xd8")) {
            return 1;
        }
        $length = \strlen($data);
        // The segments before the image data, until the APP1 one of the EXIF data
        for ($at = 2; $at + 4 <= $length && "\xff" === $data[$at];) {
            $marker = \ord($data[$at + 1]);
            if (0xDA === $marker || 0xD9 === $marker) {
                break;
            }
            $size = unpack('n', $data, $at + 2)[1];
            if (0xE1 === $marker && 'Exif' === substr($data, $at + 4, 4)) {
                $tiff = $at + 10;
                $order = substr($data, $tiff, 2);
                if ('II' !== $order && 'MM' !== $order) {
                    return 1;
                }
                $short = 'II' === $order ? 'v' : 'n';
                $long = 'II' === $order ? 'V' : 'N';
                if ($tiff + 8 > $length) {
                    return 1;
                }
                $ifd = $tiff + unpack($long, $data, $tiff + 4)[1];
                if ($ifd + 2 > $length) {
                    return 1;
                }
                $count = unpack($short, $data, $ifd)[1];
                for ($i = 0; $i < $count && $ifd + 2 + 12 * ($i + 1) <= $length; ++$i) {
                    $entry = $ifd + 2 + 12 * $i;
                    if (0x112 === unpack($short, $data, $entry)[1]) {
                        $value = unpack($short, $data, $entry + 8)[1];

                        return $value >= 1 && $value <= 8 ? $value : 1;
                    }
                }

                return 1;
            }
            $at += 2 + $size;
        }

        return 1;
    }

    /**
     * The image turned and flipped to stand up as an EXIF orientation says.
     */
    private static function orient(\GdImage $image, int $orientation): \GdImage|false
    {
        // Turned clockwise by 90 degrees for 5 and 6, counterclockwise for 7 and 8, half a turn for 3 and 4; then
        // flipped sideways for 2, 4, 5 and 7: 4, half a turn flipped, is upside down
        $turned = match ($orientation) {
            3, 4 => imagerotate($image, 180, 0),
            5, 6 => imagerotate($image, 270, 0),
            7, 8 => imagerotate($image, 90, 0),
            default => $image,
        };
        if (false === $turned) {
            return false;
        }
        if (\in_array($orientation, [2, 4, 5, 7], true)) {
            imageflip($turned, \IMG_FLIP_HORIZONTAL);
        }

        return $turned;
    }
}
