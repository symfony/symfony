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
use Symfony\Component\HtmlRenderer\HtmlRenderer;
use Symfony\Component\HtmlRenderer\RenderingContext;

final class GradientPatternTest extends TestCase
{
    public function testDefaultGradientOriginIsThePaddingBoxAndClipDoesNotResizeIt()
    {
        $operations = new HtmlRenderer()->render('<!doctype html><div style="width:10px;height:6px;padding:1px;border:2px solid;background:linear-gradient(to right,red,blue);background-clip:content-box"></div>', new RenderingContext(100, 100))->getDisplayList()->getOperations();
        $gradient = array_values(array_filter($operations, static fn (array $op): bool => 'gradient' === $op['type']))[0];
        self::assertSame(12.0, $gradient['gradient']['length']);
        self::assertSame(2.0, $gradient['pattern']['x']['start']);
        self::assertSame(12.0, $gradient['pattern']['x']['size']);
        self::assertSame(8.0, $gradient['pattern']['y']['size']);
        self::assertSame(10.0, $operations[0]['width']);
    }

    public function testFractionalGradientTilesShareImagePositionRepeatAndRoundGeometry()
    {
        $operations = new HtmlRenderer()->render('<!doctype html><div style="width:20.5px;height:10px;background:linear-gradient(to right,red,blue) right 1.25px bottom .5px / 4.5px 2.25px no-repeat"></div>', new RenderingContext(100, 100))->getDisplayList()->getOperations();
        self::assertSame(4.5, $operations[0]['gradient']['length']);
        self::assertSame(14.75, $operations[0]['pattern']['x']['start']);
        self::assertSame(7.25, $operations[0]['pattern']['y']['start']);
        self::assertSame(1, $operations[0]['pattern']['x']['count']);
    }

    public function testGradientAutoAxisHasNoIntrinsicAspectRatioAndRoundResizesOnlyItsAxis()
    {
        $operations = new HtmlRenderer()->render('<!doctype html><div style="width:20.5px;height:10px;background:linear-gradient(to right,red,blue);background-size:4.5px auto;background-repeat:round no-repeat"></div>', new RenderingContext(100, 100))->getDisplayList()->getOperations();
        self::assertSame(4.1, $operations[0]['gradient']['length']);
        self::assertSame(4.1, $operations[0]['pattern']['x']['size']);
        self::assertSame(10.0, $operations[0]['pattern']['y']['size']);
        self::assertSame(1, $operations[0]['pattern']['y']['count']);
    }
}
