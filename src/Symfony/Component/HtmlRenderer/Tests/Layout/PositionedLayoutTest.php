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

final class PositionedLayoutTest extends TestCase
{
    public function testAbsolutePositionSkipsStaticAncestorsAndUsesPaddingBox()
    {
        $box = new Box(null, 'block', ['position' => 'relative', 'box-sizing' => 'border-box', 'width' => '100px', 'height' => '80px', 'padding' => '5px', 'border-width' => '1px'], [new Box(null, 'block', ['width' => '40px', 'height' => '20px', 'margin-left' => '20px'], [new Box(null, 'block', ['position' => 'absolute', 'left' => '10%', 'top' => '50%', 'width' => '10px', 'height' => '5px'])])]);
        $fragment = (new LayoutEngine(new MonospaceTextMeasurer()))->layout($box, 100, 100);
        $absolute = $fragment->children[0]->children[0];
        self::assertEqualsWithDelta(10.8, $absolute->bounds->x, 0.000001);
        self::assertSame(40.0, $absolute->bounds->y);
    }

    public function testFixedPositionUsesViewportAcrossPositionedAncestor()
    {
        $box = new Box(null, 'block', ['position' => 'relative', 'width' => '80px', 'height' => '60px', 'margin' => '10px'], [new Box(null, 'block', ['position' => 'fixed', 'right' => '1.5px', 'bottom' => '2.25px', 'width' => '10px', 'height' => '5px'])]);
        $fragment = (new LayoutEngine(new MonospaceTextMeasurer()))->layout($box, 100, 80);
        self::assertSame(88.5, $fragment->children[0]->bounds->x);
        self::assertSame(72.75, $fragment->children[0]->bounds->y);
    }

    public function testBottomOffsetCanUseAutoSizedContainingBlock()
    {
        $box = new Box(null, 'block', ['position' => 'relative'], [new Box(null, 'block', ['height' => '30.5px']), new Box(null, 'block', ['position' => 'absolute', 'bottom' => '0', 'width' => '10px', 'height' => '5px'])]);
        $fragment = (new LayoutEngine(new MonospaceTextMeasurer()))->layout($box, 100, 100);
        self::assertSame(25.5, $fragment->children[1]->bounds->y);
        self::assertSame(30.5, $fragment->bounds->height);
    }

    public function testOpposingInsetsResolveAutomaticWidth()
    {
        $box = new Box(null, 'block', ['position' => 'relative'], [new Box(null, 'block', ['position' => 'absolute', 'left' => '2.5px', 'right' => '3px', 'height' => '5px'])]);
        $fragment = (new LayoutEngine(new MonospaceTextMeasurer()))->layout($box, 100, 100);
        self::assertSame(94.5, $fragment->children[0]->bounds->width);
    }

    public function testRelativeVisualOffsetDoesNotMoveFollowingFlow()
    {
        $box = new Box(null, 'block', [], [new Box(null, 'block', ['position' => 'relative', 'top' => '5.5px', 'height' => '10px']), new Box(null, 'block', ['height' => '2px'])]);
        $fragment = (new LayoutEngine(new MonospaceTextMeasurer()))->layout($box, 100, 100);
        self::assertSame(5.5, $fragment->children[0]->bounds->y);
        self::assertSame(10.0, $fragment->children[1]->bounds->y);
        self::assertSame(12.0, $fragment->bounds->height);
    }
}
