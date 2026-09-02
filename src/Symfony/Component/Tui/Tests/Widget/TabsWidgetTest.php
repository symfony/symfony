<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Tui\Tests\Widget;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Tui\Ansi\AnsiUtils;
use Symfony\Component\Tui\Event\TabChangeEvent;
use Symfony\Component\Tui\Render\RenderContext;
use Symfony\Component\Tui\Style\Direction;
use Symfony\Component\Tui\Terminal\VirtualTerminal;
use Symfony\Component\Tui\Tui;
use Symfony\Component\Tui\Widget\InputWidget;
use Symfony\Component\Tui\Widget\TabItem;
use Symfony\Component\Tui\Widget\TabsWidget;
use Symfony\Component\Tui\Widget\TextWidget;

class TabsWidgetTest extends TestCase
{
    public function testOnlyActiveTabContentIsAttached()
    {
        $first = new InputWidget();
        $second = new InputWidget();

        $tabs = new TabsWidget([
            new TabItem('first', 'First', $first),
            new TabItem('second', 'Second', $second),
        ]);

        $this->assertSame($tabs, $first->getParent());
        $this->assertNull($second->getParent());

        $tui = new Tui(terminal: new VirtualTerminal(80, 24));
        $tui->add($tabs);

        $focusables = $tui->getFocusManager()->all();
        $this->assertContains($tabs, $focusables);
        $this->assertContains($first, $focusables);
        $this->assertNotContains($second, $focusables);

        $tabs->setActiveTab(1);

        $focusables = $tui->getFocusManager()->all();
        $this->assertContains($tabs, $focusables);
        $this->assertContains($second, $focusables);
        $this->assertNotContains($first, $focusables);
    }

    public function testFocusMovesBackToTabsWhenSwitchingAwayFromFocusedContent()
    {
        $first = new InputWidget();
        $second = new InputWidget();

        $tabs = new TabsWidget([
            new TabItem('first', 'First', $first),
            new TabItem('second', 'Second', $second),
        ]);

        $tui = new Tui(terminal: new VirtualTerminal(80, 24));
        $tui->add($tabs);

        $tui->setFocus($first);
        $this->assertSame($first, $tui->getFocus());

        $tabs->setActiveTab(1);

        $this->assertSame($tabs, $tui->getFocus());
    }

    public function testFocusContentOnSwitchFocusesNewActiveContent()
    {
        $first = new InputWidget();
        $second = new InputWidget();

        $tabs = new TabsWidget([
            new TabItem('first', 'First', $first),
            new TabItem('second', 'Second', $second),
        ]);
        $tabs->setFocusContentOnSwitch(true);

        $tui = new Tui(terminal: new VirtualTerminal(80, 24));
        $tui->add($tabs);

        $tui->setFocus($first);

        $tabs->setActiveTab(1);

        $this->assertSame($second, $tui->getFocus());
    }

    public function testCanFocusActiveTabContentOnConfirm()
    {
        $first = new InputWidget();
        $second = new InputWidget();

        $tabs = new TabsWidget([
            new TabItem('first', 'First', $first),
            new TabItem('second', 'Second', $second),
        ]);

        $tui = new Tui(terminal: new VirtualTerminal(80, 24));
        $tui->add($tabs);

        $tui->setFocus($tabs);
        $this->assertSame($tabs, $tui->getFocus());

        $tabs->handleInput("\r");

        $this->assertSame($first, $tui->getFocus());
    }

    public function testTabChangeEventIsDispatched()
    {
        $tabs = new TabsWidget([
            new TabItem('first', 'First', new TextWidget('A')),
            new TabItem('second', 'Second', new TextWidget('B')),
        ]);

        $tui = new Tui(terminal: new VirtualTerminal(80, 24));
        $tui->add($tabs);

        $previous = null;
        $current = null;

        $tui->addListener(static function (TabChangeEvent $event) use (&$previous, &$current): void {
            $previous = $event->getPreviousId();
            $current = $event->getId();
        });

        $tabs->setActiveTab(1);

        $this->assertSame('first', $previous);
        $this->assertSame('second', $current);
    }

    public function testRemoveActiveTabDispatchesTabChangeEvent()
    {
        $first = new TextWidget('a');
        $second = new TextWidget('b');
        $third = new TextWidget('c');

        $tabs = new TabsWidget([
            new TabItem('first', 'First', $first),
            new TabItem('second', 'Second', $second),
            new TabItem('third', 'Third', $third),
        ]);

        $tui = new Tui(terminal: new VirtualTerminal(80, 24));
        $tui->add($tabs);

        $events = [];
        $tabs->onTabChange(static function (TabChangeEvent $event) use (&$events): void {
            $events[] = [$event->getPreviousId(), $event->getId()];
        });

        $tabs->remove($third);
        $this->assertSame([], $events);

        $tabs->remove($first);
        $this->assertSame([['first', 'second']], $events);

        $tabs->remove($second);
        $this->assertSame([['first', 'second']], $events);
    }

    public function testTabAndShiftTabSwitchTabsByDefault()
    {
        $tabs = new TabsWidget([
            new TabItem('first', 'First', new TextWidget('A')),
            new TabItem('second', 'Second', new TextWidget('B')),
            new TabItem('third', 'Third', new TextWidget('C')),
        ]);

        $tui = new Tui(terminal: new VirtualTerminal(80, 24));
        $tui->add($tabs);

        $tabs->handleInput("\t");
        $this->assertSame('second', $tabs->getActiveTabId());

        $tabs->handleInput("\x1b[Z");
        $this->assertSame('first', $tabs->getActiveTabId());

        $tabs->handleInput("\x1b[Z");
        $this->assertSame('third', $tabs->getActiveTabId());
    }

    public function testHorizontalRenderDoesNotPadToContextHeight()
    {
        $tabs = new TabsWidget([
            new TabItem('first', 'First', new TextWidget('one line')),
            new TabItem('second', 'Second', new TextWidget('other line')),
        ]);

        $tui = new Tui(terminal: new VirtualTerminal(80, 24));
        $tui->add($tabs);

        $lines = $tabs->render(new RenderContext(40, 20));

        $this->assertCount(4, $lines);
        $this->assertStringContainsString('First', $lines[1]);
        $this->assertStringContainsString('one line', $lines[3]);
    }

    public function testVerticalRenderDoesNotPadToContextHeight()
    {
        $tabs = new TabsWidget([
            new TabItem('first', 'First', new TextWidget('one line')),
            new TabItem('second', 'Second', new TextWidget('other line')),
        ], Direction::Vertical);

        $tui = new Tui(terminal: new VirtualTerminal(80, 24));
        $tui->add($tabs);

        $lines = $tabs->render(new RenderContext(40, 20));

        // 2 tabs × 3 rows each = 6 lines
        $this->assertCount(6, $lines);
        // Tab 0: rows 0 (top), 1 (label), 2 (bottom); Tab 1: rows 3 (top), 4 (label), 5 (bottom)
        $this->assertStringContainsString('one line', $lines[1]);
        $this->assertStringContainsString('First', $lines[1]);
        $this->assertStringContainsString('Second', $lines[4]);
    }

    public function testVerticalRenderUsesHeaderDecorationAndEnclosedLabels()
    {
        $tabs = new TabsWidget([
            new TabItem('first', 'First', new TextWidget('one line')),
            new TabItem('second', 'Second', new TextWidget('other line')),
        ], Direction::Vertical);

        $tui = new Tui(terminal: new VirtualTerminal(80, 24));
        $tui->add($tabs);

        $lines = $tabs->render(new RenderContext(40, 20));

        // 2 tabs × 3 rows = 6 lines:
        // [0] top of tab 0 (top separator), [1] label tab 0 (active),
        // [2] bottom of tab 0, [3] top of tab 1,
        // [4] label tab 1 (inactive), [5] bottom of tab 1 (bottom separator)
        $this->assertCount(6, $lines);

        $plainTop = AnsiUtils::stripAnsiCodes($lines[0]);
        $plainActive = AnsiUtils::stripAnsiCodes($lines[1]);
        $plainActiveBottom = AnsiUtils::stripAnsiCodes($lines[2]);
        $plainInactiveTop = AnsiUtils::stripAnsiCodes($lines[3]);
        $plainInactive = AnsiUtils::stripAnsiCodes($lines[4]);
        $plainBottom = AnsiUtils::stripAnsiCodes($lines[5]);

        $headerWidth = mb_strpos($plainActive, 'one line', 0, 'UTF-8');
        $this->assertNotFalse($headerWidth);
        $this->assertGreaterThanOrEqual(3, $headerWidth);

        $headerWidth = (int) $headerWidth;

        // Top separator (first tab top): ╭──── extended with ─ fill (active at edge, no vertical line)
        $this->assertSame('╭', mb_substr($plainTop, 0, 1, 'UTF-8'));
        $this->assertSame('─', mb_substr($plainTop, $headerWidth - 1, 1, 'UTF-8'));
        $this->assertSame('─', mb_substr($plainTop, $headerWidth, 1, 'UTF-8'));

        // Active tab label: open right (space)
        $this->assertSame('│', mb_substr($plainActive, 0, 1, 'UTF-8'));
        $this->assertSame(' ', mb_substr($plainActive, $headerWidth - 1, 1, 'UTF-8'));

        // Active tab bottom: ╰──┐ (┐ starts the vertical line below)
        $this->assertSame('╰', mb_substr($plainActiveBottom, 0, 1, 'UTF-8'));
        $this->assertSame('┐', mb_substr($plainActiveBottom, $headerWidth - 1, 1, 'UTF-8'));

        // Inactive tab top: ╭──┤ (┤ continues the vertical line)
        $this->assertSame('╭', mb_substr($plainInactiveTop, 0, 1, 'UTF-8'));
        $this->assertSame('┤', mb_substr($plainInactiveTop, $headerWidth - 1, 1, 'UTF-8'));

        // Inactive tab label: closed right (│)
        $this->assertSame('│', mb_substr($plainInactive, 0, 1, 'UTF-8'));
        $this->assertSame('│', mb_substr($plainInactive, $headerWidth - 1, 1, 'UTF-8'));

        // Bottom separator (last tab bottom): ╰──┴ extended with ─ fill (┴ connects vertical line)
        $this->assertSame('╰', mb_substr($plainBottom, 0, 1, 'UTF-8'));
        $this->assertSame('┴', mb_substr($plainBottom, $headerWidth - 1, 1, 'UTF-8'));
        $this->assertSame('─', mb_substr($plainBottom, $headerWidth, 1, 'UTF-8'));
    }

    public function testVerticalTabBoxStructureVariesByActiveTab()
    {
        $tabs = new TabsWidget([
            new TabItem('a', 'A', new TextWidget('x')),
            new TabItem('b', 'B', new TextWidget('y')),
            new TabItem('c', 'C', new TextWidget('z')),
        ], Direction::Vertical);

        $tui = new Tui(terminal: new VirtualTerminal(80, 24));
        $tui->add($tabs);

        // Header width for 1-char labels is 5 (│ A │), right char at position 4
        $extractRightChars = static function (TabsWidget $tabs): array {
            $lines = $tabs->render(new RenderContext(40, 20));
            $chars = [];
            foreach ($lines as $line) {
                $plain = AnsiUtils::stripAnsiCodes($line);
                $chars[] = mb_substr($plain, 4, 1, 'UTF-8');
            }

            return $chars;
        };

        // 3 tabs × 3 rows = 9 lines:
        // [0] tab0 top, [1] tab0 label, [2] tab0 bottom,
        // [3] tab1 top, [4] tab1 label, [5] tab1 bottom,
        // [6] tab2 top, [7] tab2 label, [8] tab2 bottom

        // Active tab 0: vertical line starts at ┐ on active bottom, runs down
        $tabs->setActiveTab(0);
        $chars = $extractRightChars($tabs);
        $this->assertSame('─', $chars[0], 'Tab 0 top: ─ (no line, active at edge)');
        $this->assertSame(' ', $chars[1], 'Tab 0 label: active (open)');
        $this->assertSame('┐', $chars[2], 'Tab 0 bottom: ┐ (line starts)');
        $this->assertSame('┤', $chars[3], 'Tab 1 top: ┤ (line continues)');
        $this->assertSame('│', $chars[4], 'Tab 1 label: │ (inactive)');
        $this->assertSame('┤', $chars[5], 'Tab 1 bottom: ┤ (line continues)');
        $this->assertSame('┤', $chars[6], 'Tab 2 top: ┤ (line continues)');
        $this->assertSame('│', $chars[7], 'Tab 2 label: │ (inactive)');
        $this->assertSame('┴', $chars[8], 'Tab 2 bottom: ┴ (bottom separator)');

        // Active tab 1: line breaks at tab 1 label
        $tabs->setActiveTab(1);
        $chars = $extractRightChars($tabs);
        $this->assertSame('┬', $chars[0], 'Tab 0 top: ┬ (top separator)');
        $this->assertSame('│', $chars[1], 'Tab 0 label: │ (inactive)');
        $this->assertSame('┤', $chars[2], 'Tab 0 bottom: ┤ (line continues)');
        $this->assertSame('┘', $chars[3], 'Tab 1 top: ┘ (line ends)');
        $this->assertSame(' ', $chars[4], 'Tab 1 label: active (open)');
        $this->assertSame('┐', $chars[5], 'Tab 1 bottom: ┐ (line starts)');
        $this->assertSame('┤', $chars[6], 'Tab 2 top: ┤ (line continues)');
        $this->assertSame('│', $chars[7], 'Tab 2 label: │ (inactive)');
        $this->assertSame('┴', $chars[8], 'Tab 2 bottom: ┴ (bottom separator)');

        // Active tab 2: line ends at ┘ on active top
        $tabs->setActiveTab(2);
        $chars = $extractRightChars($tabs);
        $this->assertSame('┬', $chars[0], 'Tab 0 top: ┬ (top separator)');
        $this->assertSame('│', $chars[1], 'Tab 0 label: │ (inactive)');
        $this->assertSame('┤', $chars[2], 'Tab 0 bottom: ┤ (line continues)');
        $this->assertSame('┤', $chars[3], 'Tab 1 top: ┤ (line continues)');
        $this->assertSame('│', $chars[4], 'Tab 1 label: │ (inactive)');
        $this->assertSame('┤', $chars[5], 'Tab 1 bottom: ┤ (line continues)');
        $this->assertSame('┘', $chars[6], 'Tab 2 top: ┘ (line ends)');
        $this->assertSame(' ', $chars[7], 'Tab 2 label: active (open)');
        $this->assertSame('─', $chars[8], 'Tab 2 bottom: ─ (no line, active at edge)');
    }

    public function testVerticalTopAndBottomSeparatorsFillToRightEdge()
    {
        $tabs = new TabsWidget([
            new TabItem('first', 'A', new TextWidget('content')),
            new TabItem('second', 'B', new TextWidget('other')),
        ], Direction::Vertical);

        $tui = new Tui(terminal: new VirtualTerminal(80, 24));
        $tui->add($tabs);

        $lines = $tabs->render(new RenderContext(30, 10));

        // 2 tabs × 3 rows = 6 total lines
        $this->assertCount(6, $lines);

        // Top separator (first tab top, line 0) should fill to full width with ─
        $this->assertSame(30, AnsiUtils::visibleWidth($lines[0]));
        $plainTop = AnsiUtils::stripAnsiCodes($lines[0]);
        $this->assertSame('╭', mb_substr($plainTop, 0, 1, 'UTF-8'));
        $this->assertSame('─', mb_substr($plainTop, 29, 1, 'UTF-8'));

        // Bottom separator (last tab bottom, line 5) should fill to full width with ─
        $this->assertSame(30, AnsiUtils::visibleWidth($lines[5]));
        $plainBottom = AnsiUtils::stripAnsiCodes($lines[5]);
        $this->assertSame('╰', mb_substr($plainBottom, 0, 1, 'UTF-8'));
        $this->assertSame('─', mb_substr($plainBottom, 29, 1, 'UTF-8'));
    }

    public function testHorizontalBottomLineFillsToRightEdge()
    {
        $tabs = new TabsWidget([
            new TabItem('first', 'First', new TextWidget('content')),
        ]);

        $tui = new Tui(terminal: new VirtualTerminal(80, 24));
        $tui->add($tabs);

        $lines = $tabs->render(new RenderContext(30, 10));

        $this->assertCount(4, $lines);
        $this->assertSame(30, AnsiUtils::visibleWidth($lines[2]));

        $plainBottom = AnsiUtils::stripAnsiCodes($lines[2]);
        $this->assertSame('─', mb_substr($plainBottom, 29, 1, 'UTF-8'));
    }

    public function testHorizontalHeaderKeepsActiveTabVisible()
    {
        $tabs = new TabsWidget([
            new TabItem('first', 'AAAA', new TextWidget('a')),
            new TabItem('second', 'BBBB', new TextWidget('b')),
            new TabItem('third', 'CCCC', new TextWidget('c')),
        ]);

        $tui = new Tui(terminal: new VirtualTerminal(80, 24));
        $tui->add($tabs);
        $tabs->setActiveTab(2);

        $lines = $tabs->render(new RenderContext(10, 10));
        $header = AnsiUtils::stripAnsiCodes($lines[1]);

        $this->assertStringContainsString('CCCC', $header);
        $this->assertStringNotContainsString('AAAA', $header);
        $this->assertStringContainsString('c', $lines[3]);
    }

    public function testVerticalHeaderKeepsActiveTabVisible()
    {
        $items = [];
        foreach (range(0, 5) as $i) {
            $items[] = new TabItem((string) $i, 'T'.$i, new TextWidget('content'.$i));
        }

        $tabs = new TabsWidget($items, Direction::Vertical);
        $tui = new Tui(terminal: new VirtualTerminal(80, 24));
        $tui->add($tabs);
        $tabs->setActiveTab(5);

        $lines = array_map(AnsiUtils::stripAnsiCodes(...), $tabs->render(new RenderContext(20, 6)));
        $output = implode("\n", $lines);

        $this->assertStringContainsString('T5', $output);
        $this->assertStringNotContainsString('T0', $output);
        $this->assertStringContainsString('content5', $output);
    }

    public function testVerticalScrolledHeaderUsesEdgeJunctions()
    {
        $items = [];
        foreach (range(0, 5) as $i) {
            $items[] = new TabItem((string) $i, 'T'.$i, new TextWidget('content'.$i));
        }

        $tabs = new TabsWidget($items, Direction::Vertical);
        $tui = new Tui(terminal: new VirtualTerminal(80, 24));
        $tui->add($tabs);

        // Header width for 2-char labels is 6 (│ T0 │), right char at position 5
        $extractRightChars = static function (TabsWidget $tabs): array {
            $chars = [];
            foreach ($tabs->render(new RenderContext(20, 9)) as $line) {
                $chars[] = mb_substr(AnsiUtils::stripAnsiCodes($line), 5, 1, 'UTF-8');
            }

            return $chars;
        };

        // 9 rows show 3 of the 6 tab boxes; the first and last visible rows are
        // the full-width separators and must use edge junctions (┬, ┴ or ─)

        // Active tab 0: window shows tabs 0-2, active at the window top
        $chars = $extractRightChars($tabs);
        $this->assertSame(['─', ' ', '┐', '┤', '│', '┤', '┤', '│', '┴'], $chars);

        // Active tab 3: window shows tabs 1-3, active at the window bottom
        $tabs->setActiveTab(3);
        $chars = $extractRightChars($tabs);
        $this->assertSame(['┬', '│', '┤', '┤', '│', '┤', '┘', ' ', '─'], $chars);

        // Active tab 5: window shows tabs 3-5, active at the window bottom
        $tabs->setActiveTab(5);
        $chars = $extractRightChars($tabs);
        $this->assertSame(['┬', '│', '┤', '┤', '│', '┤', '┘', ' ', '─'], $chars);
    }

    public function testVerticalRenderFitsNarrowContexts()
    {
        $tabs = new TabsWidget([
            new TabItem('first', 'First', new TextWidget('content')),
        ], Direction::Vertical);

        $tui = new Tui(terminal: new VirtualTerminal(80, 24));
        $tui->add($tabs);

        foreach ([1, 2, 3] as $columns) {
            foreach ($tabs->render(new RenderContext($columns, 5)) as $line) {
                $this->assertLessThanOrEqual($columns, AnsiUtils::visibleWidth($line));
            }
        }
    }

    public function testHeaderDirectionCanBeChanged()
    {
        $tabs = new TabsWidget([
            new TabItem('first', 'First', new TextWidget('A')),
            new TabItem('second', 'Second', new TextWidget('B')),
        ]);

        $tui = new Tui(terminal: new VirtualTerminal(80, 24));
        $tui->add($tabs);

        $tabs->setHeaderDirection(Direction::Vertical);
        $tabs->handleInput("\x1b[B");

        $this->assertSame('second', $tabs->getActiveTabId());
        $this->assertStringContainsString('Second', $tabs->render(new RenderContext(40, 20))[4]);
    }

    public function testAddAttachesFirstTabContentWhenAlreadyAttached()
    {
        $tabs = new TabsWidget();

        $tui = new Tui(terminal: new VirtualTerminal(80, 24));
        $tui->add($tabs);

        $first = new InputWidget();
        $tabs->add($first);

        $this->assertContains($first, $tui->getFocusManager()->all());
    }

    public function testRemoveActiveTabDetachesContentAndAttachesNewActive()
    {
        $first = new InputWidget();
        $second = new InputWidget();

        $tabs = new TabsWidget([
            new TabItem('first', 'First', $first),
            new TabItem('second', 'Second', $second),
        ]);

        $tui = new Tui(terminal: new VirtualTerminal(80, 24));
        $tui->add($tabs);

        $tabs->remove($first);

        $focusables = $tui->getFocusManager()->all();
        $this->assertNotContains($first, $focusables);
        $this->assertContains($second, $focusables);
    }

    public function testRemoveInactiveTabBeforeActiveTabPreservesActiveContent()
    {
        $first = new InputWidget();
        $second = new InputWidget();
        $third = new InputWidget();

        $tabs = new TabsWidget([
            new TabItem('first', 'First', $first),
            new TabItem('second', 'Second', $second),
            new TabItem('third', 'Third', $third),
        ]);

        $tui = new Tui(terminal: new VirtualTerminal(80, 24));
        $tui->add($tabs);
        $tabs->setActiveTab(1);

        $tabs->remove($first);

        $this->assertSame('second', $tabs->getActiveTabId());
        $focusables = $tui->getFocusManager()->all();
        $this->assertContains($second, $focusables);
        $this->assertNotContains($third, $focusables);
    }

    public function testRemoveActiveTabKeepsFocusOnTabsWhenContentWasFocused()
    {
        $other = new InputWidget();
        $first = new InputWidget();
        $second = new InputWidget();

        $tabs = new TabsWidget([
            new TabItem('first', 'First', $first),
            new TabItem('second', 'Second', $second),
        ]);

        $tui = new Tui(terminal: new VirtualTerminal(80, 24));
        $tui->add($other);
        $tui->add($tabs);

        $tui->setFocus($first);

        $tabs->remove($first);

        $this->assertSame($tabs, $tui->getFocus());
    }

    public function testClearKeepsFocusOnTabsWhenContentWasFocused()
    {
        $other = new InputWidget();
        $first = new InputWidget();

        $tabs = new TabsWidget([
            new TabItem('first', 'First', $first),
        ]);

        $tui = new Tui(terminal: new VirtualTerminal(80, 24));
        $tui->add($other);
        $tui->add($tabs);

        $tui->setFocus($first);

        $tabs->clear();

        $this->assertSame($tabs, $tui->getFocus());
    }

    public function testAutoGeneratedTabIdsStayUniqueAfterRemoval()
    {
        $tabs = new TabsWidget();
        $first = new TextWidget('a');

        $tabs->add($first);
        $tabs->add(new TextWidget('b'));
        $tabs->remove($first);
        $tabs->add(new TextWidget('c'));

        $ids = array_map(static fn (TabItem $tab) => $tab->getId(), $tabs->getTabs());

        $this->assertSame(['tab_1', 'tab_2'], $ids);
    }

    public function testClearDetachesActiveContent()
    {
        $first = new InputWidget();

        $tabs = new TabsWidget([
            new TabItem('first', 'First', $first),
        ]);

        $tui = new Tui(terminal: new VirtualTerminal(80, 24));
        $tui->add($tabs);

        $this->assertContains($first, $tui->getFocusManager()->all());

        $tabs->clear();

        $this->assertNotContains($first, $tui->getFocusManager()->all());
    }

    public function testEmptyTabsCanBeAttachedRenderedAndNavigated()
    {
        $tabs = new TabsWidget();

        $tui = new Tui(terminal: new VirtualTerminal(80, 24));
        $tui->add($tabs);

        $tabs->handleInput("\x1b[C");

        $this->assertNull($tabs->getActiveTabId());
        $this->assertSame([], $tabs->render(new RenderContext(40, 10)));

        $tabs->add(new TextWidget('content'));
        $tabs->clear();
        $tabs->handleInput("\x1b[D");

        $this->assertNull($tabs->getActiveTabId());
    }

    public function testFocusedTabsRenderBackgroundOnHeaderLabels()
    {
        $tabs = new TabsWidget([
            new TabItem('first', 'First', new TextWidget('A')),
            new TabItem('second', 'Second', new TextWidget('B')),
        ]);

        $tui = new Tui(terminal: new VirtualTerminal(80, 24));
        $tui->add($tabs);

        $tui->setFocus(null);
        $unfocused = $tabs->render(new RenderContext(40, 10));
        $this->assertStringNotContainsString("\x1b[48;", $unfocused[1]);

        $tui->setFocus($tabs);

        $focused = $tabs->render(new RenderContext(40, 10));
        $this->assertStringContainsString("\x1b[48;", $focused[1]);
    }
}
