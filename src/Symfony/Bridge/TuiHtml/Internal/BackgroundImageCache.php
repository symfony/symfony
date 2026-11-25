<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\TuiHtml\Internal;

use Symfony\Bridge\TuiHtml\Style\RasterImage;

/** @internal */
final class BackgroundImageCache
{
    private array $entries = [];
    private int $bytes = 0;
    private int $pixels = 0;
    private int $cells = 0;

    public function __construct(private readonly int $maxEntries = 32, private readonly int $maxBytes = 16777216, private readonly int $maxPixels = 4194304, private readonly int $maxCells = 65536)
    {
        if (min($maxEntries, $maxBytes, $maxPixels, $maxCells) < 1) {
            throw new \InvalidArgumentException('Background image cache budgets must be positive.');
        }
    }

    public function get(string $data, string $mime, bool $fromImage, bool $pixelated, int $columns, int $rows): ?RasterImage
    {
        $key = hash('xxh128', $data).':'.$mime.':'.(int) $fromImage.':'.(int) $pixelated.':'.$columns.':'.$rows;
        if (isset($this->entries[$key])) {
            return $this->entries[$key]['image'];
        }
        if ('image/svg+xml' === $mime) {
            $data = SvgRasterizer::rasterize($data, 4)[0] ?? '';
        }
        $image = '' === $data ? null : (new RasterImage($data))->withRendering($fromImage, $pixelated);
        $size = $image?->peekSize();
        if (null === $size) {
            $image = null;
        }
        $bytes = null === $image ? 0 : \strlen($data);
        $cells = null === $image ? 0 : $columns * $rows;
        // Large cellAt() requests retain a two-pixel-per-cell GD resampling instead of PHP cell arrays.
        $pixels = null === $image ? 0 : $size[0] * $size[1] + ($cells > 65536 ? 2 * $cells : 0);
        if ($bytes > $this->maxBytes || $pixels > $this->maxPixels || $cells > $this->maxCells) {
            return $image;
        }
        while ($this->entries && (\count($this->entries) >= $this->maxEntries || $bytes > $this->maxBytes - $this->bytes || $pixels > $this->maxPixels - $this->pixels || $cells > $this->maxCells - $this->cells)) {
            $removed = array_shift($this->entries);
            $this->bytes -= $removed['bytes'];
            $this->pixels -= $removed['pixels'];
            $this->cells -= $removed['cells'];
        }
        $this->entries[$key] = ['image' => $image, 'bytes' => $bytes, 'pixels' => $pixels, 'cells' => $cells];
        $this->bytes += $bytes;
        $this->pixels += $pixels;
        $this->cells += $cells;

        return $image;
    }
}
