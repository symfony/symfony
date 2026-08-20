<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Tui\Transition;

use Symfony\Component\Tui\Ansi\AnsiUtils;
use Symfony\Component\Tui\Exception\InvalidArgumentException;
use Symfony\Component\Tui\Transition\Direction\RadialDirection;

/**
 * Reveal cells along a spiral path.
 *
 * @experimental
 *
 * @author Simon André <smn.andre@gmail.com>
 */
final class SpiralTransition extends AbstractTransition
{
    /**
     * Caches each spiral path by its block-grid dimensions (`{columns}x{rows}`).
     *
     * @var array<string, list<array{int, int}>> Block coordinates, in reveal order
     */
    private array $pathCache = [];

    /**
     * @param RadialDirection $direction Inward (towards center) or Outward (from center)
     * @param int             $size      Block thickness in cells
     */
    public function __construct(
        private readonly RadialDirection $direction = RadialDirection::Inward,
        private readonly int $size = 1,
    ) {
        if ($size <= 0) {
            throw new InvalidArgumentException(\sprintf('Size must be greater than 0, got %d.', $size));
        }
    }

    protected function doBlend(array $fromLines, array $toLines, int $width, int $height, float $progress): array
    {
        $cols = (int) ceil($width / $this->size);
        $rows = (int) ceil($height / $this->size);
        $totalBlocks = $cols * $rows;

        $cacheKey = "{$cols}x{$rows}";
        if (!isset($this->pathCache[$cacheKey])) {
            $this->pathCache[$cacheKey] = $this->generateSpiralPath($cols, $rows);
        }

        $path = $this->pathCache[$cacheKey];
        if (RadialDirection::Outward === $this->direction) {
            $path = array_reverse($path);
        }

        $threshold = (int) ($totalBlocks * $progress);
        $visibleBlocks = array_fill(0, $totalBlocks, false);
        for ($i = 0; $i < $threshold; ++$i) {
            $visibleBlocks[$path[$i][1] * $cols + $path[$i][0]] = true;
        }

        $result = [];
        for ($y = 0; $y < $height; ++$y) {
            $blockY = (int) ($y / $this->size);
            $line = '';
            for ($bx = 0; $bx < $cols; ++$bx) {
                $blockIdx = $blockY * $cols + $bx;
                $startX = $bx * $this->size;
                $w = min($this->size, $width - $startX);

                if ($visibleBlocks[$blockIdx] ?? false) {
                    $line .= AnsiUtils::sliceToWidth($toLines[$y] ?? '', $startX, $w);
                } else {
                    $line .= AnsiUtils::sliceToWidth($fromLines[$y] ?? '', $startX, $w);
                }
            }
            $result[] = $line;
        }

        return $result;
    }

    /**
     * @return list<array{int, int}>
     */
    private function generateSpiralPath(int $w, int $h): array
    {
        $path = [];
        $top = 0;
        $bottom = $h - 1;
        $left = 0;
        $right = $w - 1;

        while ($top <= $bottom && $left <= $right) {
            // Right
            for ($i = $left; $i <= $right; ++$i) {
                $path[] = [$i, $top];
            }
            ++$top;
            // Down
            for ($i = $top; $i <= $bottom; ++$i) {
                $path[] = [$right, $i];
            }
            --$right;
            if ($top <= $bottom) {
                // Left
                for ($i = $right; $i >= $left; --$i) {
                    $path[] = [$i, $bottom];
                }
                --$bottom;
            }
            if ($left <= $right) {
                // Up
                for ($i = $bottom; $i >= $top; --$i) {
                    $path[] = [$left, $i];
                }
                ++$left;
            }
        }

        return $path;
    }
}
