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
final class CssWideKeywords
{
    public static function affected(array $properties): array
    {
        return array_values(array_filter($properties, static fn (string $property) => !str_starts_with($property, '--') && !\in_array($property, ['all', 'direction', 'unicode-bidi'], true)));
    }

    public static function resolve(string $keyword, string $property, bool $author, array $userAgent, array $inherited): string
    {
        if (\in_array($keyword, ['revert', 'revert-layer'], true) && $author && isset($userAgent[$property])) {
            return $userAgent[$property];
        }
        if (\in_array($keyword, ['revert', 'revert-layer'], true) && 'appearance' === $property) {
            return 'auto';
        }
        if (\in_array($keyword, ['initial', 'inherit'], true)) {
            return $keyword;
        }

        return \in_array($property, $inherited, true) ? 'inherit' : 'initial';
    }
}
