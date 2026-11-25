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
use Symfony\Component\HtmlRenderer\HtmlRenderer;
use Symfony\Component\HtmlRenderer\Layout\TableGeometry;
use Symfony\Component\HtmlRenderer\RenderingContext;

final class FixedTableTest extends TestCase
{
    public function testFixedTracksIgnoreLaterContentAndKeepFractionalRoom()
    {
        $result = (new HtmlRenderer())->render('<style>html,body,td{margin:0;padding:0}table{table-layout:fixed;width:100.5px;border-spacing:0.5px 0}</style><table><tr><td id="a">a</td><td id="b">bb</td><td id="c">c</td></tr><tr><td>long long long long long</td><td>x</td><td>y</td></tr></table>', new RenderingContext(200, 200));
        foreach (['a', 'b', 'c'] as $id) {
            self::assertEqualsWithDelta(99.5 / 3, $result->getBounds($result->getDocument()->getElementById($id))[0]->width, 1.0E-10);
        }
    }

    public function testColumnsPrecedeFirstRowWidthsAndDoNotGenerateAnonymousRows()
    {
        $result = (new HtmlRenderer())->render('<style>html,body,td{margin:0;padding:0}table{table-layout:fixed;width:100px}#late{width:90px}</style><table><colgroup><col style="width:40px"><col></colgroup><tr><td id="a" style="width:10px">a</td><td id="b">b</td></tr><tr><td id="late">c</td><td>d</td></tr></table>', new RenderingContext(200, 200));
        $document = $result->getDocument();
        self::assertSame(40.0, $result->getBounds($document->getElementById('a'))[0]->width);
        self::assertSame(60.0, $result->getBounds($document->getElementById('b'))[0]->width);
        self::assertSame(0.0, $result->getBounds($document->getElementById('a'))[0]->y);
        self::assertSame([], $result->getBounds($document->querySelector('col')));
    }

    public function testFirstRowSpansShareTheirWidthAcrossUnsetColumns()
    {
        $result = (new HtmlRenderer())->render('<style>html,body,td{margin:0;padding:0}table{table-layout:fixed;width:101px;border-spacing:1px 0}</style><table><tr><td style="width:41px" colspan="2">a</td><td>c</td></tr><tr><td id="a">a</td><td id="b">b</td><td id="c">c</td></tr></table>', new RenderingContext(200, 200));
        self::assertSame(20.0, $result->getBounds($result->getDocument()->getElementById('a'))[0]->width);
        self::assertSame(20.0, $result->getBounds($result->getDocument()->getElementById('b'))[0]->width);
        self::assertSame(59.0, $result->getBounds($result->getDocument()->getElementById('c'))[0]->width);
    }

    public function testFixedLayoutWithoutSpecifiedWidthRemainsContentSized()
    {
        $result = (new HtmlRenderer())->render('<style>html,body,td{margin:0;padding:0}table{table-layout:fixed}</style><table><tr><td id="a">long</td><td id="b">b</td></tr></table>', new RenderingContext(200, 200));
        self::assertSame(32.0, $result->getBounds($result->getDocument()->getElementById('a'))[0]->width);
        self::assertSame(8.0, $result->getBounds($result->getDocument()->getElementById('b'))[0]->width);
    }

    public function testExplicitAutoWidthDoesNotActivateFixedLayoutAndPercentDoes()
    {
        $renderer = new HtmlRenderer();
        foreach (['auto' => [32.0, 8.0], '50%' => [50.0, 50.0]] as $width => $expected) {
            $result = $renderer->render('<style>html,body,td{margin:0;padding:0}table{table-layout:fixed;width:'.$width.'}</style><table><tr><td id="a">long</td><td id="b">b</td></tr></table>', new RenderingContext(200, 200));
            self::assertSame($expected, [$result->getBounds($result->getDocument()->getElementById('a'))[0]->width, $result->getBounds($result->getDocument()->getElementById('b'))[0]->width]);
        }
    }

    public function testFixedTrackAllocationPreservesOverflowRatherThanShrinkingSpecifiedWidths()
    {
        self::assertSame([40.0, 40.0, 0.0], TableGeometry::fixedColumns(3, 30.0, [0 => 40.0, 1 => 40.0]));
        self::assertSame([20.0, 10.0], TableGeometry::fixedColumns(2, 30.0, [0 => 10.0, 1 => 5.0]));
        self::assertSame([15.0, 15.0], TableGeometry::fixedColumns(2, 30.0, [0 => 0.0, 1 => 0.0]));
        $result = (new HtmlRenderer())->render('<style>html,body,td{margin:0;padding:0}table{table-layout:fixed;width:30px}</style><table><col style="width:40px"><col style="width:40px"><tr><td id="a">a</td><td id="b">b</td></tr></table>', new RenderingContext(200, 200));
        self::assertSame(80.0, $result->getBounds($result->getDocument()->querySelector('table'))[0]->width);
        self::assertSame(40.0, $result->getBounds($result->getDocument()->getElementById('a'))[0]->width);
        self::assertSame(40.0, $result->getBounds($result->getDocument()->getElementById('b'))[0]->width);
    }

    public function testAutoLayoutGivesSurplusToColumnsWithoutSpecifiedWidths()
    {
        $result = (new HtmlRenderer())->render('<style>html,body,td{margin:0;padding:0}table{width:160px}</style><table><tr><td style="width:64px" id="a">a</td><td id="b">b</td></tr></table>', new RenderingContext(200, 200));
        self::assertSame(64.0, $result->getBounds($result->getDocument()->getElementById('a'))[0]->width);
        self::assertSame(96.0, $result->getBounds($result->getDocument()->getElementById('b'))[0]->width);
    }
}
