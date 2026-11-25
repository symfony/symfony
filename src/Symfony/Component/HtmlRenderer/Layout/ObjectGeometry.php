<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HtmlRenderer\Layout;

/** @internal */
final class ObjectGeometry
{
    /** @return array{float, float} */
    public static function size(float $width, float $height, float $ratio, string $fit, float $naturalWidth, float $naturalHeight, ?\Closure $quantize = null): array
    {
        if ($width <= 0 || $height <= 0) {
            return [0.0, 0.0];
        }
        $byWidth = [$width, $width / $ratio];
        $byHeight = [$height * $ratio, $height];
        if (null !== $quantize) {
            $byWidth = $quantize($byWidth);
            $byHeight = $quantize($byHeight);
        }
        $contained = $byWidth[1] >= $height ? $byHeight : $byWidth;

        return match ($fit) {
            'contain' => $contained,
            'cover' => $byWidth[1] >= $height ? $byWidth : $byHeight,
            'none' => [$naturalWidth, $naturalHeight],
            'scale-down' => $naturalWidth <= $width && $naturalHeight <= $height ? [$naturalWidth, $naturalHeight] : $contained,
            default => [$width, $height],
        };
    }
}
