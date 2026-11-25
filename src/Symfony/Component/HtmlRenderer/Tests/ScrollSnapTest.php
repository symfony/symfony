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

class ScrollSnapTest extends TestCase
{
    public function testRepeatedSmallScrollsUseTheActualCommittedSnapOffset(): void
    {
        $session = new HtmlRenderer()->createSession('<style>#s{width:160px;height:48px;overflow:auto}section{height:48px;scroll-snap-align:start}#s{scroll-snap-type:y mandatory}</style><div id="s"><section>one</section><section>two</section><section>three</section><section>four</section></div>', new RenderingContext(320, 240));
        $owner = $session->getDocument()->getElementById('s');
        self::assertSame([0.0, 0.0], $session->getScroll($owner));
        $session->scroll($owner, 0, 1);
        self::assertSame([0.0, 48.0], $session->getScroll($owner));
        $session->handleWheel(new WheelEvent(8, 8, 0, 1));
        self::assertSame([0.0, 96.0], $session->getScroll($owner));
        $session->handleWheel(new WheelEvent(8, 8, 0, -1));
        self::assertSame([0.0, 48.0], $session->getScroll($owner));
    }

    public function testChosenTargetsDriveContainerStateAndClearingTheAxisRemovesIt(): void
    {
        $session = new HtmlRenderer()->createSession('<style>#s{width:160px;height:48px;overflow:auto;scroll-snap-type:y mandatory}section{height:48px;scroll-snap-align:start;container-type:scroll-state}@container scroll-state(snapped:y){span{color:green}}</style><div id="s"><section><span id="a">one</span></section><section><span id="b">two</span></section><section>three</section><section>four</section></div>', new RenderingContext(320, 240));
        $document = $session->getDocument();
        $owner = $document->getElementById('s');
        $a = $document->getElementById('a');
        $b = $document->getElementById('b');
        self::assertSame('green', $session->render()->getComputedStyle($a)['color']);
        $session->scroll($owner, 0, 1);
        $result = $session->render();
        self::assertSame('green', $result->getComputedStyle($b)['color']);
        self::assertNotSame('green', $result->getComputedStyle($a)['color']);
        self::assertSame([$b->parentElement], $result->getScrollGeometry($owner)->snapped['y']);
        $owner->setAttribute('style', 'scroll-snap-type:none');
        $result = $session->render();
        self::assertSame([], $result->getScrollGeometry($owner)->snapped);
        self::assertNotSame('green', $result->getComputedStyle($b)['color']);
    }

    public function testBothAxesRetainTheirOwnTargetsAndNestedPortsOwnTheirDescendants(): void
    {
        $session = new HtmlRenderer()->createSession('<style>#s{display:grid;grid-template-columns:100px 100px;grid-auto-rows:100px;width:50px;height:50px;overflow:auto;scroll-snap-type:both mandatory}section{scroll-snap-align:start}#n{width:25px;height:25px;overflow:auto;scroll-snap-type:y mandatory}p{margin:0;height:50px;scroll-snap-align:start}</style><div id="s"><section id="a"><div id="n"><p id="inner">nested</p></div></section><section id="b">two</section><section id="c">three</section><section id="d">four</section></div>', new RenderingContext(320, 240));
        $document = $session->getDocument();
        $owner = $document->getElementById('s');
        $result = $session->render();
        $geometry = $result->getScrollGeometry($owner);
        self::assertSame([$document->getElementById('a'), $document->getElementById('c')], $geometry->snapped['x']);
        self::assertSame([$document->getElementById('a'), $document->getElementById('b')], $geometry->snapped['y']);
        self::assertSame([$document->getElementById('inner')], $result->getScrollGeometry($document->getElementById('n'))->snapped['y']);
        self::assertSame($geometry->snapped, $geometry->moved(5.25, 7.5)->snapped);
        $session->scroll($owner, 1, 1);
        $geometry = $session->render()->getScrollGeometry($owner);
        self::assertSame([100.0, 100.0], [$geometry->offsetX, $geometry->offsetY]);
        self::assertSame([$document->getElementById('b'), $document->getElementById('d')], $geometry->snapped['x']);
        self::assertSame([$document->getElementById('c'), $document->getElementById('d')], $geometry->snapped['y']);
    }

    public function testResolvedPaddingMarginsStopsAndProximityUseTheSameCandidates(): void
    {
        $html = '<style>#s{width:160px;height:48px;overflow:auto;scroll-snap-type:y mandatory;scroll-padding-top:4px}section{height:48px;scroll-snap-align:start;scroll-margin-top:2px}#b{scroll-snap-stop:always}</style><div id="s"><section>one</section><section id="b">two</section><section>three</section><section>four</section></div>';
        $session = new HtmlRenderer()->createSession($html, new RenderingContext(320, 240));
        $owner = $session->getDocument()->getElementById('s');
        $session->scroll($owner, 0, 130);
        self::assertSame([0.0, 42.0], $session->getScroll($owner));
        $owner->setAttribute('style', 'scroll-snap-type:y proximity');
        $session->scroll($owner, 0, 18);
        self::assertSame([0.0, 18.0], $session->getScroll($owner));
        $session->scroll($owner, 0, 26);
        self::assertSame([0.0, 42.0], $session->getScroll($owner));
    }

    public function testRemovedOwnersAndAxesWithoutOverflowDoNotRetainSnapHistory(): void
    {
        $session = new HtmlRenderer()->createSession('<style>#s{width:160px;height:48.5px;overflow:auto;scroll-snap-type:y mandatory}section{height:48.5px;scroll-snap-align:start}</style><div id="s"><section>one</section><section>two</section><section>three</section></div>', new RenderingContext(320, 240));
        $document = $session->getDocument();
        $owner = $document->getElementById('s');
        $session->scroll($owner, 0, 1);
        self::assertSame([0.0, 48.5], $session->getScroll($owner));
        $owner->setAttribute('style', 'height:200px');
        self::assertSame([0.0, 0.0], $session->getScroll($owner));
        $owner->remove();
        $session->render();
        $document->body->append($owner);
        $owner->removeAttribute('style');
        self::assertSame([0.0, 0.0], $session->getScroll($owner));
        $session->scroll($owner, 0, 1);
        self::assertSame([0.0, 48.5], $session->getScroll($owner));
    }
}
