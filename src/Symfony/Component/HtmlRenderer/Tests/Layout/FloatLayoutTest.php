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

final class FloatLayoutTest extends TestCase
{
    public function testLeftFloatExcludesOnlyTheLinesItOverlaps()
    {
        $box = new Box(null, 'block', ['font-size' => '10px', 'line-height' => '10px'], [new Box(null, 'block', ['float' => 'left', 'width' => '20.5px', 'height' => '20px']), new Box(null, 'block', [], [new Box(null, 'inline', [], [], 'one two three four five six')])]);
        $layout = (new LayoutEngine(new MonospaceTextMeasurer()))->layout($box, 60.5, 100);
        $lines = $layout->children[1]->children[0]->children;
        self::assertSame(20.5, $lines[0]->bounds->x);
        self::assertSame(20.5, $lines[1]->bounds->x);
        self::assertSame(0.0, $lines[2]->bounds->x);
        self::assertSame(0.0, $layout->children[1]->bounds->y);
    }

    public function testRightFloatAndClearKeepFractionalCoordinates()
    {
        $box = new Box(null, 'block', [], [new Box(null, 'block', ['float' => 'right', 'width' => '20.25px', 'height' => '10.5px']), new Box(null, 'block', ['clear' => 'right', 'height' => '5px'])]);
        $layout = (new LayoutEngine(new MonospaceTextMeasurer()))->layout($box, 100.5, 100);
        self::assertSame(80.25, $layout->children[0]->bounds->x);
        self::assertSame(10.5, $layout->children[1]->bounds->y);
        self::assertSame(15.5, $layout->bounds->height);
    }

    public function testFlowRootContainsItsFloatingChildren()
    {
        $box = new Box(null, 'flow-root', [], [new Box(null, 'block', ['float' => 'left', 'width' => '20px', 'height' => '10.5px'])]);
        $layout = (new LayoutEngine(new MonospaceTextMeasurer()))->layout($box, 100.5, 100);
        self::assertSame(10.5, $layout->bounds->height);
    }

    public function testIndependentFormattingContextUsesRoomBesideFloat()
    {
        $box = new Box(null, 'block', [], [new Box(null, 'block', ['float' => 'left', 'width' => '20.5px', 'height' => '20px']), new Box(null, 'flow-root', ['height' => '10px'])]);
        $layout = (new LayoutEngine(new MonospaceTextMeasurer()))->layout($box, 60.5, 100);
        self::assertSame(20.5, $layout->children[1]->bounds->x);
        self::assertSame(40.0, $layout->children[1]->bounds->width);
    }
}
