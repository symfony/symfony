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
final class RubyGeometry
{
    public static function place(float $baseWidth, float $annotationWidth, float $baseHeight, float $annotationHeight, bool $under = false, bool $start = false): array
    {
        $width = max($baseWidth, $annotationWidth);

        return ['width' => $width, 'height' => $baseHeight + $annotationHeight, 'base' => [$start ? 0.0 : ($width - $baseWidth) / 2, $under ? 0.0 : $annotationHeight], 'annotation' => [$start ? 0.0 : ($width - $annotationWidth) / 2, $under ? $baseHeight : 0.0]];
    }
}
