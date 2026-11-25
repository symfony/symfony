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
final class TextEmphasis
{
    public static function parse(string $value): array
    {
        $keyword = strtolower(trim($value));
        if (\in_array($keyword, ['initial', 'inherit', 'unset', 'revert', 'revert-layer'], true)) {
            return ['text-emphasis-style' => $keyword, 'text-emphasis-color' => $keyword];
        }
        $style = [];
        $color = 'currentcolor';
        foreach (ValueTokenizer::split($value) as $part) {
            if (preg_match('/^(?:none|filled|open|dot|circle|double-circle|triangle|sesame)$/i', $part) || preg_match('/^["\']/', $part)) {
                $style[] = $part;
            } else {
                $color = $part;
            }
        }

        return ['text-emphasis-style' => [] !== $style ? implode(' ', $style) : 'none', 'text-emphasis-color' => $color];
    }
}
