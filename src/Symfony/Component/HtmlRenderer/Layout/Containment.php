<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HtmlRenderer\Layout;

/** @internal */
final class Containment
{
    /** @return array{bool, bool} */
    public static function axes(array $style): array
    {
        if (!isset($style['container-type']) && !isset($style['container']) && !isset($style['contain'])) {
            return [false, false];
        }
        $type = $style['container-type'] ?? (str_contains($style['container'] ?? '', '/') ? explode('/', $style['container'], 2)[1] : 'normal');
        $types = preg_split('/\s+/', strtolower(trim($type)), -1, \PREG_SPLIT_NO_EMPTY) ?: [];
        $contain = preg_split('/\s+/', strtolower(trim($style['contain'] ?? 'none')), -1, \PREG_SPLIT_NO_EMPTY) ?: [];
        $size = \in_array('size', $types, true) || (bool) array_intersect(['size', 'strict'], $contain);

        return [$size || \in_array('inline-size', $types, true) || \in_array('inline-size', $contain, true), $size];
    }

    public static function layout(array $style): bool
    {
        if (!isset($style['container-type']) && !isset($style['container']) && !isset($style['contain'])) {
            return false;
        }
        $type = $style['container-type'] ?? (str_contains($style['container'] ?? '', '/') ? explode('/', $style['container'], 2)[1] : 'normal');

        return (bool) preg_match('/(?:^|\s)(?:size|inline-size)(?:\s|$)/', strtolower($type)) || isset($style['contain']) && (bool) preg_match('/(?:^|\s)(?:layout|paint|content|strict)(?:\s|$)/', strtolower($style['contain']));
    }

    public static function fixed(array $style): bool
    {
        foreach (['transform', 'translate', 'rotate', 'scale', 'filter', 'backdrop-filter', '-webkit-backdrop-filter', 'perspective'] as $property) {
            if (isset($style[$property]) && !\in_array(strtolower(trim($style[$property])), ['none', ''], true)) {
                return true;
            }
        }
        $willChange = isset($style['will-change']) ? array_map('trim', explode(',', strtolower($style['will-change']))) : [];

        return self::layout($style) || [] !== array_intersect(['transform', 'translate', 'rotate', 'scale', 'filter', 'backdrop-filter', 'perspective'], $willChange);
    }

    public static function intrinsicSize(array $declarations): array
    {
        $sizes = [null, null];
        if (isset($declarations['contain-intrinsic-size'])) {
            $parts = array_values(array_filter(\Symfony\Component\HtmlRenderer\Css\ValueTokenizer::split(trim($declarations['contain-intrinsic-size'])), static fn (string $part) => 'auto' !== strtolower($part)));
            $sizes = [$parts[0] ?? null, $parts[1] ?? $parts[0] ?? null];
        }
        foreach ([0 => ['contain-intrinsic-width', 'contain-intrinsic-inline-size'], 1 => ['contain-intrinsic-height', 'contain-intrinsic-block-size']] as $axis => $properties) {
            foreach ($properties as $property) {
                if (isset($declarations[$property])) {
                    $parts = array_values(array_filter(\Symfony\Component\HtmlRenderer\Css\ValueTokenizer::split(trim($declarations[$property])), static fn (string $part) => 'auto' !== strtolower($part)));
                    $sizes[$axis] = $parts[0] ?? null;
                }
            }
        }

        return array_map(static fn (?string $size) => null === $size || 'none' === strtolower($size) ? null : $size, $sizes);
    }
}
