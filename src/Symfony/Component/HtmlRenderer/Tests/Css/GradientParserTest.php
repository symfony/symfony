<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HtmlRenderer\Tests\Css;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HtmlRenderer\Css\GradientParser;
use Symfony\Component\HtmlRenderer\Paint\GradientGeometry;

class GradientParserTest extends TestCase
{
    public function testLinearStopsResolveFractionalGeometryAndPortableColors()
    {
        $gradient = GradientParser::parse('linear-gradient(to right, red 10.5px, blue)', self::length(...));
        $model = GradientGeometry::resolveModel($gradient, 100.5, 20.25);
        self::assertSame([1.0, 0.0], $model['direction']);
        self::assertSame(100.5, $model['length']);
        self::assertEqualsWithDelta(10.5 / 100.5, $model['stops'][0][1], 0.000001);
        self::assertSame([[1.0, 0.0, 0.0], 1.0], $model['stops'][0][0]);
        self::assertIsString(json_encode($model, \JSON_THROW_ON_ERROR));
    }

    public function testRadialAndConicPreludeUseIndependentPositions()
    {
        $radial = GradientParser::parse('radial-gradient(ellipse closest-side at right 10px bottom 5px, red, transparent)', self::length(...));
        $model = GradientGeometry::resolveModel($radial, 100, 60);
        self::assertSame([90.0, 55.0], $model['center']);
        self::assertSame([10.0, 5.0], $model['radii']);
        $conic = GradientParser::parse('repeating-conic-gradient(from 90deg at 25% 75%, red 0deg, blue 90deg)', self::length(...));
        self::assertSame(90.0, $conic['from']);
        self::assertTrue($conic['repeating']);
        self::assertSame(0.25, $conic['stops'][1][1]);
    }

    public function testGradientSamplingPreservesTransparentAlpha()
    {
        $gradient = GradientParser::parse('linear-gradient(to right, transparent, red)', self::length(...));
        $model = GradientGeometry::resolveModel($gradient, 100, 20);
        self::assertSame([[1.0, 0.0, 0.0], 0.5], GradientGeometry::sample($model, 50, 10));
    }

    private static function length(string $value, bool $vertical = false): ?float
    {
        return preg_match('/^([+-]?[\d.]+)(?:px)?$/', $value, $m) ? (float) $m[1] : null;
    }
}
