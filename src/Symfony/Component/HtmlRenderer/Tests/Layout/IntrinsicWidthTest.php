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

final class IntrinsicWidthTest extends TestCase
{
    public function testPositionedBlockChildrenUseMaximumWidthWithOwnFractionalChrome()
    {
        $menu = new Box(null, 'block', ['position' => 'absolute', 'left' => '0', 'top' => '0', 'padding' => '2.5px', 'border-width' => '1.25px'], [new Box(null, 'block', [], [], 'Open'), new Box(null, 'block', [], [], 'Save')]);
        $layout = (new LayoutEngine(new MonospaceTextMeasurer()))->layout(new Box(null, 'block', [], [$menu]), 200, 100);
        self::assertSame(39.5, $layout->children[0]->bounds->width);
        self::assertSame(32.0, $layout->children[0]->getContentBox()->width);
    }

    public function testInlineGroupsSumButSeparateBlocksStartNewIntrinsicLines()
    {
        $menu = new Box(null, 'block', ['position' => 'absolute', 'left' => '0', 'top' => '0'], [new Box(null, 'inline', [], [], 'AB'), new Box(null, 'inline', [], [], 'CD'), new Box(null, 'block', [], [], 'E')]);
        $layout = (new LayoutEngine(new MonospaceTextMeasurer()))->layout(new Box(null, 'block', [], [$menu]), 200, 100);
        self::assertSame(32.0, $layout->children[0]->bounds->width);
    }

    public function testShrinkToFitUsesAvailableInsetSpaceWhileOpposingInsetsStretch()
    {
        $menu = new Box(null, 'block', ['position' => 'absolute', 'left' => '60px', 'top' => '0', 'font-size' => '10px', 'line-height' => '10px', 'border-width' => '1px'], [], 'one two three');
        $stretch = new Box(null, 'block', ['position' => 'absolute', 'left' => '10px', 'right' => '20px', 'top' => '0', 'border-width' => '1px'], [], 'x');
        $layout = (new LayoutEngine(new MonospaceTextMeasurer()))->layout(new Box(null, 'block', [], [$menu, $stretch]), 100, 100);
        self::assertSame(40.0, $layout->children[0]->bounds->width);
        self::assertSame(60.0, $layout->children[0]->bounds->x);
        self::assertSame(70.0, $layout->children[1]->bounds->width);
    }

    public function testAutomaticFixedBoxWithFourInsetsStretchesRatherThanLegacyIntrinsicCentering()
    {
        $box = new Box(null, 'block', ['position' => 'fixed', 'left' => '0', 'right' => '0', 'top' => '0', 'bottom' => '0', 'margin' => 'auto', 'border-width' => '1px'], [], 'Centered');
        $layout = (new LayoutEngine(new MonospaceTextMeasurer()))->layout(new Box(null, 'block', [], [$box]), 100.5, 80.25);
        self::assertSame(100.5, $layout->children[0]->bounds->width);
        self::assertSame(80.25, $layout->children[0]->bounds->height);
        self::assertSame(0.0, $layout->children[0]->bounds->x);
        self::assertSame(0.0, $layout->children[0]->bounds->y);
    }
}
