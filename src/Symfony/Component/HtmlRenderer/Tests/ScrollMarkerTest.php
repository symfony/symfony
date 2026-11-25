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

class ScrollMarkerTest extends TestCase
{
    public function testTheGeneratedGroupReservesItsHeightAndRetainsNativeTargets(): void
    {
        foreach (['before', 'after'] as $position) {
            $session = new HtmlRenderer()->createSession('<style>#s{width:160px;height:64px;line-height:16px;overflow:auto;scroll-marker-group:'.$position.'}p{margin:0}p::scroll-marker{content:attr(data-label)}</style><div id="s"><p id="a" data-label="A">one<br>two<br>three</p><p id="b" data-label="B">four<br>five<br>six</p></div>', new RenderingContext(320, 240));
            $document = $session->getDocument();
            $source = $document->saveHtml();
            $owner = $document->getElementById('s');
            $result = $session->render();
            self::assertSame(48.0, $result->getScrollGeometry($owner)->port->height);
            self::assertSame('before' === $position ? 0.0 : 48.0, $result->getBounds($owner, 'scroll-marker-group')[0]->y);
            self::assertNotEmpty($result->getBounds($document->getElementById('a'), 'scroll-marker'));
            self::assertNotEmpty($result->getBounds($document->getElementById('b'), 'scroll-marker'));
            self::assertSame($source, $document->saveHtml());
        }
    }

    public function testReorderingAndRemovingTargetsRebuildsTheGroupWithoutMovingSemanticNodes(): void
    {
        $session = new HtmlRenderer()->createSession('<style>#s{height:64px;line-height:16px;overflow:auto;scroll-marker-group:before}p{margin:0}p::scroll-marker{content:attr(data-label)}</style><div id="s"><p id="a" data-label="A">one</p><p id="b" data-label="B">two</p></div>', new RenderingContext(320, 240));
        $document = $session->getDocument();
        $owner = $document->getElementById('s');
        $a = $document->getElementById('a');
        $b = $document->getElementById('b');
        $result = $session->render();
        self::assertLessThan($result->getBounds($b, 'scroll-marker')[0]->x, $result->getBounds($a, 'scroll-marker')[0]->x);
        $owner->prepend($b);
        $result = $session->render();
        self::assertLessThan($result->getBounds($a, 'scroll-marker')[0]->x, $result->getBounds($b, 'scroll-marker')[0]->x);
        self::assertSame($b, $owner->firstElementChild);
        $a->remove();
        $result = $session->render();
        self::assertSame([], $result->getBounds($a, 'scroll-marker'));
        self::assertNotEmpty($result->getBounds($b, 'scroll-marker'));
        $b->remove();
        self::assertSame([], $session->render()->getBounds($owner, 'scroll-marker-group'));
    }

    public function testCurrentMarkerTracksActualScrollGeometry(): void
    {
        $session = new HtmlRenderer()->createSession('<style>#s{width:160px;height:64px;line-height:16px;overflow:auto;scroll-marker-group:after}p{margin:0}p::scroll-marker{content:attr(data-label);color:red}p::scroll-marker:target-current{color:green}</style><div id="s"><p id="a" data-label="A">one<br>two<br>three</p><p id="b" data-label="B">four<br>five<br>six</p></div>', new RenderingContext(320, 240));
        $document = $session->getDocument();
        $owner = $document->getElementById('s');
        $color = static function ($result, $target): string {
            $walk = static function ($fragment) use (&$walk, $target): ?string {
                if ($fragment->node === $target && 'scroll-marker' === $fragment->pseudoElement) {
                    return $fragment->style['color'] ?? null;
                }
                foreach ($fragment->children as $child) {
                    if (null !== $value = $walk($child)) {
                        return $value;
                    }
                }

                return null;
            };

            return $walk($result->getLayout());
        };
        $result = $session->render();
        self::assertSame('green', $color($result, $document->getElementById('a')));
        self::assertSame('red', $color($result, $document->getElementById('b')));
        $session->scroll($owner, 0, 48);
        $result = $session->render();
        self::assertSame('red', $color($result, $document->getElementById('a')));
        self::assertSame('green', $color($result, $document->getElementById('b')));
    }

    public function testPointerMarkersRevealTheirTargetWithoutFollowingItsLinkOrMovingFocus(): void
    {
        $session = new HtmlRenderer()->createSession('<style>#s{width:160px;height:64px;line-height:16px;overflow:auto;scroll-marker-group:after}a{display:block}a::scroll-marker{content:attr(data-label)}</style><div id="s"><a id="a" href="#destination" data-label="A">one<br>two<br>three</a><a id="b" href="#destination" data-label="B">four<br>five<br>six</a></div><p id="destination">Destination</p>', new RenderingContext(320, 240));
        $document = $session->getDocument();
        $owner = $document->getElementById('s');
        $target = $document->getElementById('b');
        $result = $session->render();
        $bounds = $result->getBounds($target, 'scroll-marker')[0];
        $control = $result->getScrollControlAt($bounds->x + 1, $bounds->y + 1);
        self::assertSame($owner, $control['element']);
        self::assertSame($target, $control['target']);
        self::assertSame('marker', $control['action']);
        $session->pointer($bounds->x + 1, $bounds->y + 1, true);
        self::assertSame(48.0, $session->render()->getScrollGeometry($owner)->offsetY);
        self::assertNull($session->getFocusedElement());
    }

    public function testCanceledOrRemovedMarkerTargetsNeverApplyTheDefaultScroll(): void
    {
        foreach ([false, true] as $remove) {
            $session = new HtmlRenderer()->createSession('<style>#s{width:160px;height:64px;line-height:16px;overflow:auto;scroll-marker-group:after}p{margin:0}p::scroll-marker{content:attr(data-label)}</style><div id="s"><p data-label="A">one<br>two<br>three</p><p id="b" data-label="B">four<br>five<br>six</p></div>', new RenderingContext(320, 240));
            $document = $session->getDocument();
            $owner = $document->getElementById('s');
            $target = $document->getElementById('b');
            $bounds = $session->render()->getBounds($target, 'scroll-marker')[0];
            $session->on('click', static function ($event) use ($remove, $target): void {
                if ($remove) {
                    $target->remove();
                } else {
                    $event->preventDefault();
                }
            }, $target);
            $session->pointer($bounds->x + 1, $bounds->y + 1, true);
            self::assertSame(0.0, $session->render()->getScrollGeometry($owner)->offsetY);
        }
    }
}
