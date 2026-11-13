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
use Symfony\Component\Tui\Event\CancelEvent;
use Symfony\Component\Tui\Event\CloseEvent;
use Symfony\Component\Tui\Event\ToggleEvent;
use Symfony\Component\Tui\Html\HtmlBuilder;
use Symfony\Component\Tui\Html\HtmlView;
use Symfony\Component\Tui\Terminal\VirtualTerminal;
use Symfony\Component\Tui\Tui;

final class DialogClosedByTest extends TestCase
{
    private const HTML = '<p id="page">The page under the dialogs</p>
        <dialog id="default"><p>Default</p><button>OK</button></dialog>
        <dialog id="any" closedby="any"><p>Any</p><button>OK</button></dialog>
        <dialog id="none" closedby="none"><p>None</p><button>OK</button></dialog>';

    public function testAModalDialogClosesOnEscapeByDefault()
    {
        [$tui, $view] = $this->mount();
        $view->showModal($view->getById('default'));
        $tui->processRender();

        $view->click(0, 0);
        $this->assertTrue($view->getById('default')->hasState('open'), 'closerequest: a click outside does nothing.');
        $tui->handleInput("\e");
        $this->assertFalse($view->getById('default')->hasState('open'));
    }

    public function testClosedByAnyClosesOnAClickOutside()
    {
        [$tui, $view] = $this->mount();
        $dialog = $view->getById('any');
        $view->showModal($dialog);
        $tui->processRender();

        $rect = $tui->getWidgetRect($dialog);
        $view->click($rect->row + 1, $rect->col + 2);
        $this->assertTrue($dialog->hasState('open'), 'A click inside does not close it.');
        $view->click($tui->getWidgetRect($dialog)->row - 1, 0);
        $this->assertFalse($dialog->hasState('open'), 'A click outside does.');
    }

    public function testClosedByNone()
    {
        [$tui, $view] = $this->mount();
        $view->showModal($view->getById('none'));
        $tui->processRender();

        $tui->handleInput("\e");
        $this->assertTrue($view->getById('none')->hasState('open'), 'Escape does not close it.');
        $view->close($view->getById('none'));
        $this->assertFalse($view->getById('none')->hasState('open'), 'Only the application does.');
    }

    public function testANonModalDialogWithClosedBy()
    {
        [$tui, $view] = $this->mount();
        $view->getById('any')->setState('open');
        $view->getById('default')->setState('open');
        $tui->requestRender();
        $tui->processRender();

        $tui->handleInput("\e");
        $this->assertTrue($view->getById('default')->hasState('open'), 'Not modal, none by default: Escape is for the any one.');
        $this->assertFalse($view->getById('any')->hasState('open'));
    }

    /**
     * @return array{Tui, HtmlView}
     */
    public function testShowOpensADialogInThePageAndFocusesIt()
    {
        [$tui, $view] = $this->mount();
        $dialog = $view->getById('default');
        $view->show($dialog);

        $this->assertTrue($dialog->hasState('open'));
        $this->assertFalse($dialog->hasState('modal'), 'Not as a modal: the page stays interactive.');
        $this->assertFalse($view->getById('page')->hasState('inert'));
        $this->assertSame('OK', $tui->getFocus()?->getText());
    }

    public function testEscapeDispatchesACancelEventThatCanKeepTheDialogOpen()
    {
        [$tui, $view] = $this->mount();
        $dialog = $view->getById('default');
        $events = [];
        $keep = true;
        $dialog->on(CancelEvent::class, static function (CancelEvent $event) use (&$events, &$keep): void {
            $events[] = 'cancel';
            if ($keep) {
                $event->stopPropagation();
            }
        });
        $dialog->on(CloseEvent::class, static function (CloseEvent $event) use (&$events): void {
            $events[] = 'close:'.$event->getReturnValue();
        });
        $view->showModal($dialog);

        $tui->handleInput("\e");
        $this->assertTrue($dialog->hasState('open'), 'Stopped, the cancel event keeps it open.');

        $keep = false;
        $tui->handleInput("\e");
        $this->assertFalse($dialog->hasState('open'));

        $view->showModal($dialog);
        $view->close($dialog, 'done');
        $view->close($dialog, 'again');
        $this->assertSame(['cancel', 'cancel', 'close:', 'close:done'], $events, 'close() does not cancel; a closed dialog does not close again.');
        $this->assertSame('done', $view->getReturnValue($dialog));
    }

    public function testOpeningAndClosingDispatchToggleEvents()
    {
        [$tui, $view] = $this->mount();
        $dialog = $view->getById('default');
        $events = [];
        $refuse = true;
        $dialog->on(BeforeToggleEvent::class, static function (BeforeToggleEvent $event) use (&$events, &$refuse): void {
            $events[] = 'before:'.$event->getNewState();
            if ($refuse) {
                $event->stopPropagation();
            }
        });
        $dialog->on(ToggleEvent::class, static function (ToggleEvent $event) use (&$events): void {
            $events[] = $event->getNewState();
        });

        $view->showModal($dialog);
        $this->assertFalse($dialog->hasState('open'), 'Stopped, it does not open.');
        $refuse = false;
        $view->showModal($dialog);
        $view->close($dialog);
        $view->show($dialog);
        $refuse = true;
        $view->close($dialog);
        $this->assertFalse($dialog->hasState('open'), 'Closing cannot be stopped.');
        $this->assertSame(['before:open', 'before:open', 'open', 'before:closed', 'closed', 'before:open', 'open', 'before:closed', 'closed'], $events);
    }

    private function mount(): array
    {
        $tui = new Tui(terminal: new VirtualTerminal(50, 16));
        $view = (new HtmlBuilder())->build(self::HTML)->mount($tui);
        $tui->start();
        $tui->processRender();

        return [$tui, $view];
    }
}
