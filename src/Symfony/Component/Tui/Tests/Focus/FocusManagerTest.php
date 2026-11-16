<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Tui\Tests\Focus;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Tui\Event\FocusEvent;
use Symfony\Component\Tui\Input\Key;
use Symfony\Component\Tui\Input\Keybindings;
use Symfony\Component\Tui\Terminal\VirtualTerminal;
use Symfony\Component\Tui\Tests\KeySequenceParser;
use Symfony\Component\Tui\Tui;
use Symfony\Component\Tui\Widget\InputWidget;

class FocusManagerTest extends TestCase
{
    public function testMoveBefore()
    {
        $tui = new Tui(terminal: new VirtualTerminal(80, 24));
        $focusManager = $tui->getFocusManager();
        [$a, $b, $c, $d, $absent] = [new InputWidget(), new InputWidget(), new InputWidget(), new InputWidget(), new InputWidget()];
        $focusManager->add($a)->add($b)->add($c)->add($d);

        $focusManager->moveBefore([$d, $absent, $c], $b);
        $this->assertSame([$a, $d, $c, $b], $focusManager->all());

        $focusManager->moveBefore([$a], null);
        $this->assertSame([$d, $c, $b, $a], $focusManager->all());
    }

    public function testMoveBeforeOneOfThoseMovedOrUnknown()
    {
        $tui = new Tui(terminal: new VirtualTerminal(80, 24));
        $focusManager = $tui->getFocusManager();
        [$a, $b, $c, $d, $absent] = [new InputWidget(), new InputWidget(), new InputWidget(), new InputWidget(), new InputWidget()];
        $focusManager->add($a)->add($b)->add($c)->add($d);

        // Before one moved: before the first one after it that is not
        $focusManager->moveBefore([$d, $b], $b);
        $this->assertSame([$a, $d, $b, $c], $focusManager->all());

        // Before one it does not hold: none moved
        $focusManager->moveBefore([$a], $absent);
        $this->assertSame([$a, $d, $b, $c], $focusManager->all());
    }

    public function testKeepFocusWhile()
    {
        $tui = new Tui(terminal: new VirtualTerminal(80, 24));
        $focusManager = $tui->getFocusManager();
        [$a, $b, $c] = [new InputWidget(), new InputWidget(), new InputWidget()];
        $focusManager->add($a)->add($b)->add($c);
        $tui->setFocus($b);
        $events = 0;
        $tui->getEventDispatcher()->addListener(FocusEvent::class, static function () use (&$events) {
            ++$events;
        });

        $focusManager->keepFocusWhile(static function () use ($focusManager, $b) {
            $focusManager->remove($b);
            $focusManager->add($b);
        });
        $this->assertSame($b, $tui->getFocus(), 'Removed and added back: the focus stays.');
        $this->assertTrue($b->isFocused());
        $this->assertSame(0, $events);
        $this->assertSame([$a, $c, $b], $focusManager->all());

        $focusManager->keepFocusWhile(static function () use ($focusManager, $b) {
            $focusManager->remove($b);
        });
        $this->assertSame($a, $tui->getFocus(), 'Not added back: the focus goes as when it is removed.');
        $this->assertFalse($b->isFocused());
    }

    public function testFocusNavigation()
    {
        $tui = new Tui(terminal: new VirtualTerminal(80, 24));
        $focusManager = $tui->getFocusManager();

        $first = new InputWidget();
        $second = new InputWidget();

        $focusManager->add($first)->add($second);

        $tui->setFocus($first);
        $this->assertSame($first, $tui->getFocus());

        $focusManager->focusNext();
        $this->assertSame($second, $tui->getFocus());

        $focusManager->focusPrevious();
        $this->assertSame($first, $tui->getFocus());
    }

    public function testHandleInputMovesFocus()
    {
        $tui = new Tui(terminal: new VirtualTerminal(80, 24));
        $focusManager = $tui->getFocusManager();

        $first = new InputWidget();
        $second = new InputWidget();

        $focusManager->add($first)->add($second);
        $tui->setFocus($first);

        $tui->handleInput(KeySequenceParser::parseKeys('<F6>'));
        $this->assertSame($second, $tui->getFocus());

        $tui->handleInput(KeySequenceParser::parseKeys('<Shift+F6>'));
        $this->assertSame($first, $tui->getFocus());
    }

    public function testCustomFocusKeybindings()
    {
        $keybindings = new Keybindings([
            'focus_next' => [Key::TAB],
            'focus_previous' => ['shift+tab'],
        ]);
        $tui = new Tui(terminal: new VirtualTerminal(80, 24), keybindings: $keybindings);
        $focusManager = $tui->getFocusManager();

        $first = new InputWidget();
        $second = new InputWidget();

        $focusManager->add($first)->add($second);
        $tui->setFocus($first);

        // Default F6 should no longer work
        $tui->handleInput(KeySequenceParser::parseKeys('<F6>'));
        $this->assertSame($first, $tui->getFocus());

        // Custom Tab binding should work
        $tui->handleInput(KeySequenceParser::parseKeys('<Tab>'));
        $this->assertSame($second, $tui->getFocus());

        // Custom Shift+Tab binding should work
        $tui->handleInput(KeySequenceParser::parseKeys('<Shift+Tab>'));
        $this->assertSame($first, $tui->getFocus());
    }

    public function testFocusChangedEventProvidesPreviousWidget()
    {
        $tui = new Tui(terminal: new VirtualTerminal(80, 24));
        $focusManager = $tui->getFocusManager();

        $first = new InputWidget();
        $second = new InputWidget();

        $focusManager->add($first)->add($second);

        $received = null;
        $tui->addListener(static function (FocusEvent $event) use (&$received): void {
            $received = $event;
        });

        $tui->setFocus($first);
        $tui->setFocus($second);

        /* @var FocusEvent $received */
        $this->assertSame($second, $received->getTarget());
        $this->assertSame($first, $received->getPrevious());
    }
}
