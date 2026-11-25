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
final class PositionArea
{
    public static function parse(string $value, string $direction = 'ltr', string $writingMode = 'horizontal-tb'): ?array
    {
        $parts = ValueTokenizer::split(strtolower(trim($value)));
        // The logical keywords, in a horizontal writing mode from the left: x and inline are the row, y and block the
        // column; start and end alone are of the block axis first, then of the inline one, both when alone
        $logical = static function (string $part, ?string $axis) use ($direction, $writingMode) {
            $span = str_starts_with($part, 'span-') ? 'span-' : '';
            $name = preg_replace('/^(?:span-)?(?:self-)?/', '', $part);
            [$side, $axis] = match ($name) {
                'x-start', 'inline-start' => ['start', 'inline'],
                'x-end', 'inline-end' => ['end', 'inline'],
                'y-start', 'block-start' => ['start', 'block'],
                'y-end', 'block-end' => ['end', 'block'],
                'start', 'end' => [$name, $axis],
                default => [null, null],
            };
            if (null === $side || null === $axis) {
                return $part;
            }

            return $span.LogicalProperties::physical('inset-'.$axis.'-'.$side, $writingMode, $direction);
        };
        if (1 === \count($parts) && \in_array(preg_replace('/^(?:span-)?(?:self-)?/', '', $parts[0]), ['start', 'end'], true)) {
            $parts = [$logical($parts[0], 'block'), $logical($parts[0], 'inline')];
        } else {
            $parts = array_map(static fn (string $part, int $i) => $logical($part, 2 === \count($parts) ? 0 === $i ? 'block' : 'inline' : null), $parts, array_keys($parts));
        }
        $block = ['top', 'center', 'bottom', 'span-top', 'span-bottom', 'span-all'];
        $inline = ['left', 'center', 'right', 'span-left', 'span-right', 'span-all'];
        if (1 === \count($parts)) {
            return match (true) {
                \in_array($parts[0], ['top', 'bottom', 'span-top', 'span-bottom'], true) => [$parts[0], 'center'],
                \in_array($parts[0], ['left', 'right', 'span-left', 'span-right'], true) => ['center', $parts[0]],
                'center' === $parts[0] => ['center', 'center'],
                default => null,
            };
        }
        if (2 !== \count($parts)) {
            return null;
        }
        // Either order, like CSS: the block keyword is the one that is only a block keyword
        if (\in_array($parts[0], ['left', 'right', 'span-left', 'span-right'], true) || \in_array($parts[1], ['top', 'bottom', 'span-top', 'span-bottom'], true)) {
            $parts = [$parts[1], $parts[0]];
        }

        return \in_array($parts[0], $block, true) && \in_array($parts[1], $inline, true) ? [$parts[0], $parts[1]] : null;
    }
}
