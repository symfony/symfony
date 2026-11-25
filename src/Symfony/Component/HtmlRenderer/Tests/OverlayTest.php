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
use Symfony\Component\HtmlRenderer\HtmlRenderer;
use Symfony\Component\HtmlRenderer\RenderingContext;

final class OverlayTest extends TestCase
{
    public function testToggleListenersCanReenterWithoutDuplicatingOverlayTransitions()
    {
        $session = (new HtmlRenderer())->createSession('<dialog><input></dialog><div popover>Popup</div>', new RenderingContext(300, 200));
        $dialog = $session->getDocument()->querySelector('dialog');
        $popover = $session->getDocument()->querySelector('[popover]');
        $calls = 0;
        $session->on('beforetoggle', static function ($event) use ($session, $dialog, &$calls) {
            if (++$calls > 3) {
                throw new \LogicException('Recursive toggle');
            }
            if ('open' === $event->data['newState']) {
                $session->showModal($dialog);
            } else {
                $session->close($dialog);
            }
        }, $dialog);
        $session->showModal($dialog);
        $session->close($dialog);
        self::assertSame(2, $calls);
        $session->on('beforetoggle', static function ($event) use ($session, $popover) {
            if ('open' === $event->data['newState']) {
                $session->showPopover($popover);
            } else {
                $session->hidePopover($popover);
            }
        }, $popover);
        $session->showPopover($popover);
        $session->hidePopover($popover);
        self::assertSame([], $session->render()->getBounds($popover));
    }

    public function testModalHitTestingAndTabNeverReachBackgroundApplication()
    {
        $session = (new HtmlRenderer())->createSession('<button style="position:fixed;inset:0">Outside</button><dialog style="width:50px;height:40px"><button>Inside</button></dialog>', new RenderingContext(300, 200));
        $dialog = $session->getDocument()->querySelector('dialog');
        $session->showModal($dialog);
        self::assertNull($session->render()->hitTest(1, 1));
        self::assertTrue($session->focusNext(false, false));
        self::assertSame('Inside', $session->getFocusedElement()->textContent);
        $remove = $session->on('keydown', static fn ($event) => $event->preventDefault());
        $session->key(new KeyboardEvent('Escape'));
        self::assertTrue($dialog->hasAttribute('open'));
        $remove();
        $session->key(new KeyboardEvent('Escape'));
        self::assertFalse($dialog->hasAttribute('open'));
    }

    public function testOpeningCanBeCanceledWithoutChangingDocumentOrFocus()
    {
        $session = (new HtmlRenderer())->createSession('<button>Outside</button><dialog><input></dialog>', new RenderingContext(300, 200));
        $button = $session->getDocument()->querySelector('button');
        $dialog = $session->getDocument()->querySelector('dialog');
        $session->focus($button);
        $session->on('beforetoggle', static fn ($event) => $event->preventDefault(), $dialog);
        $session->showModal($dialog);
        self::assertFalse($dialog->hasAttribute('open'));
        self::assertSame($button, $session->getFocusedElement());
        self::assertSame([], $session->render()->getBounds($dialog));
    }

    public function testModalFocusIsContainedAndRestoredWhenClosed()
    {
        $session = (new HtmlRenderer())->createSession('<button id="outside">Outside</button><dialog><button>A</button><input autofocus><button>B</button></dialog>', new RenderingContext(300, 200));
        $document = $session->getDocument();
        $outside = $document->getElementById('outside');
        $dialog = $document->querySelector('dialog');
        $session->focus($outside);
        $session->showModal($dialog);
        self::assertTrue($dialog->hasAttribute('open'));
        self::assertSame($document->querySelector('input'), $session->getFocusedElement());
        $session->focusNext();
        self::assertSame('B', $session->getFocusedElement()->textContent);
        $session->focusNext();
        self::assertSame('A', $session->getFocusedElement()->textContent);
        $session->activate($outside);
        self::assertSame('A', $session->getFocusedElement()->textContent);
        $session->close($dialog, 'done');
        self::assertSame($outside, $session->getFocusedElement());
        self::assertFalse($dialog->hasAttribute('open'));
        self::assertSame('done', $session->getReturnValue($dialog));
    }

    public function testEscapeCanBeCanceledAndNestedModalsUnwind()
    {
        $session = (new HtmlRenderer())->createSession('<button>Outside</button><dialog id="one"><button>One</button></dialog><dialog id="two"><button>Two</button></dialog>', new RenderingContext(300, 200));
        $one = $session->getDocument()->getElementById('one');
        $two = $session->getDocument()->getElementById('two');
        $session->showModal($one);
        $session->showModal($two);
        $remove = $session->on('cancel', static fn ($event) => $event->preventDefault(), $two);
        $session->key(new KeyboardEvent('Escape'));
        self::assertTrue($two->hasAttribute('open'));
        $remove();
        $session->key(new KeyboardEvent('Escape'));
        self::assertFalse($two->hasAttribute('open'));
        self::assertSame('One', $session->getFocusedElement()->textContent);
        self::assertTrue($one->hasAttribute('open'));
    }

    public function testDialogSubmissionClosesWithoutProducingTransportSubmission()
    {
        $session = (new HtmlRenderer())->createSession('<dialog><form method="dialog"><button value="accepted">Accept</button></form></dialog>', new RenderingContext(300, 200));
        $dialog = $session->getDocument()->querySelector('dialog');
        $session->showModal($dialog);
        self::assertNull($session->activate($session->getDocument()->querySelector('button')));
        self::assertFalse($dialog->hasAttribute('open'));
        self::assertSame('accepted', $session->getReturnValue($dialog));
    }

    public function testPopoverInvokerAutoDismissalAndManualPreservation()
    {
        $session = (new HtmlRenderer())->createSession('<button popovertarget="one">Open</button><div id="one" popover>One</div><div id="two" popover>Two</div><div id="manual" popover="manual">Manual</div>', new RenderingContext(300, 200));
        $document = $session->getDocument();
        $one = $document->getElementById('one');
        $two = $document->getElementById('two');
        $manual = $document->getElementById('manual');
        self::assertSame([], $session->render()->getBounds($one));
        $session->activate($document->querySelector('button'));
        self::assertNotEmpty($session->render()->getBounds($one));
        $session->showPopover($manual);
        $session->showPopover($two);
        self::assertSame([], $session->render()->getBounds($one));
        self::assertNotEmpty($session->render()->getBounds($manual));
        self::assertNotEmpty($session->render()->getBounds($two));
        $session->key(new KeyboardEvent('Escape'));
        self::assertSame([], $session->render()->getBounds($two));
        self::assertNotEmpty($session->render()->getBounds($manual));
    }

    public function testRemovingOpenDialogRestoresPageInteraction()
    {
        $session = (new HtmlRenderer())->createSession('<button>Outside</button><dialog><input></dialog>', new RenderingContext(300, 200));
        $dialog = $session->getDocument()->querySelector('dialog');
        $session->showModal($dialog);
        $dialog->remove();
        $session->render();
        $session->focusNext();
        self::assertSame('Outside', $session->getFocusedElement()->textContent);
    }
}
