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

final class BackgroundClipTest extends TestCase
{
    public function testSolidColorUsesFractionalContentBoxWithoutClippingBorder()
    {
        $box = new Box(null, 'block', ['width' => '20.5px', 'height' => '10.25px', 'padding' => '1.25px 2.5px 3.75px 4.5px', 'border-width' => '0.5px 1.5px 2.5px 3.5px', 'background-color' => 'red', 'background-clip' => 'content-box']);
        $operations = $this->paint($box);
        self::assertSame(['rectangle', 'border'], array_column($operations, 'type'));
        self::assertSame([8.0, 1.75, 20.5, 10.25], array_map(static fn ($key) => $operations[0][$key], ['x', 'y', 'width', 'height']));
        self::assertSame([0.0, 0.0, 32.5, 18.25], array_map(static fn ($key) => $operations[1][$key], ['x', 'y', 'width', 'height']));
    }

    public function testGradientClipPreservesFullPaintingCoordinateSystem()
    {
        $box = new Box(null, 'block', ['box-sizing' => 'border-box', 'width' => '40px', 'height' => '20px', 'padding' => '3px', 'border-width' => '2px', 'background-image' => 'linear-gradient(to right, red, blue)', 'background-origin' => 'border-box', 'background-clip' => 'padding-box']);
        $operations = $this->paint($box);
        self::assertSame(['push-clip', 'gradient', 'pop-clip', 'border'], array_column($operations, 'type'));
        self::assertSame([2.0, 2.0, 36.0, 16.0], array_map(static fn ($key) => $operations[0][$key], ['x', 'y', 'width', 'height']));
        self::assertSame([0.0, 0.0, 40.0, 20.0], array_map(static fn ($key) => $operations[1][$key], ['x', 'y', 'width', 'height']));
        self::assertSame(40.0, $operations[1]['gradient']['length']);
        self::assertSame(40.0, $operations[3]['width']);
    }

    public function testLayerIndicesSurviveSkippedImagesAndColorUsesLastImageLayerClip()
    {
        $box = new Box(null, 'block', ['width' => '20px', 'height' => '10px', 'padding' => '3px', 'border-width' => '2px', 'background-color' => 'black', 'background-image' => 'linear-gradient(red,blue), none, linear-gradient(green,yellow)', 'background-clip' => 'content-box, border-box, padding-box']);
        $operations = $this->paint($box);
        self::assertSame(['rectangle', 'push-clip', 'gradient', 'pop-clip', 'push-clip', 'gradient', 'pop-clip', 'border'], array_column($operations, 'type'));
        self::assertSame(26.0, $operations[0]['width']);
        self::assertSame(26.0, $operations[1]['width']);
        self::assertSame(20.0, $operations[4]['width']);
    }

    public function testColorClipListCyclesAndTruncatesToImageLayerCount()
    {
        foreach ([['none', 'padding-box,content-box', 26.0], ['linear-gradient(red,blue),linear-gradient(green,yellow)', 'padding-box,content-box,border-box', 20.0], ['linear-gradient(red,blue),none,linear-gradient(green,yellow)', 'content-box,padding-box', 20.0]] as [$images, $clips, $width]) {
            $box = new Box(null, 'block', ['width' => '20px', 'height' => '10px', 'padding' => '3px', 'border-width' => '2px', 'background-color' => 'black', 'background-image' => $images, 'background-clip' => $clips]);
            self::assertSame($width, $this->paint($box)[0]['width']);
        }
    }

    public function testCssDeclarationReachesStandalonePainting()
    {
        $result = new HtmlRenderer()->render('<!doctype html><div style="width:20px;height:10px;padding:3px;border:2px solid;background:red;background-clip:content-box"></div>', new RenderingContext(100, 100));
        $rectangles = array_values(array_filter($result->getDisplayList()->getOperations(), static fn (array $op) => 'rectangle' === $op['type']));
        self::assertSame([5.0, 5.0, 20.0, 10.0], array_map(static fn ($key) => $rectangles[0][$key], ['x', 'y', 'width', 'height']));
    }

    private function paint(Box $box): array
    {
        $layout = (new LayoutEngine(new MonospaceTextMeasurer()))->layout($box, 100, 100);

        return (new Painter())->paint($layout)->getOperations();
    }
}
