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
final class BackgroundColor
{
    public static function fromShorthand(string $value, \Closure $isColor): string|false
    {
        $parts = BackgroundParser::tokens($value);
        $color = 'transparent';
        $found = false;
        foreach ($parts as $part) {
            if (\count($parts) > 1 && preg_match('/^\\d+$/', $part)) {
                $found = true;
                continue;
            }
            if (self::isLayoutToken($part, \count($parts))) {
                $found = true;
            } elseif ($isColor($part)) {
                $color = $part;
                $found = true;
            } elseif (preg_match('/^(?:url|image-set|-webkit-image-set|(?:repeating-)?(?:linear|radial|conic)-gradient)\\(/i', $part)) {
                $found = true;
            } else {
                return false;
            }
        }

        return $found ? $color : false;
    }

    public static function isLayoutToken(string $part, int $count): bool
    {
        return (bool) preg_match('~^(?:none|repeat(?:-[xy])?|no-repeat|space|round|left|right|top|bottom|center|cover|contain|auto|scroll|fixed|local|(?:border|padding|content)-box|text|/|-?\\d*\\.?\\d+(?:%|[a-z]+)?)$~i', $part) && !(1 === $count && preg_match('/^\\d+$/', $part));
    }
}
