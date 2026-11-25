<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HtmlRenderer\Tests\Paint;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HtmlRenderer\HtmlRenderer;
use Symfony\Component\HtmlRenderer\RenderingContext;

final class OutlinePaintingTest extends TestCase
{
    public function testFractionalOutlineDoesNotChangeLayoutAndPaintsOutsideOwnClip()
    {
        $document = \Dom\HTMLDocument::createFromString('<!doctype html><div id="a" style="width:20.5px;height:10.25px;overflow:hidden;outline:1.5px dashed red;outline-offset:2.25px">A</div>');
        $element = $document->getElementById('a');
        $result = (new HtmlRenderer())->render($document, new RenderingContext(100, 100));
        $bounds = $result->getBounds($element)[0];
        self::assertSame(20.5, $bounds->width);
        self::assertSame(10.25, $bounds->height);
        $outlines = array_values(array_filter($result->getDisplayList()->getOperations(), static fn (array $op): bool => 'border' === $op['type']));
        self::assertCount(1, $outlines);
        self::assertSame(-3.75, $outlines[0]['x']);
        self::assertSame(28.0, $outlines[0]['width']);
        self::assertSame(1.5, $outlines[0]['thickness']);
        self::assertSame('dashed', $outlines[0]['style']);
    }

    public function testEarlyOutlinePaintsAfterFlowAndBeforePositivePositionedContexts()
    {
        $document = \Dom\HTMLDocument::createFromString('<!doctype html><div id="owner" style="width:40px;height:40px;outline:5px solid red"><div id="flow">flow</div><div id="positive" style="position:relative;z-index:1">layer</div></div>');
        $result = (new HtmlRenderer())->render($document, new RenderingContext(100, 100));
        $steps = \Symfony\Component\HtmlRenderer\Paint\PaintOrder::build($result->getLayout());
        $events = [];
        foreach ($steps as $step) {
            if ('outline' === $step['type'] || 'content' === $step['type'] && null !== $step['fragment']->text) {
                $events[] = 'outline' === $step['type'] ? 'outline' : $step['fragment']->text;
            }
        }
        self::assertSame(['flow', 'outline', 'layer'], $events);
    }

    public function testOutlineRetainsAncestorClipAndTransformWithoutItsOwnOverflowClip()
    {
        $document = \Dom\HTMLDocument::createFromString('<!doctype html><section style="width:30px;height:30px;overflow:hidden"><div id="a" style="width:20px;height:10px;overflow:hidden;transform:translate(2.5px,1.25px);outline:1px solid blue">A</div></section>');
        $result = (new HtmlRenderer())->render($document, new RenderingContext(100, 100));
        $outline = array_values(array_filter(\Symfony\Component\HtmlRenderer\Paint\PaintOrder::build($result->getLayout()), static fn (array $step): bool => 'outline' === $step['type']))[0];
        self::assertCount(1, $outline['clips']);
        self::assertSame('section', $outline['clips'][0]['owner']->node->localName);
        self::assertSame([1.0, 0.0, 0.0, 1.0, 2.5, 1.25], $outline['matrix']);
    }

    public function testShorthandCompetesBeforeCascadeAndResetsOmittedParts()
    {
        $result = (new HtmlRenderer())->render('<style>div {outline:thick dashed red;outline:solid;outline-offset:-0.5px} #off{outline:0 solid red}</style><div>A</div><div id="off">B</div>', new RenderingContext(100, 100));
        $outlines = array_values(array_filter($result->getDisplayList()->getOperations(), static fn (array $operation): bool => 'border' === $operation['type']));
        self::assertCount(1, $outlines);
        self::assertSame(3.0, $outlines[0]['thickness']);
        self::assertSame('solid', $outlines[0]['style']);
        self::assertSame('#000000', $outlines[0]['color']);
    }

    public function testSharedRingGeometryPreservesIntegerDeviceCoordinates()
    {
        for ($width = 1; $width <= 10; ++$width) {
            for ($height = 1; $height <= 10; ++$height) {
                for ($offset = -5; $offset <= 5; ++$offset) {
                    $outer = \Symfony\Component\HtmlRenderer\Paint\OutlineGeometry::outer(new \Symfony\Component\HtmlRenderer\Geometry\Rect(3, 7, $width, $height), 1, 1, $offset, $offset);
                    $distance = $offset + 1;
                    if ($width + 2 * $distance <= 0 || $height + 2 * $distance <= 0) {
                        self::assertNull($outer);
                    } else {
                        self::assertSame([(float) (3 - $distance), (float) (7 - $distance), (float) ($width + 2 * $distance), (float) ($height + 2 * $distance)], [$outer->x, $outer->y, $outer->width, $outer->height]);
                    }
                }
            }
        }
    }
}
