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
use Symfony\Component\HtmlRenderer\Resource\ImageResource;

final class BorderImagePaintingTest extends TestCase
{
    public function testBorderOperationKeepsMaskAndResolvedGradientSource()
    {
        $box = new Box(null, 'block', ['width' => '20.5px', 'height' => '10.25px', 'border-width' => '1px', 'border-left-width' => '2.5px', 'border-image-source' => 'linear-gradient(to right, currentColor, brand)', 'color' => 'red']);
        $layout = (new LayoutEngine(new MonospaceTextMeasurer(), null, ['brand' => 'blue']))->layout($box, 100, 100);
        $operation = (new Painter())->paint($layout)->getOperations()[0];
        self::assertSame('border', $operation['type']);
        self::assertSame(2.5, $operation['sides']['left']['thickness']);
        self::assertSame('gradient', $operation['source']['type']);
        self::assertSame([[1.0, 0.0, 0.0], 1.0], $operation['source']['gradient']['stops'][0][0]);
        self::assertSame([[0.0, 0.0, 1.0], 1.0], $operation['source']['gradient']['stops'][1][0]);
        self::assertSame(24.0, $operation['source-bounds']['width']);
        self::assertStringNotContainsString('brand', json_encode($operation, \JSON_THROW_ON_ERROR));
    }

    public function testImageSourceSurvivesPositionAndScrollCopiesAsPlainData()
    {
        $image = new ImageResource('image bytes', 'image/png', 2.0, 2.0);
        $box = new Box(null, 'block', ['width' => '40px', 'height' => '20px', 'overflow' => 'auto', '--html-scroll-y' => '10'], [
            new Box(null, 'block', ['height' => '30px']),
            new Box(null, 'block', ['width' => '10px', 'height' => '10px', 'border-width' => '1px'], [], null, null, null, null, null, $image),
        ]);
        $layout = (new LayoutEngine(new MonospaceTextMeasurer()))->layout($box, 100, 100);
        $operation = array_values(array_filter((new Painter())->paint($layout)->getOperations(), static fn (array $op): bool => 'border' === $op['type']))[0];
        self::assertSame(['type' => 'image', 'data' => 'image bytes', 'mime-type' => 'image/png', 'color' => 'black'], $operation['source']);
        self::assertSame(20.0, $operation['source-bounds']['y']);
        self::assertSame(12.0, $operation['source-bounds']['width']);
    }

    public function testShorthandAndDataResourceWorkThroughStandalonePipeline()
    {
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="2" height="2"><rect width="2" height="2" fill="red"/></svg>';
        $html = '<style>div{width:20px;height:10px;border:1px solid;border-image:url("data:image/svg+xml;base64,'.base64_encode($svg).'") 20 / 3 / 2 repeat}</style><div></div>';
        $result = (new HtmlRenderer())->render($html, new RenderingContext(100, 100));
        $operation = array_values(array_filter($result->getDisplayList()->getOperations(), static fn (array $op): bool => 'border' === $op['type']))[0];
        self::assertSame('svg', $operation['source']['type']);
        self::assertStringContainsString('<svg', $operation['source']['data']);
        self::assertSame(1.0, $operation['thickness']);
    }
}
