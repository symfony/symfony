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

/** @internal Shared lexical classification; device conversion belongs to its caller. */
final class OutlineParser
{
    public const STYLES = ['none', 'hidden', 'solid', 'double', 'dotted', 'dashed', 'groove', 'ridge', 'inset', 'outset', 'auto'];

    public static function components(string $value, \Closure $isWidth): array
    {
        $parts = ValueTokenizer::split(strtolower($value));

        return ['parts' => $parts, 'style' => array_values(array_intersect($parts, self::STYLES))[0] ?? null, 'width' => array_values(array_filter($parts, static fn (string $part): bool => !\in_array($part, self::STYLES, true) && $isWidth($part)))[0] ?? null];
    }
}
