<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HtmlRenderer\Tests\Paint;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HtmlRenderer\Font\MonospaceTextMeasurer;
use Symfony\Component\HtmlRenderer\HtmlRenderer;
use Symfony\Component\HtmlRenderer\Layout\Box;
use Symfony\Component\HtmlRenderer\Layout\LayoutEngine;
use Symfony\Component\HtmlRenderer\Paint\Painter;
use Symfony\Component\HtmlRenderer\RenderingContext;

final class GradientPaintingTest extends TestCase
{
    public function testDisplayListContainsResolvedGeometryAndPortableColorStops()
    {
        $box = new Box(null, 'block', ['width' => '100.5px', 'height' => '20.25px', 'background-image' => 'linear-gradient(to right, red calc(50% - 2px), blue)']);
        $fragment = (new LayoutEngine(new MonospaceTextMeasurer()))->layout($box, 200, 100);
        $operations = (new Painter())->paint($fragment)->getOperations();
        self::assertCount(1, $operations);
        self::assertSame('gradient', $operations[0]['type']);
        self::assertSame(100.5, $operations[0]['width']);
        $model = $operations[0]['gradient'];
        self::assertSame([1.0, 0.0], $model['direction']);
        self::assertEqualsWithDelta(.5 - 2 / 100.5, $model['stops'][0][1], .000001);
        self::assertSame([[1.0, 0.0, 0.0], 1.0], $model['stops'][0][0]);
        self::assertIsString(json_encode($operations, \JSON_THROW_ON_ERROR));
    }

    public function testBackgroundLayersPaintBackToFrontAndResolveCurrentColor()
    {
        $box = new Box(null, 'block', ['width' => '40px', 'height' => '20px', 'color' => '#123456', 'background-color' => 'white', 'background-image' => 'linear-gradient(currentColor, transparent), radial-gradient(red, blue)', 'border-width' => '1px']);
        $fragment = (new LayoutEngine(new MonospaceTextMeasurer()))->layout($box, 100, 100);
        $operations = (new Painter())->paint($fragment)->getOperations();
        self::assertSame(['rectangle', 'gradient', 'gradient', 'border'], array_column($operations, 'type'));
        self::assertSame('radial', $operations[1]['gradient']['kind']);
        self::assertSame('linear', $operations[2]['gradient']['kind']);
        self::assertSame([[18 / 255, 52 / 255, 86 / 255], 1.0], $operations[2]['gradient']['stops'][0][0]);
    }

    public function testGradientAliasesResolveToPortableColorsIncludingNestedFunctions()
    {
        $box = new Box(null, 'block', ['width' => '40px', 'height' => '20px', 'background-image' => 'linear-gradient(brand,208,color-mix(in srgb,brand,208))']);
        $fragment = (new LayoutEngine(new MonospaceTextMeasurer(), null, ['brand' => '#ff0000', '208' => '#0000ff']))->layout($box, 100, 100);
        $operations = (new Painter())->paint($fragment)->getOperations();
        $stops = $operations[0]['gradient']['stops'];
        self::assertSame([[1.0, 0.0, 0.0], 1.0], $stops[0][0]);
        self::assertSame([[0.0, 0.0, 1.0], 1.0], $stops[1][0]);
        self::assertSame([[.5, 0.0, .5], 1.0], $stops[2][0]);
        self::assertStringNotContainsString('brand', json_encode($operations, \JSON_THROW_ON_ERROR));
    }

    public function testStandardAndSystemGradientColorsTakePriorityOverAliases()
    {
        $box = new Box(null, 'block', ['width' => '40px', 'height' => '20px', 'color-scheme' => 'dark', 'background-image' => 'linear-gradient(red,Canvas)']);
        $fragment = (new LayoutEngine(new MonospaceTextMeasurer(), null, ['red' => 'blue', 'canvas' => 'white']))->layout($box, 100, 100);
        $stops = (new Painter())->paint($fragment)->getOperations()[0]['gradient']['stops'];
        self::assertSame([[1.0, 0.0, 0.0], 1.0], $stops[0][0]);
        self::assertSame([[0.0, 0.0, 0.0], 1.0], $stops[1][0]);
    }

    public function testContextPreferenceResolvesDefaultDualAndForcedGradientSchemes()
    {
        foreach ([['dark', '', 0.0], ['dark', 'light', 1.0], ['dark', 'light dark', 0.0], ['light', 'light dark', 1.0]] as [$preferred,$scheme,$channel]) {
            $html = '<style>div{width:40px;height:20px;background-image:linear-gradient(light-dark(white,black),Canvas);'.('' !== $scheme ? 'color-scheme:'.$scheme : '').'}</style><div></div>';
            $result = (new HtmlRenderer())->render($html, new RenderingContext(100, 100, null, null, null, null, ['prefers-color-scheme' => $preferred]));
            $operations = array_values(array_filter($result->getDisplayList()->getOperations(), static fn (array $op): bool => 'gradient' === $op['type']));
            self::assertSame([[$channel, $channel, $channel], 1.0], $operations[0]['gradient']['stops'][0][0]);
            self::assertSame([[$channel, $channel, $channel], 1.0], $operations[0]['gradient']['stops'][1][0]);
        }
    }

    public function testAliasesAndBorderGradientShareUsedSchemeResolution()
    {
        $html = '<style>div{width:40px;height:20px;border:1px solid;border-image-source:linear-gradient(theme,Canvas);color-scheme:light dark}</style><div></div>';
        $result = (new HtmlRenderer())->render($html, new RenderingContext(100, 100, null, null, null, null, ['prefers-color-scheme' => 'dark'], '', ['theme' => 'light-dark(red,blue)']));
        $operations = array_values(array_filter($result->getDisplayList()->getOperations(), static fn (array $op): bool => 'border' === $op['type']));
        self::assertSame([[0.0, 0.0, 1.0], 1.0], $operations[0]['source']['gradient']['stops'][0][0]);
        self::assertSame([[0.0, 0.0, 0.0], 1.0], $operations[0]['source']['gradient']['stops'][1][0]);
    }
}
