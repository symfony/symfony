<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HtmlRenderer\Font;

final class FontStyle
{
    public function __construct(public readonly string $family = 'sans-serif', public readonly float $size = 16.0, public readonly float $lineHeight = 19.2, public readonly int $weight = 400, public readonly string $direction = 'ltr', public readonly string $style = 'normal', public readonly float $letterSpacing = 0.0, public readonly float $wordSpacing = 0.0)
    {
        if (!is_finite($letterSpacing) || !is_finite($wordSpacing)) {
            throw new \InvalidArgumentException('Text spacing must be finite.');
        }
        if (!is_finite($size) || !is_finite($lineHeight) || $size < 0 || $lineHeight < 0) {
            throw new \InvalidArgumentException('Font size and line height must be finite and non-negative.');
        }
    }
}
