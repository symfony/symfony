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

use Symfony\Component\HtmlRenderer\Css\ColorValue;

/** @internal */
final class BackgroundCompositor
{
    /**
     * Samples one isolated element background. Image pixels are supplied by its device backend.
     * Text coverage is shared by all masked layers of this element and supplied at its glyph positions.
     *
     * @return array{array{float, float, float}, float}
     */
    public static function sample(array $operations, float $x, float $y, \Closure $image, bool $text = false, ?\Closure $color = null): array
    {
        if (\count($operations) > 128) {
            throw new \LengthException('A background group exceeds its drawing operation budget.');
        }
        $backdrop = [[0.0, 0.0, 0.0], 0.0];
        $clips = [[-\INF, -\INF, \INF, \INF]];
        foreach ($operations as $index => $operation) {
            $clip = $clips[array_key_last($clips)];
            if ('push-clip' === $operation['type']) {
                $clips[] = [($operation['clip-x'] ?? true) ? max($clip[0], $operation['x']) : $clip[0], ($operation['clip-y'] ?? true) ? max($clip[1], $operation['y']) : $clip[1], ($operation['clip-x'] ?? true) ? min($clip[2], $operation['x'] + $operation['width']) : $clip[2], ($operation['clip-y'] ?? true) ? min($clip[3], $operation['y'] + $operation['height']) : $clip[3]];
                continue;
            }
            if ('pop-clip' === $operation['type']) {
                if (\count($clips) < 2) {
                    throw new \InvalidArgumentException('A background group cannot restore outer clipping.');
                }
                array_pop($clips);
                continue;
            }
            if ($x < $clip[0] || $y < $clip[1] || $x >= $clip[2] || $y >= $clip[3] || $x < $operation['x'] || $y < $operation['y'] || $x >= $operation['x'] + $operation['width'] || $y >= $operation['y'] + $operation['height'] || (!$text && isset($operation['text-mask']))) {
                continue;
            }
            if ('rectangle' === $operation['type']) {
                $source = null !== $color ? $color($operation['color']) : ColorValue::parse($operation['color']);
            } else {
                $point = [$x - $operation['x'], $y - $operation['y']];
                if (isset($operation['pattern'])) {
                    $point = BackgroundGeometry::sample($operation['pattern'], ...$point);
                    if (null === $point) {
                        continue;
                    }
                }
                $source = match ($operation['type']) {
                    'gradient' => GradientGeometry::sample($operation['gradient'], $point[0], $point[1]),
                    'background-image' => $image($operation, $point, $index),
                    default => throw new \InvalidArgumentException('Unsupported background group drawing operation.'),
                };
            }
            if (!\is_array($source) || $source[1] <= 0) {
                continue;
            }
            $mode = BlendMode::fromKeyword($operation['blend-mode'] ?? 'normal') ?? BlendMode::Normal;
            $backdrop = self::over($backdrop, $source, $mode);
        }
        if (\count($clips) > 1) {
            throw new \InvalidArgumentException('Background group clipping must be balanced.');
        }

        return $backdrop;
    }

    /** Source-over compositing with blending, https://www.w3.org/TR/compositing-1/#blending */
    private static function over(array $backdrop, array $source, BlendMode $mode): array
    {
        [$cb, $ab] = $backdrop;
        [$cs, $as] = $source;
        $mixed = $mode->blendFloat($cb, $cs);
        $alpha = $as + $ab * (1 - $as);
        $out = [];
        for ($i = 0; $i < 3; ++$i) {
            $out[] = $alpha > 0 ? ($as * ((1 - $ab) * $cs[$i] + $ab * $mixed[$i]) + (1 - $as) * $ab * $cb[$i]) / $alpha : 0.0;
        }

        return [$out, $alpha];
    }
}
