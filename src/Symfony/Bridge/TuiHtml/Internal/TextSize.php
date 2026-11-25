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

/** @internal */
final class TextSize
{
    public static function scale(float $pixels): int
    {
        return max(1, min(4, (int) round($pixels / 16)));
    }
}
