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
use Symfony\Component\HtmlRenderer\Event\DocumentEvent;
use Symfony\Component\HtmlRenderer\HtmlRenderer;
use Symfony\Component\HtmlRenderer\RenderingContext;

final class CommandTest extends TestCase
{
    public function testCustomCommandsPreserveCaseAndTargetAndSource(): void
    {
        $session = (new HtmlRenderer())->createSession('<button id="n" commandfor="p" command="--Next"><span>Next</span></button><button id="bad" commandfor="p" command="next">Bad</button><p id="p">1</p>', new RenderingContext(80, 24));
        $events = [];
        $session->on('command', static function (DocumentEvent $event) use (&$events): void {
            $events[] = [$event->target->id, $event->data['command'], $event->data['source']->id];
        });
        $session->activate($session->getDocument()->querySelector('span'));
        $session->activate($session->getDocument()->getElementById('bad'));
        self::assertSame([['p', '--Next', 'n']], $events);
    }

    public function testBuiltInCommandsCanBeCanceledAndRevalidateAfterListeners(): void
    {
        $session = (new HtmlRenderer())->createSession('<button id="o" commandfor="d" command="SHOW-MODAL">Open</button><dialog id="d"><button id="x" commandfor="d" command="close">Close</button></dialog>', new RenderingContext(80, 24));
        $dialog = $session->getDocument()->getElementById('d');
        $unsubscribe = $session->on('command', static fn (DocumentEvent $event) => $event->preventDefault());
        $session->activate($session->getDocument()->getElementById('o'));
        self::assertFalse($dialog->hasAttribute('open'));
        $unsubscribe();
        $session->activate($session->getDocument()->getElementById('o'));
        self::assertTrue($dialog->hasAttribute('open'));
        $session->activate($session->getDocument()->getElementById('x'));
        self::assertFalse($dialog->hasAttribute('open'));
        $session->on('command', static fn (DocumentEvent $event) => $event->target->remove());
        $session->activate($session->getDocument()->getElementById('o'));
        self::assertFalse($dialog->hasAttribute('open'));
    }

    public function testModalInvokerCanOpenASiblingDialog()
    {
        $session = (new HtmlRenderer())->createSession('<dialog id="first"><button commandfor="second" command="show-modal">Next</button></dialog><dialog id="second"><button>Done</button></dialog>', new RenderingContext(300, 200));
        $first = $session->getDocument()->getElementById('first');
        $second = $session->getDocument()->getElementById('second');
        $session->showModal($first);
        $invoker = $first->querySelector('button');
        $session->activate($invoker);
        self::assertTrue($second->hasAttribute('open'));
        self::assertTrue($second->contains($session->getFocusedElement()));
        $session->close($second);
        self::assertSame($invoker, $session->getFocusedElement());
    }

    public function testMalformedPopoverInvokersAreInert()
    {
        $session = (new HtmlRenderer())->createSession('<form><button id="invalid" popovertarget="plain">Invalid</button><button id="missing" popovertarget="absent">Missing</button></form><div id="plain">Ordinary content</div>', new RenderingContext(300, 200));
        self::assertNull($session->activate($session->getDocument()->getElementById('invalid')));
        self::assertNull($session->activate($session->getDocument()->getElementById('missing')));
    }

    public function testPopoverCommandsAndUnknownTargetsDoNotSubmitForms(): void
    {
        $session = (new HtmlRenderer())->createSession('<form><button id="s" commandfor="p" command="show-popover">Show</button><button id="h" commandfor="p" command="hide-popover">Hide</button><button id="missing" commandfor="absent" command="close">Missing</button></form><div id="p" popover>Menu</div>', new RenderingContext(80, 24));
        self::assertNull($session->activate($session->getDocument()->getElementById('s')));
        self::assertNotEmpty($session->render()->getBounds($session->getDocument()->getElementById('p')));
        $session->activate($session->getDocument()->getElementById('h'));
        self::assertSame([], $session->render()->getBounds($session->getDocument()->getElementById('p')));
        self::assertNull($session->activate($session->getDocument()->getElementById('missing')));
    }
}
