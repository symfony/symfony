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
use Symfony\Component\HtmlRenderer\Event\WheelEvent;
use Symfony\Component\HtmlRenderer\HtmlRenderer;
use Symfony\Component\HtmlRenderer\RenderingContext;

final class WheelScrollingTest extends TestCase
{
    public function testWheelUsesTheNearestScrollableAncestorThenChainsAtItsBoundary()
    {
        $session = $this->nested();
        $inner = $session->getDocument()->getElementById('inner');
        $outer = $session->getDocument()->getElementById('outer');
        self::assertTrue($session->handleWheel(new WheelEvent(4, 4, 0, 16)));
        self::assertSame([0.0, 16.0], $session->getScroll($inner));
        self::assertSame([0.0, 0.0], $session->getScroll($outer));
        self::assertTrue($session->handleWheel(new WheelEvent(4, 4, 0, 1000)));
        self::assertSame([0.0, 64.0], $session->getScroll($inner));
        self::assertSame([0.0, 0.0], $session->getScroll($outer));
        self::assertTrue($session->handleWheel(new WheelEvent(4, 4, 0, 16)));
        self::assertSame([0.0, 16.0], $session->getScroll($outer));
        self::assertFalse($session->handleWheel(new WheelEvent(4, 400, 0, 16)));
        $before = $session->render();
        self::assertFalse($session->handleWheel(new WheelEvent(4, 4, 0, 0)));
        self::assertSame($before, $session->render());
    }

    public function testWheelBubblesAndCancellationOrRemovalPreventsDefaultScrolling()
    {
        $session = $this->nested();
        $inner = $session->getDocument()->getElementById('inner');
        $events = [];
        $stop = $session->on('wheel', static function ($event) use (&$events): void {
            $events[] = 'inner';
            $event->stopPropagation();
        }, $inner);
        $session->on('wheel', static function () use (&$events): void { $events[] = 'document'; });
        self::assertTrue($session->handleWheel(new WheelEvent(4, 4, 0, 16)));
        self::assertSame(['inner'], $events);
        $stop();
        $cancel = $session->on('wheel', static fn ($event) => $event->preventDefault(), $inner);
        self::assertFalse($session->handleWheel(new WheelEvent(4, 4, 0, 16)));
        self::assertSame([0.0, 16.0], $session->getScroll($inner));
        $cancel();
        $session->on('wheel', static fn () => $inner->remove(), $inner);
        self::assertFalse($session->handleWheel(new WheelEvent(4, 4, 0, 16)));
    }

    public function testContainmentStopsOnlyItsAxisAndHiddenOverflowDoesNotTakeWheelInput()
    {
        $session = $this->nested('overscroll-behavior: auto contain');
        $inner = $session->getDocument()->getElementById('inner');
        $outer = $session->getDocument()->getElementById('outer');
        $session->scroll($inner, 0, 64);
        self::assertFalse($session->handleWheel(new WheelEvent(4, 4, 0, 16)));
        self::assertSame([0.0, 0.0], $session->getScroll($outer));
        $inner->setAttribute('style', 'height:32px;overflow:hidden');
        self::assertTrue($session->handleWheel(new WheelEvent(4, 4, 0, 16)));
        self::assertSame([0.0, 16.0], $session->getScroll($outer));
    }

    public function testWheelRoutesIntoChildViewportWithoutBubblingIntoItsParentDocument()
    {
        $inner = '<div style="height:200px">Inside</div>';
        $session = new HtmlRenderer()->createSession('<iframe style="position:absolute;left:20px;top:30px;width:100px;height:40px;border:0;transform:translate(5px,6px)" srcdoc="'.htmlspecialchars($inner).'"></iframe>', new RenderingContext(300, 200));
        $child = $session->getEmbeddedSession($session->getDocument()->querySelector('iframe'));
        $outerEvents = 0;
        $innerEvents = 0;
        $session->on('wheel', static function () use (&$outerEvents): void { ++$outerEvents; });
        $child->on('wheel', static function () use (&$innerEvents): void { ++$innerEvents; });
        self::assertTrue($session->handleWheel(new WheelEvent(30, 40, 0, 16)));
        self::assertSame([0.0, 16.0], $child->getScroll($child->getDocument()->documentElement));
        self::assertSame(0, $outerEvents);
        self::assertSame(1, $innerEvents);
    }

    public function testAxesChainIndependentlyWhenOneAxisIsContained()
    {
        $session = new HtmlRenderer()->createSession('<div id="outer" style="width:64px;height:64px;overflow:auto"><div id="inner" style="height:32px;overflow:auto;overscroll-behavior-x:contain"><div style="width:200px;height:96px">Rows</div></div><div style="width:200px;height:200px">Tail</div></div>', new RenderingContext(300, 200));
        $inner = $session->getDocument()->getElementById('inner');
        $outer = $session->getDocument()->getElementById('outer');
        $geometry = $session->render()->getScrollGeometry($inner);
        $session->scroll($inner, $geometry->maxX, $geometry->maxY);
        self::assertTrue($session->handleWheel(new WheelEvent(4, 4, 16, 16)));
        self::assertSame([0.0, 16.0], $session->getScroll($outer));
        self::assertSame([$geometry->maxX, $geometry->maxY], $session->getScroll($inner));
    }

    public function testChildBoundariesChainIntoTheParentUnlessTheChildContainsOverscroll()
    {
        foreach ([false, true] as $contained) {
            $inner = ($contained ? '<style>html{overscroll-behavior:contain}</style>' : '').'<div style="height:120px">Inside</div>';
            $session = new HtmlRenderer()->createSession('<div id="outer" style="height:80px;overflow:auto"><iframe style="display:block;width:100px;height:40px;border:0" srcdoc="'.htmlspecialchars($inner).'"></iframe><div style="height:200px">Tail</div></div>', new RenderingContext(300, 200));
            $child = $session->getEmbeddedSession($session->getDocument()->querySelector('iframe'));
            $root = $child->getDocument()->documentElement;
            $child->scroll($root, 0, 80);
            self::assertSame(!$contained, $session->handleWheel(new WheelEvent(4, 4, 0, 16)));
            self::assertSame([0.0, $contained ? 0.0 : 16.0], $session->getScroll($session->getDocument()->getElementById('outer')));
        }
    }

    public function testNonFiniteDeltasAreRejectedBeforeAnyDocumentInput()
    {
        $this->expectException(\InvalidArgumentException::class);
        new WheelEvent(0, 0, \INF, 0);
    }

    private function nested(string $extra = '')
    {
        return new HtmlRenderer()->createSession('<div id="outer" style="width:100px;height:64px;overflow:auto"><div id="inner" style="height:32px;overflow:auto;'.$extra.'"><div style="height:96px">Rows</div></div><div style="height:160px">Tail</div></div>', new RenderingContext(300, 200));
    }
}
