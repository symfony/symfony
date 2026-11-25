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
final class WhiteSpace
{
    public static function resolve(array $computed): string
    {
        // The longhands: white-space-collapse and text-wrap-mode
        if (!isset($computed['white-space']) && isset($computed['white-space-collapse'])) {
            $wrap = !\in_array('nowrap', ValueTokenizer::split(strtolower($computed['text-wrap-mode'] ?? $computed['text-wrap'] ?? '')), true);

            return match (strtolower(trim($computed['white-space-collapse']))) {
                'preserve', 'break-spaces', 'preserve-spaces' => $wrap ? 'pre-wrap' : 'pre',
                'preserve-breaks' => $wrap ? 'pre-line' : 'pre',
                default => $wrap ? 'normal' : 'nowrap',
            };
        }
        // text-wrap: nowrap, or its longhand, keeps a block on one line like white-space: nowrap
        if ((!isset($computed['white-space']) || 'normal' === $computed['white-space']) && \in_array('nowrap', ValueTokenizer::split(strtolower($computed['text-wrap-mode'] ?? $computed['text-wrap'] ?? '')), true)) {
            return 'nowrap';
        }

        return self::parse($computed['white-space'] ?? 'normal') ?? 'normal';
    }

    public static function parse(string $value): ?string
    {
        $keywords = preg_split('/\s+/', strtolower(trim($value))) ?: [];
        if (1 === \count($keywords) && \in_array($keywords[0], ['normal', 'nowrap', 'pre', 'pre-wrap', 'pre-line'], true)) {
            return $keywords[0];
        }
        if (['break-spaces'] === $keywords) {
            return 'pre-wrap';
        }
        $collapse = array_values(array_intersect($keywords, ['collapse', 'preserve', 'preserve-breaks', 'preserve-spaces', 'break-spaces']));
        $mode = array_values(array_intersect($keywords, ['wrap', 'nowrap']));
        if (!$keywords || \count($collapse) > 1 || \count($mode) > 1 || \count($collapse) + \count($mode) !== \count($keywords)) {
            return null;
        }
        $wrap = 'nowrap' !== ($mode[0] ?? 'wrap');

        return match ($collapse[0] ?? 'collapse') {
            'preserve', 'break-spaces', 'preserve-spaces' => $wrap ? 'pre-wrap' : 'pre',
            'preserve-breaks' => $wrap ? 'pre-line' : 'pre',
            default => $wrap ? 'normal' : 'nowrap',
        };
    }

    public static function components(string $value): ?array
    {
        if (null === self::parse($value)) {
            return null;
        }
        $parts = ValueTokenizer::split(strtolower(trim($value)));
        if (1 === \count($parts)) {
            $legacy = match ($parts[0]) {
                'normal' => ['collapse', 'wrap'],
                'nowrap' => ['collapse', 'nowrap'],
                'pre' => ['preserve', 'nowrap'],
                'pre-wrap' => ['preserve', 'wrap'],
                'pre-line' => ['preserve-breaks', 'wrap'],
                default => null,
            };
            if (null !== $legacy) {
                return $legacy;
            }
        }
        $collapse = array_values(array_intersect($parts, ['collapse', 'preserve', 'preserve-breaks', 'preserve-spaces', 'break-spaces']));
        $wrap = array_values(array_intersect($parts, ['wrap', 'nowrap']));

        return [$collapse[0] ?? 'collapse', $wrap[0] ?? 'wrap'];
    }
}
