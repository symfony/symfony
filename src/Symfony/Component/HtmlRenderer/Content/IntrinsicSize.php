<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HtmlRenderer\Content;

use Symfony\Component\HtmlRenderer\Exception\InvalidArgumentException;

/**
 * Natural CSS-pixel dimensions before the containing box allocates content space.
 *
 * @experimental
 */
final readonly class IntrinsicSize
{
    public function __construct(public float $minimumWidth, public float $preferredWidth, public float $height, public ?float $aspectRatio = null)
    {
        if (null !== $aspectRatio && (!is_finite($aspectRatio) || $aspectRatio <= 0)) {
            throw new InvalidArgumentException('An intrinsic aspect ratio must be finite and positive.');
        }
        if (!is_finite($minimumWidth) || !is_finite($preferredWidth) || !is_finite($height) || $minimumWidth < 0 || $preferredWidth < $minimumWidth || $height < 0) {
            throw new InvalidArgumentException('Intrinsic dimensions must be finite and non-negative, with minimum width no greater than preferred width.');
        }
    }
}
