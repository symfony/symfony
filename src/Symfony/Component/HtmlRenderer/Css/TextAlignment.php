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
final class TextAlignment
{
    public static function parent(array $parent): string
    {
        $rtl = 'rtl' === strtolower(trim($parent['direction'] ?? 'ltr'));

        return match ($value = strtolower(trim($parent['text-align'] ?? 'start'))) {
            'start', 'match-parent' => $rtl ? 'right' : 'left',
            'end' => $rtl ? 'left' : 'right',
            default => $value,
        };
    }
}
