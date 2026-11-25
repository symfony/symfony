<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HtmlRenderer\Tests\Layout;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HtmlRenderer\Layout\ObjectGeometry;

final class ObjectGeometryTest extends TestCase
{
    public function testAllFitsPreserveFractionalGeometryAndIntrinsicNoneSize()
    {
        foreach (['fill' => [10.5, 7.25], 'contain' => [3.625, 7.25], 'cover' => [10.5, 21.0], 'none' => [4.0, 8.0], 'scale-down' => [3.625, 7.25]] as $fit => $expected) {
            self::assertSame($expected, ObjectGeometry::size(10.5, 7.25, 0.5, $fit, 4.0, 8.0));
        }
        self::assertSame([4.0, 8.0], ObjectGeometry::size(20.0, 20.0, 0.5, 'scale-down', 4.0, 8.0));
    }

    public function testDeviceQuantizationRetainsTheOriginalFitChoice()
    {
        $quantize = static fn (array $size): array => array_map(static fn (float $value): float => max(1.0, min(1000.0, round($value))), $size);
        for ($i = 1; $i <= 100; ++$i) {
            $width = (float) ($i % 17 + 1);
            $height = (float) ($i % 9 + 1);
            $ratio = ($i % 7 + 1) / ($i % 11 + 1);
            $byWidth = [$width, max(1.0, min(1000.0, round($width / $ratio)))];
            $byHeight = [max(1.0, min(1000.0, round($height * $ratio))), $height];
            foreach (['contain' => false, 'cover' => true] as $fit => $cover) {
                self::assertSame(($byWidth[1] >= $height) === $cover ? $byWidth : $byHeight, ObjectGeometry::size($width, $height, $ratio, $fit, 10, 5, $quantize));
            }
        }
    }
}
