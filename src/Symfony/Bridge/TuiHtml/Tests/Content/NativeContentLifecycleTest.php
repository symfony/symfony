<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\TuiHtml\Tests\Content;

use PHPUnit\Framework\TestCase;
use Symfony\Bridge\TuiHtml\Content\NativeContentRegistry;
use Symfony\Bridge\TuiHtml\Render\TerminalTextMeasurer;
use Symfony\Bridge\TuiHtml\Widget\DocumentWidget;
use Symfony\Component\HtmlRenderer\HtmlRenderer;
use Symfony\Component\HtmlRenderer\RenderingContext;
use Symfony\Component\Tui\Event\ChangeEvent;
use Symfony\Component\Tui\Terminal\VirtualTerminal;
use Symfony\Component\Tui\Tui;
use Symfony\Component\Tui\Widget\InputWidget;
use Symfony\Component\Tui\Widget\WidgetContext;

class NativeContentLifecycleTest extends TestCase
{
    public function testAttachedNativeInputKeepsDocumentFocusAndItsOwnChangeEvent()
    {
        $session = new HtmlRenderer()->createSession('<!DOCTYPE html><app-native tabindex="0"></app-native><button>Next</button>', new RenderingContext(400, 200));
        $element = $session->getDocument()->querySelector('app-native');
        $native = new InputWidget();
        $changes = [];
        $native->onChange(static function (ChangeEvent $event) use (&$changes): void { $changes[] = [$event->getTarget(), $event->getValue()]; });
        $registry = new NativeContentRegistry();
        $registry->register($element, $native);
        $widget = new DocumentWidget($session, new TerminalTextMeasurer(), $registry);
        $outside = new InputWidget();
        $tui = new Tui(null, new VirtualTerminal(50, 10));
        $manager = $tui->getFocusManager();
        $manager->setTabIndex($native, 7);
        $tui->add($widget);
        $tui->add($outside);
        $tui->start();
        try {
            $tui->setFocus($widget);
            $session->focus($element);
            self::assertSame($widget, $manager->getFocus());
            self::assertTrue($native->isFocused());
            self::assertSame(-1, $manager->getTabIndex($native));
            self::assertSame($widget, $native->getParent());
            self::assertSame($widget->getContext(), $native->getContext());
            $tui->handleInput('x');
            self::assertSame('x', $native->getValue());
            self::assertSame([[$native, 'x']], $changes);
            $tui->handleInput("\t");
            self::assertFalse($native->isFocused());
            self::assertSame('button', $session->getFocusedElement()->localName);
            $tui->handleInput("\t");
            self::assertSame($outside, $manager->getFocus());
        } finally {
            $tui->remove($widget);
            $tui->stop();
        }
        self::assertNull($native->getContext());
        self::assertNull($native->getParent());
        self::assertSame(7, $manager->getTabIndex($native));
        $tui->add($widget);
        $tui->start();
        try {
            $tui->setFocus($widget);
            $session->focus($element);
            $tui->handleInput('y');
            self::assertSame('xy', $native->getValue());
            self::assertCount(2, $changes);
        } finally {
            $tui->stop();
        }
    }

    public function testNativeTickLifecycleStopsOnUnregisterAndCanRemount()
    {
        $session = new HtmlRenderer()->createSession('<!DOCTYPE html><app-native tabindex="0"></app-native>', new RenderingContext(400, 200));
        $native = new class extends InputWidget {
            public int $ticks = 0;
            public int $attached = 0;
            public int $detached = 0;
            private ?string $tick = null;

            protected function onAttach(WidgetContext $context): void
            {
                ++$this->attached;
                $this->tick = $context->scheduleTick(function (): void {
                    ++$this->ticks;
                    $this->invalidate();
                }, 0.000001);
            }

            protected function onDetach(): void
            {
                ++$this->detached;
                if (null !== $this->tick) {
                    $this->getContext()?->cancelTick($this->tick);
                    $this->tick = null;
                }
            }
        };
        $registry = new NativeContentRegistry();
        $element = $session->getDocument()->querySelector('app-native');
        $registry->register($element, $native);
        $widget = new DocumentWidget($session, new TerminalTextMeasurer(), $registry);
        $tui = new Tui(null, new VirtualTerminal(50, 10));
        $tui->add($widget);
        $tui->start();
        try {
            $tui->tick();
            self::assertGreaterThan(0, $native->ticks);
            self::assertSame(1, $native->attached);
            $registry->unregister($element);
            self::assertSame(1, $native->detached);
            $ticks = $native->ticks;
            $tui->tick();
            self::assertSame($ticks, $native->ticks);
            $registry->register($element, $native);
            self::assertSame(2, $native->attached);
            $tui->tick();
            self::assertGreaterThan($ticks, $native->ticks);
        } finally {
            $tui->remove($widget);
            $tui->stop();
        }
        self::assertSame(2, $native->detached);
    }

    public function testKeydownUnregisterDoesNotDeliverInputToStaleWidget()
    {
        $session = new HtmlRenderer()->createSession('<!DOCTYPE html><app-native tabindex="0"></app-native>', new RenderingContext(400, 200));
        $element = $session->getDocument()->querySelector('app-native');
        $native = new InputWidget();
        $registry = new NativeContentRegistry();
        $registry->register($element, $native);
        $widget = new DocumentWidget($session, new TerminalTextMeasurer(), $registry);
        $tui = new Tui(null, new VirtualTerminal(50, 10));
        $tui->add($widget);
        $tui->start();
        try {
            $tui->setFocus($widget);
            $session->focus($element);
            $session->on('keydown', static function () use ($registry, $element): void { $registry->unregister($element); });
            $tui->handleInput('x');
            self::assertSame('', $native->getValue());
            self::assertNull($native->getContext());
            self::assertSame($widget, $tui->getFocusManager()->getFocus());
        } finally {
            $tui->stop();
        }
    }

    public function testCanceledKeyAndNativeChangeDisposalDoNotDuplicateEvents()
    {
        $session = new HtmlRenderer()->createSession('<!DOCTYPE html><app-native tabindex="0"></app-native>', new RenderingContext(400, 200));
        $element = $session->getDocument()->querySelector('app-native');
        $native = new InputWidget();
        $registry = new NativeContentRegistry();
        $registry->register($element, $native);
        $widget = new DocumentWidget($session, new TerminalTextMeasurer(), $registry);
        $events = 0;
        $native->onChange(static function (ChangeEvent $event) use ($registry, $native, &$events): void {
            self::assertSame($native, $event->getTarget());
            ++$events;
            $registry->dispose();
        });
        $tui = new Tui(null, new VirtualTerminal(50, 10));
        $tui->add($widget);
        $tui->start();
        try {
            $tui->setFocus($widget);
            $session->focus($element);
            $unsubscribe = $session->on('keydown', static function ($event): void { $event->preventDefault(); });
            $tui->handleInput('x');
            self::assertSame('', $native->getValue());
            self::assertSame(0, $events);
            $unsubscribe();
            $tui->handleInput('y');
            self::assertSame('y', $native->getValue());
            self::assertSame(1, $events);
            self::assertNull($native->getContext());
            self::assertNull($registry->getWidget($element));
            self::assertSame($widget, $tui->getFocusManager()->getFocus());
        } finally {
            $tui->remove($widget);
            $tui->stop();
        }
    }

    public function testRegistrationRejectsAlreadyAttachedNativeWidget()
    {
        $native = new InputWidget();
        $tui = new Tui(null, new VirtualTerminal(20, 4));
        $tui->add($native);
        $element = \Dom\HTMLDocument::createEmpty()->createElement('app-native');
        $this->expectException(\InvalidArgumentException::class);
        new NativeContentRegistry()->register($element, $native);
    }
}
