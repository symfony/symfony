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

final class IntrinsicSizingTest extends TestCase
{
    public function testWidthKeywordsUseDistinctMinimumMaximumAndAvailableGeometry()
    {
        foreach (['min-content' => 25.0, 'max-content' => 65.0, 'fit-content' => 40.0, 'fit-content(30px)' => 30.0] as $keyword => $width) {
            $box = new Box(null, 'block', ['width' => $keyword, 'font-size' => '10px', 'line-height' => '10px'], [], 'one two three');
            $layout = (new LayoutEngine(new MonospaceTextMeasurer()))->layout($box, 40, 100);
            self::assertSame($width, $layout->bounds->width, $keyword);
        }
    }

    public function testIntrinsicWidthsIncludeChromeAndAvailableMarginsRegardlessOfBoxSizing()
    {
        foreach (['content-box', 'border-box'] as $sizing) {
            $box = new Box(null, 'block', ['width' => 'max-content', 'box-sizing' => $sizing, 'padding' => '2.5px', 'border-width' => '1.25px', 'font-size' => '10px'], [], 'one two three');
            $layout = (new LayoutEngine(new MonospaceTextMeasurer()))->layout($box, 40, 100);
            self::assertSame(72.5, $layout->bounds->width);
            self::assertSame(65.0, $layout->getContentBox()->width);
        }
        $box = new Box(null, 'block', ['width' => 'fit-content', 'padding' => '1px', 'margin' => '0 5px', 'font-size' => '10px'], [], 'one two three');
        $layout = (new LayoutEngine(new MonospaceTextMeasurer()))->layout($box, 50, 100);
        self::assertSame(40.0, $layout->bounds->width);
    }

    public function testExplicitIntrinsicFixedDimensionsCanCenterWithoutAutoStretch()
    {
        $box = new Box(null, 'block', ['position' => 'fixed', 'left' => '0', 'right' => '0', 'top' => '0', 'bottom' => '0', 'margin' => 'auto', 'width' => 'max-content', 'height' => 'max-content', 'font-size' => '10px', 'line-height' => '10px', 'border-width' => '1px'], [], 'Centered');
        $layout = (new LayoutEngine(new MonospaceTextMeasurer()))->layout(new Box(null, 'block', [], [$box]), 100.5, 80.25);
        self::assertSame(42.0, $layout->children[0]->bounds->width);
        self::assertSame(12.0, $layout->children[0]->bounds->height);
        self::assertSame(29.25, $layout->children[0]->bounds->x);
        self::assertSame(34.125, $layout->children[0]->bounds->y);
    }

    public function testFitContentArgumentCanOverflowAndPositionedKeywordUsesInsetBudget()
    {
        $engine = new LayoutEngine(new MonospaceTextMeasurer());
        $layout = $engine->layout(new Box(null, 'block', ['width' => 'fit-content(60px)', 'font-size' => '10px'], [], 'one two three'), 40, 100);
        self::assertSame(60.0, $layout->bounds->width);
        $box = new Box(null, 'block', ['position' => 'absolute', 'left' => '60px', 'top' => '0', 'width' => 'fit-content', 'font-size' => '10px', 'line-height' => '10px'], [], 'one two three');
        $layout = $engine->layout(new Box(null, 'block', [], [$box]), 100, 100);
        self::assertSame(40.0, $layout->children[0]->bounds->width);
    }

    public function testPreformattedMaximumAndNaturalIntrinsicHeightPreserveLines()
    {
        $engine = new LayoutEngine(new MonospaceTextMeasurer());
        $layout = $engine->layout(new Box(null, 'block', ['width' => 'max-content', 'height' => 'min-content', 'white-space' => 'pre', 'font-size' => '10px', 'line-height' => '10px'], [], "Open\nSave"), 100, 100);
        self::assertSame(20.0, $layout->bounds->width);
        self::assertSame(20.0, $layout->bounds->height);
    }

    public function testFitContentFunctionArgumentUsesDeclaredBoxSizing()
    {
        foreach (['content-box' => 47.5, 'border-box' => 40.0] as $sizing => $width) {
            $box = new Box(null, 'block', ['width' => 'fit-content(40px)', 'box-sizing' => $sizing, 'padding' => '2.5px', 'border-width' => '1.25px', 'font-size' => '10px'], [], 'one two three');
            $layout = (new LayoutEngine(new MonospaceTextMeasurer()))->layout($box,100,100);
            self::assertSame($width,$layout->bounds->width);
        }
    }
}
