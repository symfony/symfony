<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HtmlRenderer\Paint;

use Symfony\Component\HtmlRenderer\Css\Length;
use Symfony\Component\HtmlRenderer\Css\Selector;
use Symfony\Component\HtmlRenderer\Css\ValueTokenizer;
use Symfony\Component\HtmlRenderer\Geometry\Rect;
use Symfony\Component\HtmlRenderer\Vector\Geometry;

/**
 * @internal
 */
final class Transform
{
    private const IDENTITY = [1.0, 0.0, 0.0, 1.0, 0.0, 0.0];

    /**
     * @param \Closure(string, bool): ?float $length
     *
     * @return array{float, float, float, float, float, float}|null
     */
    public static function resolve(array $style, Rect $bounds, \Closure $length): ?array
    {
        $source = trim($style['transform'] ?? 'none');
        $individual = [];
        foreach (['translate', 'rotate', 'scale'] as $property) {
            $value = trim($style[$property] ?? 'none');
            if ('none' !== $value && '' !== $value) {
                $arguments = ValueTokenizer::split($value);
                $name = 'translate' === $property && 3 === \count($arguments) ? 'translate3d' : $property;
                $individual[] = $name.'('.implode(',', $arguments).')';
            }
        }
        if ($individual) {
            $source = implode(' ', $individual).('none' === $source || '' === $source ? '' : ' '.$source);
        }
        if ('none' === $source || '' === $source) {
            return null;
        }
        if (\strlen($source) > 65536) {
            return null;
        }
        $matrix = self::IDENTITY;
        $position = 0;
        $count = 0;
        while ($position < \strlen($source)) {
            if (++$count > 128 || !preg_match('/\G\s*([a-z][a-z0-9]*)\(/i', $source, $match, 0, $position)) {
                return null;
            }
            $start = $position += \strlen($match[0]);
            $depth = 1;
            while ($position < \strlen($source) && $depth > 0) {
                $character = $source[$position++];
                if ('(' === $character) {
                    ++$depth;
                } elseif (')' === $character) {
                    --$depth;
                }
                if ($depth > 64) {
                    return null;
                }
            }
            if (0 !== $depth) {
                return null;
            }
            $arguments = Selector::splitTopLevel(substr($source, $start, $position - $start - 1));
            if (1 === \count($arguments)) {
                $arguments = ValueTokenizer::split($arguments[0]);
            }
            $step = self::function(strtolower($match[1]), $arguments, $length);
            if (null === $step) {
                return null;
            }
            $matrix = Geometry::multiply($matrix, $step);
            if (!array_all($matrix, static fn (float $value): bool => is_finite($value))) {
                return null;
            }
            while ($position < \strlen($source) && ctype_space($source[$position])) {
                ++$position;
            }
        }
        $origin = ValueTokenizer::split($style['transform-origin'] ?? '50% 50%');
        if (1 === \count($origin)) {
            $origin = \in_array($origin[0], ['top', 'bottom'], true) ? ['50%', $origin[0]] : [$origin[0], '50%'];
        }
        if (\in_array($origin[0] ?? '', ['top', 'bottom'], true) || \in_array($origin[1] ?? '', ['left', 'right'], true)) {
            [$origin[0], $origin[1]] = [$origin[1], $origin[0]];
        }
        $x = self::origin($origin[0] ?? '50%', false, $length);
        $y = self::origin($origin[1] ?? '50%', true, $length);
        if (null === $x || null === $y) {
            return null;
        }
        $x += $bounds->x;
        $y += $bounds->y;

        $matrix = Geometry::multiply(Geometry::multiply([1.0, 0.0, 0.0, 1.0, $x, $y], $matrix), [1.0, 0.0, 0.0, 1.0, -$x, -$y]);

        return array_all($matrix, static fn (float $value): bool => is_finite($value)) ? $matrix : null;
    }

    /**
     * @return array{float, float}|null
     */
    public static function inversePoint(array $matrix, float $x, float $y): ?array
    {
        $determinant = $matrix[0] * $matrix[3] - $matrix[1] * $matrix[2];
        if (0.0 === $determinant || !is_finite($determinant)) {
            return null;
        }
        $x -= $matrix[4];
        $y -= $matrix[5];
        $point = [($matrix[3] * $x - $matrix[2] * $y) / $determinant, ($matrix[0] * $y - $matrix[1] * $x) / $determinant];

        return array_all($point, static fn (float $value): bool => is_finite($value)) ? $point : null;
    }

    public static function moved(array $matrix, float $x, float $y): array
    {
        $matrix[4] += $x - $matrix[0] * $x - $matrix[2] * $y;
        $matrix[5] += $y - $matrix[1] * $x - $matrix[3] * $y;

        return $matrix;
    }

    private static function function(string $name, array $arguments, \Closure $length): ?array
    {
        $count = \count($arguments);
        if (\in_array($name, ['translate', 'translatex', 'translatey', 'translate3d'], true)) {
            if ('translate3d' === $name ? 3 !== $count : ($count < 1 || $count > ('translate' === $name ? 2 : 1))) {
                return null;
            }
            if ('translate3d' === $name && null === $length($arguments[2], false)) {
                return null;
            }
            $x = 'translatey' === $name ? 0.0 : $length($arguments[0], false);
            $y = 'translatey' === $name ? $length($arguments[0], true) : (isset($arguments[1]) ? $length($arguments[1], true) : 0.0);

            return null === $x || null === $y ? null : Geometry::transform(\sprintf('translate(%.17h,%.17h)', $x, $y));
        }
        if (\in_array($name, ['scale', 'scalex', 'scaley'], true)) {
            if ($count < 1 || $count > ('scale' === $name ? 2 : 1)) {
                return null;
            }
            $x = 'scaley' === $name ? 1.0 : self::number($arguments[0]);
            $y = 'scaley' === $name ? self::number($arguments[0]) : (isset($arguments[1]) ? self::number($arguments[1]) : ('scalex' === $name ? 1.0 : $x));

            return null === $x || null === $y ? null : Geometry::transform(\sprintf('scale(%.17h,%.17h)', $x, $y));
        }
        if (\in_array($name, ['rotate', 'rotatez', 'skewx', 'skewy'], true) && 1 === $count) {
            $angle = self::angle($arguments[0]);

            return null === $angle ? null : Geometry::transform(\sprintf('%s(%.17h)', 'rotatez' === $name ? 'rotate' : $name, $angle));
        }
        if ('skew' === $name && $count >= 1 && $count <= 2) {
            $x = self::angle($arguments[0]);
            $y = isset($arguments[1]) ? self::angle($arguments[1]) : 0.0;

            return null === $x || null === $y ? null : [1.0, tan(deg2rad($y)), tan(deg2rad($x)), 1.0, 0.0, 0.0];
        }
        if ('matrix' === $name && 6 === $count) {
            $numbers = array_map(self::number(...), $arguments);
            if (!\in_array(null, $numbers, true)) {
                $numbers[4] = $length(\sprintf('%.17hpx', $numbers[4]), false);
                $numbers[5] = $length(\sprintf('%.17hpx', $numbers[5]), true);
            }

            return \in_array(null, $numbers, true) ? null : Geometry::transform('matrix('.implode(',', $numbers).')');
        }

        return null;
    }

    private static function number(string $value): ?float
    {
        $number = Length::evaluate($value, static fn (string $token): ?float => is_numeric($token) ? (float) $token : (preg_match('/^([+-]?(?:\d*\.)?\d+)%$/', $token, $match) ? (float) $match[1] / 100 : null));

        return null !== $number && is_finite($number) ? $number : null;
    }

    private static function angle(string $value): ?float
    {
        return Length::evaluate($value, static fn (string $token): ?float => is_numeric($token) && 0.0 === (float) $token ? 0.0 : null, 'deg');
    }

    private static function origin(string $value, bool $vertical, \Closure $length): ?float
    {
        $value = match ($value) {
            'left', 'top' => '0%',
            'center' => '50%',
            'right', 'bottom' => '100%',
            default => $value,
        };

        return $length($value, $vertical);
    }
}
