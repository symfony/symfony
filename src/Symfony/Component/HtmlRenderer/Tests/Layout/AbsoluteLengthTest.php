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
use Symfony\Component\HtmlRenderer\Font\MonospaceTextMeasurer;
use Symfony\Component\HtmlRenderer\HtmlRenderer;
use Symfony\Component\HtmlRenderer\Layout\Box;
use Symfony\Component\HtmlRenderer\Layout\LayoutEngine;
use Symfony\Component\HtmlRenderer\RenderingContext;

final class AbsoluteLengthTest extends TestCase
{
    public function testAbsoluteUnitsRetainFractionalCssPixels()
    {
        foreach (['px' => 1.0, 'pt' => 4 / 3, 'pc' => 16.0, 'in' => 96.0, 'cm' => 96 / 2.54, 'mm' => 96 / 25.4, 'q' => 96 / 101.6] as $unit => $factor) {
            $box = new Box(null, 'block', ['width' => '.125'.$unit, 'height' => '.375'.$unit]);
            $layout = (new LayoutEngine(new MonospaceTextMeasurer()))->layout($box, 200, 100);
            self::assertEqualsWithDelta(.125 * $factor, $layout->bounds->width, 0.000001, $unit);
            self::assertEqualsWithDelta(.375 * $factor, $layout->bounds->height, 0.000001, $unit);
        }
    }

    public function testAbsoluteUnitsInMathUseTheContainingBlockWithoutRounding()
    {
        $box = new Box(null, 'block', ['width' => 'calc(50% - .125in)', 'height' => 'min(2cm, 1pc)']);
        $engine = new LayoutEngine(new MonospaceTextMeasurer());
        self::assertSame(88.25, $engine->layout($box, 200.5, 100)->bounds->width);
        self::assertSame(138.25, $engine->layout($box, 300.5, 100)->bounds->width);
        self::assertSame(16.0, $engine->layout($box, 300.5, 100)->bounds->height);
    }

    public function testComputedFontAndLayoutUseTheSameAbsoluteUnits()
    {
        foreach (['pt' => '12', 'pc' => '1', 'in' => '0.16666666666666666', 'cm' => '0.42333333333333334', 'mm' => '4.233333333333333', 'q' => '16.933333333333334'] as $unit => $number) {
            $result = (new HtmlRenderer())->render('<p style="margin:0;font-size:'.$number.$unit.';line-height:1.25;width:1in">X</p>', new RenderingContext(200, 100));
            $element = $result->getDocument()->querySelector('p');
            self::assertEqualsWithDelta(16.0, (float) $result->getComputedStyle($element)['font-size'], 0.000001, $unit);
            self::assertSame(96.0, $result->getBounds($element)[0]->width);
            self::assertEqualsWithDelta(20.0, $result->getBounds($element)[0]->height, 0.000001, $unit);
        }
    }
}
