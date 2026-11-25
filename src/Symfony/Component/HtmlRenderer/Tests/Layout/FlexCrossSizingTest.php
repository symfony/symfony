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
use Symfony\Component\HtmlRenderer\Layout\Box;
use Symfony\Component\HtmlRenderer\Layout\LayoutEngine;

final class FlexCrossSizingTest extends TestCase
{
    public function testColumnNonStretchAutoWidthUsesIntrinsicContentAndChrome()
    {
        $child = new Box(null, 'block', ['padding' => '2.5px', 'border-width' => '1.25px'], [], 'center');
        $box = new Box(null, 'flex', ['width' => '100.5px', 'flex-direction' => 'column', 'align-items' => 'center', 'font-size' => '10px', 'line-height' => '10px'], [$child]);
        $fragment = (new LayoutEngine(new MonospaceTextMeasurer()))->layout($box, 200, 100)->children[0];
        self::assertSame(37.5, $fragment->bounds->width);
        self::assertSame(31.5, $fragment->bounds->x);
        self::assertSame(17.5, $fragment->bounds->height);
    }

    public function testColumnAutoCrossWidthCapsAndWrapsBeforeDeterminingMainSize()
    {
        $box = new Box(null, 'flex', ['width' => '35px', 'flex-direction' => 'column', 'align-items' => 'flex-end', 'font-size' => '10px', 'line-height' => '10px'], [new Box(null, 'block', [], [], 'one two three'), new Box(null, 'block', ['width' => '20px'], [], 'x')]);
        $fragments = (new LayoutEngine(new MonospaceTextMeasurer()))->layout($box, 100, 100)->children;
        self::assertSame(35.0, $fragments[0]->bounds->width);
        self::assertSame(20.0, $fragments[0]->bounds->height);
        self::assertSame(15.0, $fragments[1]->bounds->x);
        self::assertSame(20.0, $fragments[1]->bounds->y);
    }
}
