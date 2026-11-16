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
use Symfony\Component\Tui\Focus\FocusPolicyInterface;
use Symfony\Component\Tui\Terminal\VirtualTerminal;
use Symfony\Component\Tui\Tui;
use Symfony\Component\Tui\Widget\AbstractWidget;
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

    public function testATabIndexSetBackToZeroKeepsTheOrderOfAddition()
    {
        $focusManager = (new Tui(null, new VirtualTerminal(80, 24)))->getFocusManager();
        [$a, $b, $c] = [new InputWidget(), new InputWidget(), new InputWidget()];
        $focusManager->add($a)->add($b)->add($c);
        $focusManager->setTabIndex($c, 2)->setTabIndex($c, 1);
        $this->assertSame([$c, $a, $b], $focusManager->getCycle());

        $focusManager->setTabIndex($c, 0);
        $this->assertSame([$a, $b, $c], $focusManager->getCycle());
        $focusManager->setFocus($c);
        $this->assertSame($a, $focusManager->focusNext());
        $this->assertSame($c, $focusManager->focusPrevious());
    }

    public function testOnlyTheFocusablesWithAPositiveTabIndexAreCounted()
    {
        $focusManager = (new Tui(null, new VirtualTerminal(80, 24)))->getFocusManager();
        $count = static fn () => (new \ReflectionProperty($focusManager, 'positiveTabIndexes'))->getValue($focusManager);
        [$a, $button] = [new InputWidget(), new InputWidget()];
        $focusManager->setTabIndex($button, 1);
        $this->assertSame(0, $count(), 'Not added yet.');
        $focusManager->add($a)->add($button);
        $this->assertSame(1, $count());
        $this->assertSame([$button, $a], $focusManager->getCycle());

        // Like a dialog closed
        $focusManager->remove($button);
        $this->assertSame(0, $count());
        $focusManager->add($button);
        $this->assertSame(1, $count(), 'Its tab index is kept.');
        $focusManager->clear();
        $this->assertSame(0, $count());
    }

    public function testAPolicyOrdersTheCycleAfterThePositiveTabIndexes()
    {
        $focusManager = (new Tui(null, new VirtualTerminal(80, 24)))->getFocusManager();
        // Like a renderer that draws the widgets in a grid of two rows, filled column by column
        $focusManager->setPolicy(new class implements FocusPolicyInterface {
            public function allowsFocus(AbstractWidget $widget): bool
            {
                return true;
            }

            public function orderFocusCycle(array $widgets): array
            {
                return [...array_values(array_filter($widgets, static fn ($w, $i) => 0 === $i % 2, \ARRAY_FILTER_USE_BOTH)), ...array_values(array_filter($widgets, static fn ($w, $i) => 1 === $i % 2, \ARRAY_FILTER_USE_BOTH))];
            }
        });
        [$a, $b, $c, $d, $first] = [new InputWidget(), new InputWidget(), new InputWidget(), new InputWidget(), new InputWidget()];
        $focusManager->setTabIndex($first, 1);
        $focusManager->add($a)->add($b)->add($c)->add($d)->add($first);

        $this->assertSame([$first, $a, $c, $b, $d], $focusManager->getCycle());
        $focusManager->setFocus($c);
        $this->assertSame($b, $focusManager->focusNext());
        $this->assertSame($c, $focusManager->focusPrevious());
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

    public function testNavigationAsksOnlyUpToTheNextWidgetThatTakesTheFocus()
    {
        $focusManager = (new Tui(null, new VirtualTerminal(80, 24)))->getFocusManager();
        $policy = new class implements FocusPolicyInterface {
            public int $asked = 0;
            public array $refused = [];

            public function allowsFocus(AbstractWidget $widget): bool
            {
                ++$this->asked;

                return !\in_array($widget, $this->refused, true);
            }

            public function orderFocusCycle(array $widgets): array
            {
                return $widgets;
            }
        };
        $focusManager->setPolicy($policy);
        $widgets = [];
        for ($i = 0; $i < 100; ++$i) {
            $focusManager->add($widgets[] = new InputWidget());
        }
        $focusManager->setFocus($widgets[10]);
        $policy->refused = [$widgets[11]];

        $policy->asked = 0;
        $this->assertSame($widgets[12], $focusManager->focusNext());
        // The refused one and the next one, not the 100 of them, each resolving its styles
        $this->assertSame(2, $policy->asked);
        $this->assertSame($widgets[10], $focusManager->focusPrevious());
        $focusManager->setFocus($widgets[99]);
        $this->assertSame($widgets[0], $focusManager->focusNext(), 'Around the end.');
        $policy->refused = [$widgets[0]];
        $this->assertSame($widgets[1], $focusManager->focusNext(), 'From the first, the focused one refused now.');
    }

    public function testAFocusedWidgetThatNoLongerTakesTheFocusIsWhereTheCycleGoesOnFrom()
    {
        $focusManager = (new Tui(null, new VirtualTerminal(80, 24)))->getFocusManager();
        [$a, $b, $c, $d] = [new InputWidget(), new InputWidget(), new InputWidget(), new InputWidget()];
        $focusManager->add($a)->add($b)->add($c)->add($d);
        $focusManager->setFocus($c);
        $c->setState(AbstractWidget::STATE_DISABLED);
        $this->assertSame($d, $focusManager->focusNext(), 'The next one, like a browser.');
        $focusManager->setFocus($c);
        $this->assertSame($b, $focusManager->focusPrevious(), 'The previous one.');
        $this->assertNotContains($c, $focusManager->getCycle());
    }

    public function testRevalidatingBlursAFocusedWidgetThatNoLongerTakesTheFocus()
    {
        $focusManager = (new Tui(null, new VirtualTerminal(80, 24)))->getFocusManager();
        [$a, $b, $c] = [new InputWidget(), new InputWidget(), new InputWidget()];
        $focusManager->add($a)->add($b)->add($c);
        $focusManager->setFocus($b);
        $this->assertTrue($focusManager->revalidateFocus());
        $this->assertSame($b, $focusManager->getFocus());

        $b->setState(AbstractWidget::STATE_DISABLED);
        $this->assertFalse($focusManager->revalidateFocus());
        $this->assertNull($focusManager->getFocus());
        $this->assertFalse($b->isFocused());
        $this->assertSame($c, $focusManager->focusNext(), 'From where the focus was.');
        $this->assertSame($a, $focusManager->focusNext(), 'Around the end, without the disabled one.');
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
