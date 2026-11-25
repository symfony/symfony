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
final class ImageSource
{
    public static function border(string $value): ?string
    {
        foreach (ValueTokenizer::split($value) as $part) {
            if ('none' === strtolower($part) || null !== self::url($part) || preg_match('/^(?:(?:-webkit-)?image-set|(?:repeating-)?(?:linear|radial|conic)-gradient)\(/i', $part)) {
                return $part;
            }
        }

        return null;
    }

    public static function imageSet(string $value, float $dppx = 1.0, ?array $types = null): ?array
    {
        if (!preg_match('/^(?:-webkit-)?image-set\((.*)\)$/is', trim($value), $m)) {
            return null;
        }
        $types ??= ['image/png', 'image/jpeg', 'image/gif', 'image/webp', 'image/bmp', 'image/avif', 'image/svg+xml'];
        $options = [];
        foreach (Selector::splitTopLevel($m[1]) as $option) {
            $parts = ValueTokenizer::split(trim($option));
            $image = array_shift($parts) ?? '';
            // A string is a url()
            if (preg_match('/^(["\'])(.*)\1$/s', $image, $string)) {
                $image = 'url("'.$string[2].'")';
            }
            $resolution = 1.0;
            foreach ($parts as $part) {
                if (preg_match('/^type\(\s*(["\'])(.*?)\1\s*\)$/is', $part, $type)) {
                    if (!\in_array(strtolower($type[2]), $types, true)) {
                        continue 2;
                    }
                } elseif (null === $resolution = MediaQuery::resolution($part)) {
                    continue 2;
                }
            }
            $options[] = [$resolution, $image];
        }
        if (!$options) {
            return null;
        }
        usort($options, static fn (array $a, array $b) => $a[0] <=> $b[0]);
        foreach ($options as [$resolution, $image]) {
            if ($resolution >= $dppx - 1.0E-9) {
                return [$image, $resolution];
            }
        }

        return [$options[\count($options) - 1][1], $options[\count($options) - 1][0]];
    }

    public static function url(string $value): ?string
    {
        if (!preg_match('/^url\(\s*(?:"([^"]*)"|\'([^\']*)\'|([^\s"\'()]*))\s*\)$/is', trim($value), $m)) {
            return null;
        }

        return $m[1].($m[2] ?? '').($m[3] ?? '');
    }
}
