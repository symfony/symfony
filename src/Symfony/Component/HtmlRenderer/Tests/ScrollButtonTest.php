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

class ScrollButtonTest extends TestCase
{
    public function testGeneratedButtonsReserveTheirMeasuredHeightInsideTheScrollContainer()
    {
        $session = new HtmlRenderer()->createSession('<style>div{width:160px;height:64px;line-height:16px;overflow:auto}div::scroll-button(up){content:"Up"}div::scroll-button(down){content:"Down"}</style><div id="s">one<br>two<br>three<br>four<br>five<br>six</div>', new RenderingContext(320, 240));
        $node = $session->getDocument()->getElementById('s');
        $result = $session->render();
        self::assertSame(64.0, $result->getBounds($node)[0]->height);
        self::assertSame(32.0, $result->getScrollGeometry($node)->port->height);
        self::assertSame(0.0, $result->getBounds($node, 'scroll-button(up)')[0]->y);
        self::assertSame(48.0, $result->getBounds($node, 'scroll-button(down)')[0]->y);
        self::assertSame('block', $result->getComputedStyle($node)['display']);
    }

    public function testAutoHeightAndAuthoredChromeRetainTheirOuterDimensions()
    {
        foreach (['' => [128.0, 96.0], 'height:96px;border:2px solid;padding:4px' => [108.0, 64.0], 'display:inline-block;height:64px' => [64.0, 32.0]] as $style => [$outer, $port]) {
            [$result, $node] = $this->render($style, 'one<br>two<br>three<br>four<br>five<br>six');
            self::assertSame($outer, $result->getBounds($node)[0]->height, $style);
            self::assertSame($port, $result->getScrollGeometry($node)->port->height, $style);
        }
    }

    public function testTheContentKeepsAuthoredFlexGridAndPercentageContainingBlocks()
    {
        foreach (['display:flex;flex-direction:row;column-gap:8px', 'display:grid;grid-template-columns:1fr 1fr;column-gap:8px'] as $style) {
            [$result, $node] = $this->render('height:64px;'.$style, '<span id="first" style="width:50%;height:100%">A</span><span id="second">B</span>');
            $first = $result->getDocument()->getElementById('first');
            $second = $result->getDocument()->getElementById('second');
            self::assertSame(32.0, $result->getScrollGeometry($node)->port->height, $style);
            self::assertSame(32.0, $result->getBounds($first)[0]->height, $style);
            self::assertGreaterThan($result->getBounds($first)[0]->x, $result->getBounds($second)[0]->x, $style);
            self::assertSame(str_starts_with($style, 'display:flex') ? 'flex' : 'grid', $result->getComputedStyle($node)['display']);
        }
    }

    public function testDirectionalDisabledStateUsesTheActualPortWithoutDisablingTheOrigin()
    {
        $session = new HtmlRenderer()->createSession('<style>#s{width:160px;height:64px;line-height:16px;overflow:auto}#s::scroll-button(*){content:"Go"}#s::scroll-button(*):disabled{color:red}#s::scroll-button(*):enabled{color:green}</style><div id="s">one<br>two<br>three<br>four<br>five<br>six</div>', new RenderingContext(320, 240));
        $node = $session->getDocument()->getElementById('s');
        $result = $session->render();
        $colors = [];
        $walk = static function ($fragment) use (&$walk, &$colors): void {
            if (null !== $fragment->pseudoElement && str_starts_with($fragment->pseudoElement, 'scroll-button(')) {
                $colors[$fragment->pseudoElement] = $fragment->style['color'] ?? null;
            }
            foreach ($fragment->children as $child) {
                $walk($child);
            }
        };
        $walk($result->getLayout());
        self::assertSame('red', $colors['scroll-button(up)']);
        self::assertSame('green', $colors['scroll-button(down)']);
        self::assertFalse($node->hasAttribute('disabled'));
        self::assertStringNotContainsString('scroll-button-', $session->getDocument()->saveHtml());
    }

    public function testPointerButtonsScrollAnExplicitFloatingPointPageAndDoNotActivateTheOwner()
    {
        $session = new HtmlRenderer()->createSession('<style>#s{display:block;width:160px;height:64px;line-height:16px;overflow:auto}#s::scroll-button(up){content:"Up"}#s::scroll-button(down){content:"Down"}</style><a id="s" href="#target">one<br>two<br>three<br>four<br>five<br>six</a><p id="target">Target</p>', new RenderingContext(320, 240));
        $node = $session->getDocument()->getElementById('s');
        $hit = $session->render()->getScrollControlAt(8, 56);
        self::assertSame($node, $hit['element']);
        self::assertSame('down', $hit['action']);
        self::assertFalse($hit['disabled']);
        $session->handlePointer(new \Symfony\Component\HtmlRenderer\Event\PointerEvent('up', 8, 56));
        self::assertSame(0.0, $session->render()->getScrollGeometry($node)->offsetY);
        $session->handlePointer(new \Symfony\Component\HtmlRenderer\Event\PointerEvent('down', 8, 56));
        $session->handlePointer(new \Symfony\Component\HtmlRenderer\Event\PointerEvent('up', 8, 56));
        self::assertEqualsWithDelta(27.2, $session->render()->getScrollGeometry($node)->offsetY, .0001);
        self::assertNull($session->getFocusedElement());
        $session->pointer(8, 56, true);
        self::assertEqualsWithDelta(54.4, $session->render()->getScrollGeometry($node)->offsetY, .0001);
    }

    public function testCanceledAndRemovedControlOriginsNeverApplyTheDefaultScroll()
    {
        foreach ([false, true] as $remove) {
            $session = new HtmlRenderer()->createSession('<style>#s{width:160px;height:64px;line-height:16px;overflow:auto}#s::scroll-button(down){content:"Down"}</style><div id="s">one<br>two<br>three<br>four<br>five<br>six</div>', new RenderingContext(320, 240));
            $node = $session->getDocument()->getElementById('s');
            $session->on('click', static function ($event) use ($remove, $node): void {
                if ($remove) {
                    $node->remove();
                } else {
                    $event->preventDefault();
                }
            }, $node);
            $session->pointer(8, 56, true);
            self::assertSame($remove, !$node->isConnected);
            self::assertNull($session->getFocusedElement());
            if (!$remove) {
                self::assertSame(0.0, $session->render()->getScrollGeometry($node)->offsetY);
            }
        }
    }

    public function testNonBoxAndNonReplacedInlineOriginsGenerateNoScrollControls()
    {
        foreach (['inline', 'contents'] as $display) {
            [$result, $node] = $this->render('display:'.$display, 'Text');
            self::assertSame([], $result->getBounds($node, 'scroll-button(up)'));
            self::assertSame([], $result->getBounds($node, 'scroll-button(down)'));
        }
    }

    private function render(string $style, string $content): array
    {
        $session = new HtmlRenderer()->createSession('<style>#s{width:160px;line-height:16px;overflow:auto;'.$style.'}#s::scroll-button(up){content:"Up"}#s::scroll-button(down){content:"Down"}</style><div id="s">'.$content.'</div>', new RenderingContext(320, 240));

        return [$session->render(), $session->getDocument()->getElementById('s')];
    }
}
