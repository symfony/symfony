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
use Symfony\Component\HtmlRenderer\Event\KeyboardEvent;
use Symfony\Component\HtmlRenderer\Event\PointerEvent;
use Symfony\Component\HtmlRenderer\HtmlRenderer;
use Symfony\Component\HtmlRenderer\RenderingContext;

final class EmbeddedInteractionTest extends TestCase
{
    public function testTabTraversesIndependentDocumentsAndChildKeysDispatchOnlyOnce()
    {
        $inner = '<input id="a"><input id="b">';
        $session = new HtmlRenderer()->createSession('<input id="before"><iframe srcdoc="'.htmlspecialchars($inner).'"></iframe><input id="after">', new RenderingContext(800, 500));
        $frame = $session->getDocument()->querySelector('iframe');
        $child = $session->getEmbeddedSession($frame);
        $keydown = 0;
        $child->on('keydown', static function () use (&$keydown): void { ++$keydown; });
        self::assertTrue($session->tab(false, false));
        self::assertSame('before', $session->getFocusedElement()->id);
        self::assertTrue($session->tab(false, false));
        self::assertSame($frame, $session->getFocusedElement());
        self::assertSame('a', $child->getFocusedElement()->id);
        self::assertSame($child, $session->getFocusedSession());
        $session->key(new KeyboardEvent('Unidentified', '中'));
        self::assertSame('中', $child->getValue($child->getDocument()->getElementById('a')));
        self::assertSame(1, $keydown);
        self::assertTrue($session->tab(false, false));
        self::assertSame('b', $child->getFocusedElement()->id);
        self::assertTrue($session->tab(false, false));
        self::assertSame('after', $session->getFocusedElement()->id);
        self::assertNull($child->getFocusedElement());
        self::assertTrue($session->tab(true, false));
        self::assertSame('b', $child->getFocusedElement()->id);
        $child->on('keydown', static fn ($event) => $event->preventDefault());
        self::assertTrue($session->tab(false, false));
        self::assertSame('b', $child->getFocusedElement()->id);
    }

    public function testPointerTranslationCaptureAndSubmissionStayInChildDocument()
    {
        $inner = '<form><input name="name" value="ABCD"><button>Send</button></form>';
        $session = new HtmlRenderer()->createSession('<iframe style="position:absolute;left:30px;top:40px;width:200px;height:100px;border:0;transform:translate(5px,6px)" srcdoc="'.htmlspecialchars($inner).'"></iframe>', new RenderingContext(800, 500));
        $frame = $session->getDocument()->querySelector('iframe');
        $child = $session->getEmbeddedSession($frame);
        $session->handlePointer(new PointerEvent('down', 47, 54));
        $session->handlePointer(new PointerEvent('up', 47, 54));
        self::assertSame($frame, $session->getFocusedElement());
        self::assertSame('input', $child->getFocusedElement()->localName);
        $session->key(new KeyboardEvent('Unidentified', 'X'));
        self::assertSame('AXBCD', $child->getValue($child->getFocusedElement()));
        $submissions = 0;
        $child->on('submit', static function () use (&$submissions): void { ++$submissions; });
        $submission = $session->key(new KeyboardEvent('Enter'));
        self::assertNotNull($submission);
        self::assertSame(1, $submissions);
        self::assertSame($child->getDocument(), $submission->form->ownerDocument);
        $session->handlePointer(new PointerEvent('down', 35, 54));
        $session->handlePointer(new PointerEvent('move', 500, 54));
        $session->handlePointer(new PointerEvent('up', 500, 54));
        self::assertSame([0, 5], $child->getTextSelection($child->getFocusedElement()));
    }

    public function testExplicitTimeAdvancesChildAnimationsWithoutOwningAClock()
    {
        $inner = '<style>@keyframes fade{from{opacity:0}to{opacity:1}}p{animation:fade 2s linear}</style><p>Animated</p>';
        $session = new HtmlRenderer()->createSession('<iframe srcdoc="'.htmlspecialchars($inner).'"></iframe>', new RenderingContext(800, 500));
        $frame = $session->getDocument()->querySelector('iframe');
        $child = $session->getEmbeddedSession($frame);
        $p = $child->getDocument()->querySelector('p');
        self::assertTrue($session->isAnimating());
        $session->advance(1);
        $session->render();
        self::assertSame('0.5', $child->render()->getComputedStyle($p)['opacity']);
        $session->advance(1);
        $session->render();
        self::assertFalse($session->isAnimating());
    }

    public function testRemovedHostDuringChildCallbacksStopsDefaultActionsAndSubmission()
    {
        $session = new HtmlRenderer()->createSession('<iframe srcdoc="&lt;form&gt;&lt;input value=Before&gt;&lt;button&gt;Submit&lt;/button&gt;&lt;/form&gt;"></iframe>', new RenderingContext(800, 500));
        $frame = $session->getDocument()->querySelector('iframe');
        $child = $session->getEmbeddedSession($frame);
        $session->focusNext();
        $input = $child->getDocument()->querySelector('input');
        $unsubscribe = $child->on('keydown', static fn () => $frame->remove());
        $session->key(new KeyboardEvent('Unidentified', 'X'));
        self::assertSame('Before', $child->getValue($input));
        self::assertNull($session->getFocusedElement());
        $unsubscribe();

        $session->getDocument()->body->appendChild($frame);
        $session->render();
        $child = $session->getEmbeddedSession($frame);
        $session->focusNext();
        $child->on('submit', static fn () => $frame->remove());
        self::assertNull($session->key(new KeyboardEvent('Enter')));
    }

    public function testParentModalKeepsNestedFocusInsideItsOwnHost()
    {
        $inner = '<iframe srcdoc="&lt;input&gt;"></iframe>';
        $session = new HtmlRenderer()->createSession('<button>Outside</button><dialog><iframe srcdoc="'.htmlspecialchars($inner).'"></iframe></dialog>', new RenderingContext(800, 500));
        $dialog = $session->getDocument()->querySelector('dialog');
        $session->showModal($dialog);
        $frame = $dialog->querySelector('iframe');
        $session->focus($frame);
        $session->tab();
        self::assertTrue($dialog->contains($session->getFocusedElement()));
        $leaf = $session->getFocusedSession();
        self::assertNotSame($session, $leaf);
        self::assertSame('input', $leaf->getFocusedElement()->localName);
        $session->tab(false, false);
        self::assertTrue($dialog->contains($session->getFocusedElement()));
        self::assertSame('input', $session->getFocusedSession()->getFocusedElement()->localName);
        $session->close($dialog);
        self::assertNull($leaf->getFocusedElement());
    }
}
