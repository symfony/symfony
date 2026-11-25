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

/** @internal */
final class BackgroundGeometry
{
    public static function imageSize(float $naturalWidth, float $naturalHeight, float $areaWidth, float $areaHeight, ?float $width, ?float $height, ?string $fit = null, ?float $ratio = null): array
    {
        if ($naturalWidth <= 0 || $naturalHeight <= 0) {
            return [0.0, 0.0];
        }
        if (null !== $fit) {
            $scales = [$areaWidth / $naturalWidth, $areaHeight / $naturalHeight];
            $scale = 'cover' === $fit ? max($scales) : min($scales);

            return [$naturalWidth * $scale, $naturalHeight * $scale];
        }
        $ratio ??= $naturalHeight / $naturalWidth;

        return match (true) {
            null === $width && null === $height => [$naturalWidth, $naturalHeight],
            null === $height => [$width, $width * $ratio],
            null === $width => [$height / $ratio, $height],
            default => [$width, $height],
        };
    }

    public static function axis(float $origin, float $length, float $size, array $position, string $repeat): array
    {
        if ($size <= 0) {
            return ['start' => $origin, 'size' => 0.0, 'step' => 1.0, 'count' => 0];
        }
        if ('round' === $repeat && $length > 0) {
            $size = $length / max(1.0, round($length / $size));
        }
        $room = $length - $size;
        $offset = $room * $position['percent'] / 100 + $position['offset'];
        $start = $origin + ($position['fromEnd'] ? $room - $offset : $offset);
        $count = null;
        $step = $size;
        $period = null;
        if ('space' === $repeat) {
            $count = floor(max(0.0, $length) / $size);
            if ($count >= 2) {
                $start = $origin;
                $period = $length;
                $step += ($length - $count * $size) / ($count - 1);
                $count = $count <= \PHP_INT_MAX ? (int) $count : $count;
            } else {
                $count = 1;
            }
        } elseif ('no-repeat' === $repeat) {
            $count = 1;
        }

        return ['start' => $start, 'size' => $size, 'step' => $step, 'count' => $count] + (null === $period ? [] : ['period' => $period]);
    }

    public static function sampleAxis(array $axis, float $coordinate): ?float
    {
        if ($axis['size'] <= 0) {
            return null;
        }
        $offset = $coordinate - $axis['start'];
        // Space groups tile beyond their positioning area, including its edge tiles.
        // https://www.w3.org/TR/css-backgrounds-3/#the-background-repeat
        if (isset($axis['period'])) {
            $offset = fmod(fmod($offset, $axis['period']) + $axis['period'], $axis['period']);
        }
        $index = floor($offset / $axis['step']);
        if (null !== $axis['count'] && ($index < 0 || $index >= $axis['count'])) {
            return null;
        }
        $local = $offset - $index * $axis['step'];

        return $local < 0 || $local >= $axis['size'] ? null : $local;
    }

    public static function sample(array $pattern, float $x, float $y): ?array
    {
        $u = self::sampleAxis($pattern['x'], $x);
        $v = self::sampleAxis($pattern['y'], $y);

        return null === $u || null === $v ? null : [$u, $v, $pattern['x']['size'], $pattern['y']['size']];
    }
}
