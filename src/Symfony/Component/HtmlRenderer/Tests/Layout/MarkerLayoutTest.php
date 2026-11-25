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

final class MarkerLayoutTest extends TestCase
{
    public function testOutsideMarkerHasItsOwnBoxWithoutTakingInlineRoom()
    {
        $box = new Box(null, 'list-item', ['font-size' => '10px', 'line-height' => '12px'], [new Box(null, 'inline', ['list-style-position' => 'outside'], [], '1. ', null, null, 'marker'), new Box(null, 'inline', [], [], 'A')]);
        $fragment = (new LayoutEngine(new MonospaceTextMeasurer()))->layout($box, 100, 100);
        self::assertSame(-15.0, $fragment->children[0]->bounds->x);
        self::assertSame(0.0, $fragment->children[1]->bounds->x);
        self::assertSame('marker', $fragment->children[0]->pseudoElement);
        self::assertSame(12.0, $fragment->bounds->height);
    }

    public function testInsideMarkerSharesInlineFormattingAndEmptyItemRetainsLineHeight()
    {
        $marker = new Box(null, 'inline', ['list-style-position' => 'inside'], [], '1. ', null, null, 'marker');
        $engine = new LayoutEngine(new MonospaceTextMeasurer());
        $fragment = $engine->layout(new Box(null, 'list-item', ['font-size' => '10px', 'line-height' => '12px'], [$marker, new Box(null, 'inline', [], [], 'A')]), 100, 100);
        self::assertSame(15.0, $fragment->children[1]->bounds->x);
        $outside = new Box(null, 'inline', [], [], '• ', null, null, 'marker');
        $fragment = $engine->layout(new Box(null, 'list-item', ['font-size' => '10px', 'line-height' => '12px'], [$outside]), 100, 100);
        self::assertSame(12.0, $fragment->bounds->height);
    }
}
