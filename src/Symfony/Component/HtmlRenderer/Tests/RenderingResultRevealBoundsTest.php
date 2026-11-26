<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HtmlRenderer\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HtmlRenderer\Geometry\Rect;
use Symfony\Component\HtmlRenderer\Layout\Fragment;
use Symfony\Component\HtmlRenderer\Layout\ScrollGeometry;
use Symfony\Component\HtmlRenderer\Paint\DisplayList;
use Symfony\Component\HtmlRenderer\RenderingResult;

class RenderingResultRevealBoundsTest extends TestCase
{
    public function testBoundsUseCurrentScrollTranslatedCoordinatesAndRetainFractionalClamps()
    {
        $document = \Dom\HTMLDocument::createFromString('<!DOCTYPE html><div></div>');
        $element = $document->querySelector('div');
        $geometry = new ScrollGeometry(new Rect(0, 0, 10, 10), new Rect(0, 0, 50, 50), 1.5, 1.5);
        $fragment = new Fragment($element, new Rect(0, 0, 10, 10), [], null, [], null, null, [], null, null, null, $geometry);
        $result = new RenderingResult($document, new \SplObjectStorage(), $fragment, new DisplayList([]));
        self::assertSame([1.5, 11.75], $result->getScrollOffsetToRevealBounds(new Rect(2, 18.25, 2, 2), $element));
        self::assertSame([0.25, 1.5], $result->getScrollOffsetToRevealBounds(new Rect(-1.25, 2, .5, 2), $element));
        self::assertSame([40.0, 40.0], $result->getScrollOffsetToRevealBounds(new Rect(100, 100, 1, 1), $element));
        self::assertSame([1.5, 19.75], $result->getScrollOffsetToRevealBounds(new Rect(2, 18.25, 2, 2), $element, ['nearest', 'start']));
        self::assertSame([3.5, 19.75], $result->getScrollOffsetToRevealBounds(new Rect(2, 18.25, 2, 2), $element, ['start', 'start']));
        self::assertSame([40.0, 40.0], $result->getScrollOffsetToRevealBounds(new Rect(100, 100, 1, 1), $element, ['start', 'start']));
        self::assertNull($result->getScrollOffsetToRevealBounds(new Rect(0, 0, 1, 1), $document->documentElement));
    }

    public function testPhysicalAxesKeepNearestHorizontalAndStartVerticalThroughNestedTransforms()
    {
        $session = new \Symfony\Component\HtmlRenderer\HtmlRenderer()->createSession('<style>#outer{width:200px;height:150px;overflow:auto;transform:translate(11.5px,7.25px)}#inner{width:60.5px;height:40.25px;overflow:auto;transform:translate(5px,3px)}#target{margin-left:100.25px;margin-top:50.75px;width:10.5px;height:8.25px}#tail{height:100.5px}</style><div id="outer"><div id="inner"><div id="target"></div><div id="tail"></div></div></div>', new \Symfony\Component\HtmlRenderer\RenderingContext(320, 240));
        $result = $session->render();
        $document = $session->getDocument();
        $inner = $document->getElementById('inner');
        $target = $document->getElementById('target');
        self::assertSame([50.25, 18.75], $result->getScrollOffsetToReveal($target, $inner));
        self::assertSame([50.25, 50.75], $result->getScrollOffsetToReveal($target, $inner, false, ['nearest', 'start']));
        self::assertSame([50.25, 50.75], $result->getScrollOffsetToReveal($target, $inner, false, ['start', 'start']));
    }
}
