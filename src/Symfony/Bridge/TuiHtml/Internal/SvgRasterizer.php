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

use Symfony\Bridge\TuiHtml\Style\VectorImage;

/** @internal */
final class SvgRasterizer
{
    /** @return array{string, float}|null */
    public static function rasterize(string $data, int $scale): ?array
    {
        if (null === ($vector = VectorImage::fromString($data)) || null === ($png = $vector->toPng((int) ceil($vector->width * $scale), (int) ceil($vector->height * $scale), [0, 0, 0])) || false === $size = @getimagesizefromstring($png)) {
            return null;
        }

        return [$png, $size[0] / $vector->width];
    }
}
