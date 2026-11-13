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
use Symfony\Component\Tui\Terminal\VirtualTerminal;
use Symfony\Component\Tui\Tui;
use Symfony\Component\Tui\Widget\InputWidget;

final class FocusManagerTabIndexTest extends TestCase
{
    public function testCycleOrder()
    {
        $focusManager = (new Tui(null, new VirtualTerminal(80, 24)))->getFocusManager();
        [$a, $b, $c, $d, $e] = [new InputWidget(), new InputWidget(), new InputWidget(), new InputWidget(), new InputWidget()];
        $focusManager->setTabIndex($b, 3)->setTabIndex($c, -1)->setTabIndex($d, 1)->setTabIndex($e, 3);
        $focusManager->add($a)->add($b)->add($c)->add($d)->add($e);
        $this->assertSame([$d, $b, $e, $a], $focusManager->getCycle());
        $this->assertSame(3, $focusManager->getTabIndex($b));
        $this->assertSame(0, $focusManager->getTabIndex($a));
    }

    public function testFirstFocusSkipsWidgetsOutOfTheCycle()
    {
        $focusManager = (new Tui(null, new VirtualTerminal(80, 24)))->getFocusManager();
        $skipped = new InputWidget();
        $disabled = (new InputWidget())->setState('disabled');
        $first = new InputWidget();
        $focusManager->setTabIndex($skipped, -1);
        $focusManager->add($skipped)->add($disabled)->add($first);
        $this->assertSame($first, $focusManager->getFocus());
    }

    public function testNavigationSkipsDisabledWidgets()
    {
        $focusManager = (new Tui(null, new VirtualTerminal(80, 24)))->getFocusManager();
        [$a, $b, $c] = [new InputWidget(), new InputWidget(), new InputWidget()];
        $focusManager->add($a)->add($b)->add($c);
        $b->setState('disabled');
        $this->assertSame($c, $focusManager->focusNext());
        $this->assertSame($a, $focusManager->focusNext());
        $this->assertSame($c, $focusManager->focusPrevious());
        $b->setState('disabled', false);
        $this->assertSame($b, $focusManager->focusPrevious());
    }

    public function testRemovingTheFocusedWidgetFocusesTheFirstInTheCycle()
    {
        $focusManager = (new Tui(null, new VirtualTerminal(80, 24)))->getFocusManager();
        [$a, $b, $c] = [new InputWidget(), new InputWidget(), new InputWidget()];
        $focusManager->setTabIndex($c, 1);
        $focusManager->add($a)->add($b)->add($c);
        $focusManager->setFocus($a);
        $focusManager->remove($a);
        $this->assertSame($c, $focusManager->getFocus());
    }

    public function testHandleInputNeedsSeveralWidgetsInTheCycle()
    {
        $focusManager = (new Tui(null, new VirtualTerminal(80, 24)))->getFocusManager();
        [$a, $b] = [new InputWidget(), new InputWidget()];
        $focusManager->setTabIndex($b, -1);
        $focusManager->add($a)->add($b);
        $this->assertFalse($focusManager->handleInput("\x1b[17~"));
    }

    public function testEmptyCycle()
    {
        $focusManager = (new Tui(null, new VirtualTerminal(80, 24)))->getFocusManager();
        $a = new InputWidget();
        $focusManager->setTabIndex($a, -1)->add($a);
        $this->assertNull($focusManager->getFocus());
        $this->assertNull($focusManager->focusNext());
        $this->assertNull($focusManager->focusPrevious());
    }
}
