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
final class TextDecoration
{
    public const STYLES = ['solid', 'double', 'wavy', 'dotted', 'dashed'];

    public static function parse(string $value, ?\Closure $color = null, ?\Closure $length = null, bool $strict = true): ?array
    {
        $parts = ValueTokenizer::split($value);
        $lines = [];
        $result = [];
        foreach ($parts as $part) {
            $lower = strtolower($part);
            if (\in_array($lower, ['none', 'underline', 'line-through', 'overline', 'blink'], true)) {
                if ($strict && (\in_array($lower, $lines, true) || 'none' === $lower && $lines || \in_array('none', $lines, true))) {
                    return null;
                }
                $lines[] = $lower;
            } elseif (\in_array($lower, self::STYLES, true)) {
                if ($strict && isset($result['style'])) {
                    return null;
                }
                $result['style'] = $lower;
            } elseif (\in_array($lower, ['auto', 'from-font'], true) || preg_match('/^\d*\.?\d+%$/D', $part) || (null !== $length ? $length($part) : self::thickness($part))) {
                if ($strict && isset($result['thickness'])) {
                    return null;
                }
                $result['thickness'] = $part;
            } elseif ('currentcolor' === $lower || (null !== $color ? $color($part) : \is_array(ColorValue::parse($part)))) {
                if ($strict && isset($result['color'])) {
                    return null;
                }
                $result['color'] = $part;
            } else {
                return null;
            }
        }
        if (!$parts) {
            return null;
        }
        if ($lines) {
            $result['line'] = implode(' ', $lines);
        }

        return $result;
    }

    public static function thickness(string $value): bool
    {
        return '0' === $value || (bool) preg_match('/^(?:\d*\.?\d+)(?:px|em|rem|ex|ch|lh|rlh|pt|pc|in|cm|mm|q)$/iD', $value) || Length::isMath($value);
    }
}
