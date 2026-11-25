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
final class GradientParser
{
    public static function parse(string $value, \Closure $length, ?\Closure $validColor = null, ?\Closure $linear = null): ?array
    {
        $validColor ??= static fn (string $color) => ColorValue::parse($color);
        if (\strlen($value) > 65536 || !preg_match('/^(repeating-)?(linear|radial|conic)-gradient\((.*)\)$/is', trim($value), $m)) {
            return null;
        }
        $repeating = '' !== $m[1];
        $kind = strtolower($m[2]);
        $arguments = array_map('trim', Selector::splitTopLevel($m[3]));
        // The color space the colors are interpolated in, like "in oklch" before or after the direction: sRGB by default
        $method = null;
        if (preg_match('/^(.*?)\s*\b(in\s+[a-z0-9-]+(?:\s+[a-z]+\s+hue)?)\s*(.*)$/is', $arguments[0] ?? '', $in) && !str_contains($in[1].$in[3], '(')) {
            if (null === $method = ColorValue::interpolationMethod($in[2])) {
                return null;
            }
            $arguments[0] = trim($in[1].' '.$in[3]);
            if ('' === $arguments[0]) {
                array_shift($arguments);
            }
        }
        $angle = 180.0;
        $from = 0.0;
        $first = strtolower($arguments[0] ?? '');
        if ('linear' === $kind && null !== $a = self::angle($first)) {
            $angle = $a;
            array_shift($arguments);
        } elseif ('linear' === $kind && str_starts_with($first, 'to ')) {
            $sides = preg_split('/\s+/', substr($first, 3)) ?: [];
            $x = (\in_array('right', $sides, true) ? 1 : 0) - (\in_array('left', $sides, true) ? 1 : 0);
            $y = (\in_array('bottom', $sides, true) ? 1 : 0) - (\in_array('top', $sides, true) ? 1 : 0);
            if (0 === $x && 0 === $y) {
                return null;
            }
            $angle = rad2deg(atan2($x, -$y));
            array_shift($arguments);
        } elseif ('linear' !== $kind && !$validColor(ValueTokenizer::split($first)[0] ?? '')) {
            // The shape and the size of a radial one, where a conic one starts, then where they are centered
            $tokens = ValueTokenizer::split($first);
            $at = array_search('at', $tokens, true);
            if (false !== $at) {
                if (null === $center = self::position(\array_slice($tokens, $at + 1), $length)) {
                    return null;
                }
                $tokens = \array_slice($tokens, 0, $at);
            }
            if ('conic' === $kind) {
                if ($tokens && (2 !== \count($tokens) || 'from' !== $tokens[0] || null === $from = self::angle($tokens[1]))) {
                    return null;
                }
            } else {
                $lengths = [];
                foreach ($tokens as $token) {
                    if ('circle' === $token || 'ellipse' === $token) {
                        $circle = 'circle' === $token;
                    } elseif (\in_array($token, ['closest-side', 'closest-corner', 'farthest-side', 'farthest-corner'], true)) {
                        $extent = $token;
                    } elseif (null !== $radius = self::backgroundLength($token, $length, 1 === \count($lengths))) {
                        $lengths[] = $radius;
                    } else {
                        return null;
                    }
                }
                if ($lengths) {
                    // One length is the radius of a circle, two the radii of an ellipse
                    if (\count($lengths) > 2 || isset($extent)) {
                        return null;
                    }
                    $circle ??= 1 === \count($lengths);
                    $extent = [$lengths[0], $circle ? null : $lengths[1] ?? null];
                    if (!$circle && null === $extent[1]) {
                        return null;
                    }
                }
            }
            array_shift($arguments);
        }
        if (\count($arguments) < 1 || \count($arguments) > 32) {
            return null;
        }
        // Each stop: a color, then none, one or two positions: shares of the line, angles around a conic one, or
        // lengths along a linear or radial one
        $raw = [];
        foreach ($arguments as $argument) {
            $parts = ValueTokenizer::split($argument);
            $name = array_shift($parts) ?? '';
            if (!$validColor($name) || \count($parts) > 2) {
                return null;
            }
            $positions = [];
            foreach ($parts as $part) {
                if ('0' === $part) {
                    $positions[] = [0.0, 0.0];
                } elseif (preg_match('/^(-?\d*\.?\d+)%$/', $part, $p)) {
                    $positions[] = [(float) $p[1] / 100, 0.0];
                } elseif ('conic' === $kind && null !== $degrees = self::angle($part)) {
                    $positions[] = [$degrees / 360, 0.0];
                } elseif ('conic' !== $kind && null !== $offsetLength = $length($part, false)) {
                    $positions[] = [0.0, (float) $offsetLength];
                } elseif ('conic' !== $kind && null !== $linear && null !== $affine = $linear($part, false)) {
                    // A share and a length, like the calc(100% - 2rem) of the fade under the menu of MDN
                    $positions[] = [(float) $affine[0] / 100, (float) $affine[1]];
                } else {
                    return null;
                }
            }
            foreach ($positions ?: [[null, 0.0]] as [$share, $offset]) {
                if (null !== $share && !is_finite($share) || !is_finite($offset)) {
                    return null;
                }
                $raw[] = [$name, $share, $offset];
            }
        }

        return ['kind' => $kind, 'angle' => $angle, 'repeating' => $repeating, 'from' => $from, 'stops' => $raw, 'circle' => $circle ?? false, 'extent' => $extent ?? 'farthest-corner', 'center' => $center ?? null, 'space' => $method[0] ?? 'srgb', 'hue' => $method[1] ?? 'shorter'];
    }

    public static function position(array $tokens, \Closure $length): ?array
    {
        $lower = array_map(strtolower(...), $tokens);
        $count = \count($tokens);
        if (0 === $count || $count > 4) {
            return null;
        }
        $vertical = static fn (string $keyword) => 'top' === $keyword || 'bottom' === $keyword;
        $horizontal = static fn (string $keyword) => 'left' === $keyword || 'right' === $keyword;
        if (1 === $count) {
            // One value: the other axis is centered; top and bottom are vertical
            return $vertical($lower[0]) ? [['percent' => 50.0, 'offset' => 0.0, 'fromEnd' => false], self::axis($tokens, true, $length) ?? throw new \LogicException('A keyword is valid.')] : (null !== ($x = self::axis($tokens, false, $length)) ? [$x, ['percent' => 50.0, 'offset' => 0.0, 'fromEnd' => false]] : null);
        }
        if (2 === $count) {
            // Two keywords in any order, else horizontal then vertical
            if ($vertical($lower[0]) || $horizontal($lower[1])) {
                if (!\in_array($lower[0], ['top', 'bottom', 'center'], true) || !\in_array($lower[1], ['left', 'right', 'center'], true)) {
                    return null;
                }
                [$tokens, $lower] = [[$tokens[1], $tokens[0]], [$lower[1], $lower[0]]];
            }
            $x = $vertical($lower[0]) ? null : self::axis([$tokens[0]], false, $length);
            $y = $horizontal($lower[1]) ? null : self::axis([$tokens[1]], true, $length);

            return null !== $x && null !== $y ? [$x, $y] : null;
        }
        // Three or four values: edges, each followed by an offset or not; center stands alone
        $axes = [];
        for ($i = 0; $i < $count; ++$i) {
            $keyword = $lower[$i];
            if (!\in_array($keyword, ['left', 'right', 'top', 'bottom', 'center'], true)) {
                return null;
            }
            $offset = isset($tokens[$i + 1]) && !\in_array($lower[$i + 1], ['left', 'right', 'top', 'bottom', 'center'], true) ? $tokens[++$i] : null;
            if ('center' === $keyword && null !== $offset) {
                return null;
            }
            $axis = 'center' === $keyword ? null : ($vertical($keyword) ? 1 : 0);
            $axes[] = [$axis, null === $offset ? [$tokens[$i]] : [$keyword, $offset]];
        }
        if (2 !== \count($axes)) {
            return null;
        }
        // A center takes the axis the other edge leaves
        $first = $axes[0][0] ?? (1 === $axes[1][0] ? 0 : 1);
        $second = $axes[1][0] ?? 1 - $first;
        if ($first === $second) {
            return null;
        }
        $lengths = [];
        foreach ([[$first, $axes[0][1]], [$second, $axes[1][1]]] as [$axis, $parts]) {
            $lengths[$axis] = self::axis(['center'] === array_map(strtolower(...), $parts) ? ['center'] : $parts, 1 === $axis, $length);
            if (null === $lengths[$axis]) {
                return null;
            }
        }

        return [$lengths[0], $lengths[1]];
    }

    public static function angle(string $value): ?float
    {
        if (!preg_match('/^(-?\d*\.?\d+)(deg|turn|rad|grad)$/i', trim($value), $a)) {
            return null;
        }

        $angle = match (strtolower($a[2])) {
            'turn' => (float) $a[1] * 360,
            'rad' => rad2deg((float) $a[1]),
            'grad' => (float) $a[1] * 0.9,
            default => (float) $a[1],
        };

        return is_finite($angle) ? $angle : null;
    }

    public static function axis(array $tokens, bool $vertical, \Closure $length): ?array
    {
        [$start, $end] = $vertical ? ['top', 'bottom'] : ['left', 'right'];
        $keyword = strtolower($tokens[0] ?? '');
        if (1 === \count($tokens)) {
            return match ($keyword) {
                $start => ['percent' => 0.0, 'offset' => 0.0, 'fromEnd' => false],
                'center' => ['percent' => 50.0, 'offset' => 0.0, 'fromEnd' => false],
                $end => ['percent' => 100.0, 'offset' => 0.0, 'fromEnd' => false],
                default => self::backgroundLength($tokens[0], $length, $vertical),
            };
        }
        if (2 === \count($tokens) && \in_array($keyword, [$start, $end], true) && null !== $offset = self::backgroundLength($tokens[1], $length, $vertical)) {
            return ['percent' => $offset['percent'], 'offset' => $offset['offset'], 'fromEnd' => $keyword === $end];
        }

        return null;
    }

    public static function backgroundLength(string $token, \Closure $length, bool $vertical): ?array
    {
        if (preg_match('/^(-?\d+(?:\.\d+)?|-?\.\d+)%$/', trim($token), $m)) {
            return is_finite((float) $m[1]) ? ['percent' => (float) $m[1], 'offset' => 0.0, 'fromEnd' => false] : null;
        }
        $resolved = $length($token, $vertical);

        return null === $resolved || !is_finite($resolved) ? null : ['percent' => 0.0, 'offset' => (float) $resolved, 'fromEnd' => false];
    }
}
