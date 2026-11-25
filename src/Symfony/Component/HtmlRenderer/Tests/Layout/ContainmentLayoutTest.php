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

final class ContainmentLayoutTest extends TestCase
{
    public function testContentBoxUsesFractionalResolvedPaddingAndBorders()
    {
        $box = new Box(null, 'block', ['box-sizing' => 'border-box', 'width' => '100.5px', 'height' => '50.25px', 'padding' => '10%', 'border-width' => '1.25px']);
        $fragment = (new LayoutEngine(new MonospaceTextMeasurer()))->layout($box, 200, 100);
        $content = $fragment->getContentBox();
        self::assertSame(21.25, $content->x);
        self::assertSame(21.25, $content->y);
        self::assertSame(58.0, $content->width);
        self::assertSame(7.75, $content->height);
    }

    public function testSizeContainerHasZeroAutomaticContentHeightButRetainsChildren()
    {
        $box = new Box(null, 'block', ['container-type' => 'size', 'padding' => '2px'], [new Box(null, 'block', ['height' => '20px'])]);
        $fragment = (new LayoutEngine(new MonospaceTextMeasurer()))->layout($box, 100, 100);
        self::assertSame(4.0, $fragment->bounds->height);
        self::assertSame(0.0, $fragment->getContentBox()->height);
        self::assertSame(20.0, $fragment->children[0]->bounds->height);
    }

    public function testInlineSizeContainmentPreventsFlexAndGridIntrinsicFeedback()
    {
        $item = new Box(null, 'block', ['container-type' => 'inline-size', 'flex-grow' => '1'], [], str_repeat('W', 100));
        $sibling = new Box(null, 'block', ['width' => '20px'], [], 'a');
        $engine = new LayoutEngine(new MonospaceTextMeasurer());
        $flex = $engine->layout(new Box(null, 'flex', [], [$item, $sibling]), 100, 100);
        self::assertSame(80.0, $flex->children[0]->bounds->width);
        $grid = $engine->layout(new Box(null, 'grid', ['grid-template-columns' => '1fr 1fr'], [$item, $sibling]), 100, 100);
        self::assertSame(50.0, $grid->children[0]->bounds->width);
    }

    public function testIntrinsicFallbackAndExplicitHeightRemainIndependent()
    {
        $engine = new LayoutEngine(new MonospaceTextMeasurer());
        $box = new Box(null, 'inline-block', ['container-type' => 'size', 'contain-intrinsic-size' => '30.5px 20.25px', 'padding' => '2px'], [], str_repeat('W', 100));
        $fragment = $engine->layout($box, 100, 100);
        self::assertSame(30.5, $fragment->getContentBox()->width);
        self::assertSame(20.25, $fragment->getContentBox()->height);
        $fragment = $engine->layout(new Box(null, 'block', ['container-type' => 'size', 'height' => '40.5px'], [new Box(null, 'block', ['height' => '100px'])]), 100, 100);
        self::assertSame(40.5, $fragment->getContentBox()->height);
    }

    public function testSizeContainerEstablishesPositionedContainingBlock()
    {
        $box = new Box(null, 'block', ['width' => '40px', 'height' => '30px', 'container-type' => 'inline-size', 'padding' => '2px', 'border-width' => '1px'], [
            new Box(null, 'block', ['position' => 'absolute', 'left' => '50%', 'top' => '50%', 'width' => '1px', 'height' => '1px']),
            new Box(null, 'block', ['position' => 'fixed', 'left' => '50%', 'top' => '50%', 'width' => '1px', 'height' => '1px']),
        ]);
        $fragment = (new LayoutEngine(new MonospaceTextMeasurer()))->layout($box, 200, 200);
        self::assertSame(23.0, $fragment->children[0]->bounds->x);
        self::assertSame(18.0, $fragment->children[0]->bounds->y);
        self::assertSame(23.0, $fragment->children[1]->bounds->x);
        self::assertSame(18.0, $fragment->children[1]->bounds->y);
    }
}
