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
final class ColorScheme
{
    public static function valid(string $value): bool
    {
        return 1 === preg_match('/^(?:normal|(?:only\s+)?(?:light|dark)(?:\s+(?:light|dark))?(?:\s+only)?)$/i', trim($value));
    }

    public static function forced(string $value): ?string
    {
        $schemes = array_values(array_intersect(preg_split('/\s+/', strtolower(trim($value))) ?: [], ['light', 'dark']));

        return 1 === \count(array_unique($schemes)) ? $schemes[0] : null;
    }
}
