<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HtmlRenderer\Css;

/** @internal */
final class CurrentColor
{
    public static function replace(string $value, ?string $color): ?string
    {
        if (!preg_match('/\\bcurrentcolor\\b/i', $value)) {
            return $value;
        }

        return null === $color ? null : (preg_replace('/\\bcurrentcolor\\b/i', $color, $value) ?? $value);
    }
}
