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
use Symfony\Component\HtmlRenderer\Event\PointerEvent;
use Symfony\Component\HtmlRenderer\HtmlRenderer;
use Symfony\Component\HtmlRenderer\RenderingContext;

final class NavigationTest extends TestCase
{
    public function testPointerReleaseWithinSameLinkActivatesAcrossInlineDescendants()
    {
        $session = (new HtmlRenderer())->createSession('<a href="#p"><b>A</b><span>B</span></a><p id="p">Target</p>', new RenderingContext(300, 200));
        $document = $session->getDocument();
        $first = $session->render()->getBounds($document->querySelector('b'))[0];
        $second = $session->render()->getBounds($document->querySelector('span'))[0];
        $session->handlePointer(new PointerEvent('down', $first->x + 1, $first->y + 1));
        $session->handlePointer(new PointerEvent('up', $second->x + 1, $second->y + 1));
        self::assertSame($document->getElementById('p'), $session->getTarget());
    }

    public function testLinkDefaultActionUsesAncestorButClickKeepsPhysicalTarget()
    {
        $session = (new HtmlRenderer())->createSession('<a href="#p"><strong>Go</strong></a><p id="p">Target</p>', new RenderingContext(300, 200));
        $strong = $session->getDocument()->querySelector('strong');
        $targets = [];
        $session->on('click', static function ($event) use (&$targets) { $targets[] = $event->target; });
        $session->activate($strong);
        self::assertSame([$strong], $targets);
        self::assertSame($session->getDocument()->getElementById('p'), $session->getTarget());
    }

    public function testFragmentActivationSetsTargetAndRevealsItWithoutMutatingSource()
    {
        $session = (new HtmlRenderer())->createSession('<style>:target{color:red}</style><a href="#r%C3%A9sultat">Go</a><section style="height:20px;overflow:auto;font-size:10px;line-height:10px"><p style="margin:0;height:60px"></p><button id="résultat" style="height:10px;min-height:0;line-height:10px;border:0;padding:0">Result</button></section>', new RenderingContext(300, 200));
        $document = $session->getDocument();
        $source = $document->saveHtml();
        $session->activate($document->querySelector('a'));
        $target = $document->getElementById('résultat');
        self::assertSame($target, $session->getTarget());
        self::assertSame($target, $session->getFocusedElement());
        self::assertSame('red', $session->render()->getComputedStyle($target)['color']);
        self::assertSame([0.0, 50.0], $session->getScroll($target->parentElement));
        self::assertSame($source, $document->saveHtml());
        self::assertFalse($session->navigate('#missing'));
        self::assertNull($session->getTarget());
    }

    public function testExternalLinksDispatchSemanticEventsAndVisitedStateIsExplicit()
    {
        $session = (new HtmlRenderer())->createSession('<style>a:visited{color:red}a:link{color:blue}</style><a href="https://example.test/report">Report</a>', new RenderingContext(300, 200));
        $link = $session->getDocument()->querySelector('a');
        $events = [];
        $remove = $session->on('link', static function ($event) use (&$events) {
            $events[] = $event->data['href'];
            $event->preventDefault();
        });
        $session->activate($link);
        self::assertSame(['https://example.test/report'], $events);
        self::assertSame([], $session->getVisitedLinks());
        self::assertSame('blue', $session->render()->getComputedStyle($link)['color']);
        $remove();
        $session->activate($link);
        self::assertSame(['https://example.test/report'], $session->getVisitedLinks());
        self::assertSame('red', $session->render()->getComputedStyle($link)['color']);
        $session->setVisitedLinks([]);
        self::assertSame('blue', $session->render()->getComputedStyle($link)['color']);
        $session->setVisitedLinks(['123']);
        self::assertSame(['123'], $session->getVisitedLinks());
    }

    public function testPreventedClickDoesNotNavigateOrMarkVisited()
    {
        $session = (new HtmlRenderer())->createSession('<a href="#p">Go</a><p id="p">Target</p>', new RenderingContext(300, 200));
        $session->on('click', static fn ($event) => $event->preventDefault());
        $session->activate($session->getDocument()->querySelector('a'));
        self::assertNull($session->getTarget());
        self::assertSame([], $session->getVisitedLinks());
    }
}
