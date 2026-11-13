<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Tui\Tests\Html;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Tui\Event\BeforeToggleEvent;
use Symfony\Component\Tui\Event\CommandEvent;
use Symfony\Component\Tui\Event\ToggleEvent;
use Symfony\Component\Tui\Html\HtmlBuilder;
use Symfony\Component\Tui\Html\HtmlView;
use Symfony\Component\Tui\Terminal\VirtualTerminal;
use Symfony\Component\Tui\Tui;

final class PopoverTest extends TestCase
{
    private const HTML = '<button id="t" popovertarget="menu">Menu</button><button id="s" popovertarget="sub" popovertargetaction="show">Other</button><p>page</p>
        <div popover id="menu"><p>Open</p></div><div popover id="sub"><p>Other popover</p></div><div popover="manual" id="toast"><p>Saved</p></div>
        <dialog id="d"><button id="x" commandfor="d" command="close">Cancel</button></dialog><button id="o" commandfor="d" command="show-modal">Ask</button>
        <button id="n" commandfor="pager" command="--Next">Next</button><button id="bad" commandfor="pager" command="next">Bad</button><p id="pager">1</p>';

    public function testInvokersToggleThemAndTheClickOnTheInvokerDoesNotDismissFirst()
    {
        [$tui, $view] = $this->mount();
        $menu = $view->getById('menu');
        $this->assertFalse($menu->hasState('popover-open'));

        $view->getById('t')->activate();
        $this->assertTrue($menu->hasState('popover-open'));
        $tui->processRender();

        $rect = $tui->getWidgetRect($view->getById('t'));
        $view->click($rect->row, $rect->col + 1);
        $this->assertFalse($menu->hasState('popover-open'), 'A click on its invoker toggles it once.');
    }

    public function testACommandDispatchesACommandEventOnItsTarget()
    {
        [$tui, $view] = $this->mount();
        $events = [];
        $view->getById('pager')->on(CommandEvent::class, static function (CommandEvent $event) use (&$events): void {
            $events[] = [$event->getTarget()->getId(), $event->getCommand(), $event->getSource()?->getId()];
        });

        $view->getById('n')->activate();
        $view->getById('bad')->activate();
        $this->assertSame([['pager', '--Next', 'n']], $events, 'A custom command, as written; a command that is neither built in nor custom dispatches nothing.');

        $tui->addListener(static function (CommandEvent $event): void {
            if ('show-modal' === $event->getCommand()) {
                $event->stopPropagation();
            }
        });
        $view->getById('o')->activate();
        $this->assertFalse($view->getById('d')->hasState('modal'), 'Stopping the event keeps the built-in command from running.');
    }

    public function testShowingAndHidingDispatchToggleEvents()
    {
        [$tui, $view] = $this->mount();
        $menu = $view->getById('menu');
        $events = [];
        $refuse = true;
        $menu->on(BeforeToggleEvent::class, static function (BeforeToggleEvent $event) use (&$events, &$refuse): void {
            $events[] = 'before:'.$event->getNewState();
            if ($refuse && 'open' === $event->getNewState()) {
                $event->stopPropagation();
            }
        });
        $menu->on(ToggleEvent::class, static function (ToggleEvent $event) use (&$events): void {
            $events[] = $event->getOldState().'>'.$event->getNewState();
        });

        $view->showPopover($menu);
        $this->assertFalse($menu->hasState('popover-open'), 'Stopped before it is shown, it stays hidden.');

        $refuse = false;
        $view->showPopover($menu);
        $view->getById('s')->activate();
        $this->assertSame(['before:open', 'before:open', 'closed>open', 'before:closed', 'open>closed'], $events, 'Hidden by another auto popover too.');
    }

    public function testEscapeAClickOutsideAndAnotherAutoPopoverCloseIt()
    {
        [$tui, $view] = $this->mount();
        $menu = $view->getById('menu');

        $view->showPopover($menu);
        $tui->handleInput("\e");
        $this->assertFalse($menu->hasState('popover-open'), 'Escape.');

        $view->showPopover($menu);
        $tui->processRender();
        $view->click(2, 1);
        $this->assertFalse($menu->hasState('popover-open'), 'A click outside.');

        $view->showPopover($menu);
        $view->getById('s')->activate();
        $this->assertFalse($menu->hasState('popover-open'), 'Another auto popover.');
        $this->assertTrue($view->getById('sub')->hasState('popover-open'));
    }

    public function testAManualPopoverStaysAndCommandsOpenDialogs()
    {
        [$tui, $view] = $this->mount();
        $toast = $view->getById('toast');
        $view->showPopover($toast);
        $tui->handleInput("\e");
        $view->click(2, 1);
        $this->assertTrue($toast->hasState('popover-open'), 'Only closed when asked.');
        $view->hidePopover($toast);
        $this->assertFalse($toast->hasState('popover-open'));

        $view->getById('o')->activate();
        $this->assertTrue($view->getById('d')->hasState('modal'));
        $view->getById('x')->activate();
        $this->assertFalse($view->getById('d')->hasState('open'));
    }

    /**
     * @return array{Tui, HtmlView}
     */
    private function mount(): array
    {
        $tui = new Tui(terminal: new VirtualTerminal(30, 12));
        $view = (new HtmlBuilder())->build(self::HTML)->mount($tui);
        $tui->start();
        $tui->processRender();

        return [$tui, $view];
    }
}
