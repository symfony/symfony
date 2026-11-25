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
use Symfony\Component\HtmlRenderer\Paint\Painter;
use Symfony\Component\HtmlRenderer\RenderingResult;

final class RenderingResultTest extends TestCase
{
    public function testClippedContentCannotReceivePointerInput()
    {
        $document = \Dom\HTMLDocument::createFromString('<!DOCTYPE html><div><button>Click</button></div>');
        $parent = $document->querySelector('div');
        $button = $document->querySelector('button');
        $fragment = new Fragment($parent, new Rect(0, 0, 10, 10), ['overflow' => 'hidden'], null, [new Fragment($button, new Rect(15, 0, 10, 10))]);
        $result = new RenderingResult($document, new \SplObjectStorage(), $fragment, (new Painter())->paint($fragment));
        self::assertNull($result->hitTest(16, 2));
    }

    public function testTextHitResolvesToSemanticElementEvenOutsideItsBox()
    {
        $document = \Dom\HTMLDocument::createFromString('<!DOCTYPE html><a>Link</a>');
        $anchor = $document->querySelector('a');
        $fragment = new Fragment($anchor, new Rect(0, 0, 10, 10), [], null, [new Fragment($anchor->firstChild, new Rect(0, 0, 20, 10), [], 'Link')]);
        $result = new RenderingResult($document, new \SplObjectStorage(), $fragment, (new Painter())->paint($fragment));
        self::assertSame($anchor, $result->hitTest(16, 2));
    }

    public function testPointerEventsNoneAndHiddenElementsAreSkipped()
    {
        $document = \Dom\HTMLDocument::createFromString('<!DOCTYPE html><div><button>Click</button></div>');
        $parent = $document->querySelector('div');
        $button = $document->querySelector('button');
        foreach ([['pointer-events' => 'none'], ['visibility' => 'hidden']] as $style) {
            $fragment = new Fragment($parent, new Rect(0, 0, 10, 10), [], null, [new Fragment($button, new Rect(0, 0, 10, 10), $style)]);
            $result = new RenderingResult($document, new \SplObjectStorage(), $fragment, (new Painter())->paint($fragment));
            self::assertSame($parent, $result->hitTest(2, 2));
        }
    }

    public function testPositionedStackOrderAgreesWithPainting()
    {
        $document = \Dom\HTMLDocument::createFromString('<!DOCTYPE html><div><button id="front">Front</button><button id="back">Back</button></div>');
        $parent = $document->querySelector('div');
        $front = $document->querySelector('#front');
        $back = $document->querySelector('#back');
        $fragment = new Fragment($parent, new Rect(0, 0, 20, 20), [], null, [new Fragment($front, new Rect(0, 0, 20, 20), ['position' => 'absolute', 'z-index' => '2'], 'Front'), new Fragment($back, new Rect(0, 0, 20, 20), ['position' => 'absolute', 'z-index' => '1'], 'Back')]);
        $list = (new Painter())->paint($fragment);
        $result = new RenderingResult($document, new \SplObjectStorage(), $fragment, $list);
        self::assertSame(['Back', 'Front'], array_column($list->getOperations(), 'text'));
        self::assertSame($front, $result->hitTest(2, 2));
    }

    public function testOverflowClipsOnlySpecifiedAxes()
    {
        $document = \Dom\HTMLDocument::createFromString('<!DOCTYPE html><div><button>Click</button></div>');
        $parent = $document->querySelector('div');
        $button = $document->querySelector('button');
        $fragment = new Fragment($parent, new Rect(0, 0, 10, 10), ['overflow-x' => 'hidden', 'overflow-y' => 'visible'], null, [new Fragment($button, new Rect(0, 15, 5, 5))]);
        $list = (new Painter())->paint($fragment);
        self::assertTrue($list->getOperations()[0]['clip-x']);
        self::assertFalse($list->getOperations()[0]['clip-y']);
        $result = new RenderingResult($document, new \SplObjectStorage(), $fragment, $list);
        self::assertSame($button, $result->hitTest(2, 16));
    }

    public function testBoundsIndexRetainsEveryFragmentAndEmptyHiddenNodes()
    {
        $document = \Dom\HTMLDocument::createFromString('<!DOCTYPE html><main><span id="visible">Visible</span><span id="hidden">Hidden</span></main>');
        $visible = $document->querySelector('#visible');
        $hidden = $document->querySelector('#hidden');
        $first = new Rect(1.25, 2.5, 5.5, 10.0);
        $second = new Rect(1.25, 12.5, 3.5, 10.0);
        $fragment = new Fragment($document->querySelector('main'), new Rect(0, 0, 20, 30), [], null, [new Fragment($visible, $first), new Fragment($visible, $second)]);
        $result = new RenderingResult($document, new \SplObjectStorage(), $fragment, (new Painter())->paint($fragment));
        self::assertSame([], $result->getBounds($hidden));
        self::assertSame([$first, $second], $result->getBounds($visible));
        $visible->remove();
        self::assertSame([$first, $second], $result->getBounds($visible));
        self::assertSame([], $result->getBounds($document));
        self::assertSame([], $result->getBounds($hidden));
    }
}
