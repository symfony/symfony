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
use Symfony\Component\HtmlRenderer\HtmlRenderer;
use Symfony\Component\HtmlRenderer\RenderingContext;

final class InitialScrollTargetTest extends TestCase
{
    public function testInitialTargetIsRevealedOnceAndDoesNotOverrideLaterScrolling()
    {
        $session = new HtmlRenderer()->createSession('<style>main{height:20px;overflow:auto}p{margin:0;line-height:10px}.latest{scroll-initial-target:nearest}</style><main><p>First</p><p>Second</p><p>Third</p><p class="latest">Fourth</p></main>', new RenderingContext(200, 120));
        $main = $session->getDocument()->querySelector('main');
        $source = $session->getDocument()->saveHtml();
        $events = 0;
        $session->on('scroll', static function () use (&$events) { ++$events; });
        self::assertSame([0.0, 20.0], $session->getScroll($main));
        self::assertSame(1, $events);
        self::assertSame($source, $session->getDocument()->saveHtml());
        $session->scroll($main, 0, 0);
        $session->getDocument()->querySelector('.latest')->textContent = 'Fourth changed';
        self::assertSame([0.0, 0.0], $session->getScroll($main));
        self::assertSame(2, $events);
    }

    public function testComputedCascadeChoosesFirstVisibleTargetAndHonorsNone()
    {
        $session = new HtmlRenderer()->createSession('<style>main{height:10px;overflow:auto}p{margin:0;line-height:10px;scroll-initial-target:nearest}p:first-child{scroll-initial-target:none}.hidden{display:none}</style><main><p>First</p><p class="hidden">Hidden</p><p>Second</p><p>Third</p></main>', new RenderingContext(200, 120));
        self::assertSame([0.0, 10.0], $session->getScroll($session->getDocument()->querySelector('main')));
    }

    public function testTargetsAddedAfterInitialLayoutDoNotResetScroll()
    {
        $session = new HtmlRenderer()->createSession('<main style="height:10px;overflow:auto"><p style="margin:0;line-height:10px">First</p><p style="margin:0;line-height:10px">Second</p></main>', new RenderingContext(200, 120));
        $main = $session->getDocument()->querySelector('main');
        self::assertSame([0.0, 0.0], $session->getScroll($main));
        $session->getDocument()->querySelector('p:last-child')->setAttribute('style', 'margin:0;line-height:10px;scroll-initial-target:nearest');
        self::assertSame([0.0, 0.0], $session->getScroll($main));
    }
}
