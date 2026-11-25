<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\TuiHtml\Css;

use Symfony\Component\HtmlRenderer\Css\ContainerQuery as NativeContainerQuery;
use Symfony\Component\HtmlRenderer\Css\MediaQuery as NativeMediaQuery;

/** @internal */
final class ContainerQuery extends NativeContainerQuery
{
    public static function forPixels(NativeContainerQuery $query, float $cellWidth, float $cellHeight): self
    {
        $condition = $query->condition?->mapValues(static function (string $name, string $operator, ?string $value) use ($cellWidth, $cellHeight): ?string {
            if (null !== $value && is_numeric($value) && \in_array($name, ['width', 'height', 'inline-size', 'block-size'], true)) {
                return ((float) $value * (\in_array($name, ['height', 'block-size'], true) ? $cellHeight : $cellWidth)).'px';
            }

            return $value;
        });

        return new self($query->name, $condition, $query->styles, $query->any, $query->scroll);
    }

    protected static function parseMediaQuery(string $query): NativeMediaQuery
    {
        return MediaQuery::parse($query);
    }
}
