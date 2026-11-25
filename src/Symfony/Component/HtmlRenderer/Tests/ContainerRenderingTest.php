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

final class ContainerRenderingTest extends TestCase
{
    public function testNonConvergentQueriesUseTheBaseFrameDeterministically()
    {
        $session = (new HtmlRenderer())->createSession('<style>section{container-type:scroll-state;height:20px;overflow:auto}p{height:60px;margin:0}@container scroll-state(scrollable:bottom){p{height:10px;color:red}}</style><section><p>Text</p></section>', new RenderingContext(300, 200));
        $paragraph = $session->getDocument()->querySelector('p');
        $result = $session->render();
        self::assertNotSame('red', $result->getComputedStyle($paragraph)['color']);
        self::assertSame(60.0, $result->getBounds($paragraph)[0]->height);
        self::assertSame($result, $session->render());
    }

    public function testScrollDirectionQueriesFollowSemanticScrollUpdates()
    {
        $session = (new HtmlRenderer())->createSession('<style>section{container-type:scroll-state;height:20px;overflow:auto}p{height:60px;margin:0}@container scroll-state(scrolled:bottom){p{color:red}}</style><section><p>Text</p></section>', new RenderingContext(300, 200));
        $paragraph = $session->getDocument()->querySelector('p');
        $session->scroll($paragraph->parentElement, 0, 10);
        self::assertSame('red', $session->render()->getComputedStyle($paragraph)['color']);
        $session->scroll($paragraph->parentElement, 0, 0);
        self::assertNotSame('red', $session->render()->getComputedStyle($paragraph)['color']);
    }

    public function testContainerQueriesUseContentGeometryAndRecomputeAfterMutationAndResize()
    {
        $session = (new HtmlRenderer())->createSession('<style>section{container:card / inline-size;width:50%;padding:10px;border:2px solid} @container card (width > 100px){p{color:red}}</style><section><p>Text</p></section>', new RenderingContext(300, 200));
        $paragraph = $session->getDocument()->querySelector('p');
        self::assertSame('red', $session->render()->getComputedStyle($paragraph)['color']);
        $session->setContext($session->getContext()->withViewport(160, 200));
        self::assertNotSame('red', $session->render()->getComputedStyle($paragraph)['color']);
        $session->getDocument()->querySelector('section')->setAttribute('style', 'width:120.5px');
        self::assertSame('red', $session->render()->getComputedStyle($paragraph)['color']);
        self::assertSame($session->render(), $session->render());
    }

    public function testNestedStyleQueriesResolveWithoutChangingSemanticIdentity()
    {
        $session = (new HtmlRenderer())->createSession('<style>main{--theme:dark} @container style(--theme:dark){section{--accent:red}} @container style(--accent:red){p{color:var(--accent)}}</style><main><section><p>Text</p></section></main>', new RenderingContext(300, 200));
        $paragraph = $session->getDocument()->querySelector('p');
        self::assertSame('red', $session->render()->getComputedStyle($paragraph)['color']);
        self::assertSame($paragraph, $session->getDocument()->querySelector('p'));
    }

    public function testScrollStateQueriesUseActualClampedScrollableSides()
    {
        $session = (new HtmlRenderer())->createSession('<style>section{container-type:scroll-state;height:20px;overflow:auto}p{height:60px;margin:0}@container scroll-state(scrollable:bottom){p{color:red}}</style><section><p>Text</p></section>', new RenderingContext(300, 200));
        $paragraph = $session->getDocument()->querySelector('p');
        self::assertSame('red', $session->render()->getComputedStyle($paragraph)['color']);
        $session->scroll($paragraph->parentElement, 0, 999);
        self::assertNotSame('red', $session->render()->getComputedStyle($paragraph)['color']);
    }

    public function testIntermediateQueryPassesDoNotRestartAnimations()
    {
        $session = (new HtmlRenderer())->createSession('<style>section{container-type:inline-size;width:120px}@container(width>100px){p{animation:fade 2s linear forwards}}@keyframes fade{from{opacity:0}to{opacity:1}}</style><section><p>Text</p></section>', new RenderingContext(300, 200));
        $paragraph = $session->getDocument()->querySelector('p');
        self::assertSame('0', $session->render()->getComputedStyle($paragraph)['opacity']);
        $session->advance(1);
        self::assertSame('0.5', $session->render()->getComputedStyle($paragraph)['opacity']);
        $session->advance(1);
        self::assertSame('1', $session->render()->getComputedStyle($paragraph)['opacity']);
    }
}
