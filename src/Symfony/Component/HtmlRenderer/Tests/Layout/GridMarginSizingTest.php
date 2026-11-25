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

final class GridMarginSizingTest extends TestCase
{
    public function testAutoRowsContainFractionalItemMarginsWithoutCollapsing()
    {
        $style = ['margin-top' => '2.5px', 'margin-bottom' => '3.25px'];
        $root = new Box(null, 'grid', ['width' => '100px', 'row-gap' => '1.5px', 'font-size' => '10px', 'line-height' => '10px'], [new Box(null, 'block', $style, [], 'one'), new Box(null, 'block', $style, [], 'two')]);
        $layout = (new LayoutEngine(new MonospaceTextMeasurer()))->layout($root, 100, 100);
        self::assertSame(2.5, $layout->children[0]->bounds->y);
        self::assertSame(19.75, $layout->children[1]->bounds->y);
        self::assertSame(10.0, $layout->children[0]->bounds->height);
        self::assertSame(33.0, $layout->bounds->height);
    }

    public function testAutoColumnIncludesItemHorizontalChromeAndMargins()
    {
        $root = new Box(null, 'grid', ['width' => '100px', 'grid-template-columns' => 'auto 1fr', 'font-size' => '10px', 'line-height' => '10px'], [new Box(null, 'block', ['margin' => '0 3px', 'padding' => '0 2px', 'border-width' => '1px'], [], 'one'), new Box(null, 'block', [], [], 'two')]);
        $layout = (new LayoutEngine(new MonospaceTextMeasurer()))->layout($root, 100, 100);
        self::assertSame(21.0, $layout->children[0]->bounds->width);
        self::assertSame(27.0, $layout->children[1]->bounds->x);
        self::assertSame(73.0, $layout->children[1]->bounds->width);
    }

    public function testDefiniteTrackStretchAndCenterUseMarginBox()
    {
        $style = ['margin' => '2.5px 3.25px'];
        $root = new Box(null, 'grid', ['width' => '100px', 'grid-template-rows' => '30px', 'font-size' => '10px', 'line-height' => '10px'], [new Box(null, 'block', $style, [], 'one')]);
        $layout = (new LayoutEngine(new MonospaceTextMeasurer()))->layout($root, 100, 100);
        self::assertSame(93.5, $layout->children[0]->bounds->width);
        self::assertSame(25.0, $layout->children[0]->bounds->height);
        $root = new Box(null, 'grid', ['width' => '100px', 'grid-template-rows' => '30px', 'align-items' => 'center', 'justify-items' => 'center', 'font-size' => '10px', 'line-height' => '10px'], [new Box(null, 'block', $style, [], 'one')]);
        $layout = (new LayoutEngine(new MonospaceTextMeasurer()))->layout($root, 100, 100);
        self::assertSame(42.5, $layout->children[0]->bounds->x);
        self::assertSame(10.0, $layout->children[0]->bounds->y);
    }
}
