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
use Symfony\Component\Tui\Input\Key;
use Symfony\Component\Tui\Input\Keybindings;
use Symfony\Component\Tui\Render\RenderContext;
use Symfony\Component\Tui\Style\Style;
use Symfony\Component\Tui\Style\StyleSheet;
use Symfony\Component\Tui\Terminal\VirtualTerminal;
use Symfony\Component\Tui\Tui;
use Symfony\Component\Tui\Widget\AbstractWidget;
use Symfony\Component\Tui\Widget\FocusableInterface;
use Symfony\Component\Tui\Widget\FocusableTrait;
use Symfony\Component\Tui\Widget\KeybindingsTrait;
use Symfony\Component\Tui\Widget\KeyBindingWidget;

class KeyBindingWidgetTest extends TestCase
{
    // --- Render: empty state ---

    public function testRenderEmptyReturnsNoLines()
    {
        $widget = new KeyBindingWidget();

        $this->assertSame([], $widget->render(new RenderContext(80, 1)));
    }

    public function testActionsWithoutKeysAreSkipped()
    {
        $widget = new KeyBindingWidget();
        $widget->setGlobalKeybindings(new Keybindings(['quit' => []]), ['quit' => 'Quit']);

        $this->assertSame([], $widget->render(new RenderContext(80, 1)));
    }

    // --- Render: globals only ---

    public function testRenderGlobalItemsOnly()
    {
        $widget = new KeyBindingWidget();
        $widget->setGlobalKeybindings(
            new Keybindings(['quit' => ['ctrl+c']]),
            ['quit' => 'Quit']
        );

        $lines = $this->renderStripped($widget);
        $this->assertCount(1, $lines);
        $this->assertStringContainsString('Quit', $lines[0]);
        $this->assertStringContainsString('Ctrl+C', $lines[0]);
    }

    public function testGlobalsOnlyLineIsRightAnchored()
    {
        $widget = new KeyBindingWidget();
        $widget->setGlobalKeybindings(
            new Keybindings(['quit' => ['ctrl+c']]),
            ['quit' => 'Quit']
        );

        $lines = $this->renderStripped($widget, 40);
        $this->assertCount(1, $lines);
        $this->assertSame(40, AnsiUtils::visibleWidth($lines[0]));
        $this->assertStringEndsWith('Quit', $lines[0]);
    }

    // --- Render: focused items only ---

    public function testRenderFocusedItemsOnly()
    {
        [$widget] = $this->createWithTui(
            new Keybindings(['move_up' => [Key::UP]]),
            ['move_up' => 'Move Up']
        );

        $lines = $this->renderStripped($widget);
        $this->assertCount(1, $lines);
        $this->assertStringContainsString('Move Up', $lines[0]);
        $this->assertStringContainsString('▲', $lines[0]);
    }

    // --- Render: both groups on one line ---

    public function testRenderBothGroupsOnOneLine()
    {
        [$widget] = $this->createWithTui(
            new Keybindings(['move_up' => [Key::UP]]),
            ['move_up' => 'Move Up']
        );
        $widget->setGlobalKeybindings(
            new Keybindings(['quit' => ['ctrl+c']]),
            ['quit' => 'Quit']
        );

        $lines = $this->renderStripped($widget);
        $this->assertCount(1, $lines);
        $this->assertStringContainsString('Move Up', $lines[0]);
        $this->assertStringContainsString('│', $lines[0]);
        $this->assertStringContainsString('Quit', $lines[0]);
    }

    public function testGlobalsAreRightAligned()
    {
        [$widget] = $this->createWithTui(
            new Keybindings(['move_up' => [Key::UP]]),
            ['move_up' => 'Up']
        );
        $widget->setGlobalKeybindings(
            new Keybindings(['quit' => ['ctrl+c']]),
            ['quit' => 'Quit']
        );

        $lines = $this->renderStripped($widget, 40);
        $this->assertCount(1, $lines);
        // Globals appear after focused items in the line
        $this->assertGreaterThan(
            strpos($lines[0], 'Up'),
            strpos($lines[0], 'Quit')
        );
    }

    // --- Render: wrapping ---

    public function testFocusedItemsWrapToMultipleLines()
    {
        $bindings = new Keybindings([
            'a' => ['a'], 'b' => ['b'], 'c' => ['c'], 'd' => ['d'],
            'e' => ['e'], 'f' => ['f'], 'g' => ['g'], 'h' => ['h'],
        ]);
        $labels = [
            'a' => 'Action A', 'b' => 'Action B', 'c' => 'Action C', 'd' => 'Action D',
            'e' => 'Action E', 'f' => 'Action F', 'g' => 'Action G', 'h' => 'Action H',
        ];

        [$widget] = $this->createWithTui($bindings, $labels);
        $widget->setGlobalKeybindings(
            new Keybindings(['quit' => ['ctrl+c']]),
            ['quit' => 'Quit']
        );

        $lines = $this->renderStripped($widget, 40);
        $this->assertGreaterThan(1, \count($lines));
        $this->assertStringContainsString('Quit', end($lines));
    }

    public function testGlobalsOnOwnLineWhenLastLineFull()
    {
        [$widget] = $this->createWithTui(
            new Keybindings(['a' => ['a'], 'b' => ['b'], 'c' => ['c']]),
            ['a' => 'Action A Long', 'b' => 'Action B Long', 'c' => 'Action C Long']
        );
        $widget->setGlobalKeybindings(
            new Keybindings(['quit' => ['ctrl+c'], 'help' => ['?'], 'save' => ['ctrl+s']]),
            ['quit' => 'Quit', 'help' => 'Help', 'save' => 'Save']
        );

        $lines = $this->renderStripped($widget, 20);
        $lastLine = end($lines);
        $this->assertStringContainsString('Quit', $lastLine);
    }

    // --- Width constraints ---

    public function testLinesNeverExceedColumns()
    {
        $bindings = new Keybindings([
            'a' => ['a'], 'b' => ['b'], 'c' => ['c'], 'd' => ['d'],
            'e' => ['e'], 'f' => ['f'], 'g' => ['g'],
        ]);
        $labels = [
            'a' => 'Action A', 'b' => 'Action B', 'c' => 'Action C', 'd' => 'Action D',
            'e' => 'Action E', 'f' => 'Action F', 'g' => 'Action G',
        ];

        [$widget] = $this->createWithTui($bindings, $labels);
        $widget->setGlobalKeybindings(
            new Keybindings(['quit' => ['ctrl+c']]),
            ['quit' => 'Quit']
        );

        $columns = 30;
        foreach ($widget->render(new RenderContext($columns, 10)) as $line) {
            $this->assertLessThanOrEqual($columns, AnsiUtils::visibleWidth($line));
        }
    }

    public function testUnicodeKeyLabelsCountedCorrectly()
    {
        [$widget] = $this->createWithTui(
            new Keybindings(['move_up' => [Key::UP]]),
            ['move_up' => 'Up']
        );
        $widget->setKeyLabels([Key::UP => '▲▲']);

        $columns = 20;
        foreach ($widget->render(new RenderContext($columns, 1)) as $line) {
            $this->assertLessThanOrEqual($columns, AnsiUtils::visibleWidth($line));
        }
    }

    public function testItemsWiderThanColumnsAreClamped()
    {
        [$widget] = $this->createWithTui(
            new Keybindings(['move_up' => [Key::UP]]),
            ['move_up' => 'A Focused Action Label Wider Than The Terminal']
        );
        $widget->setGlobalKeybindings(
            new Keybindings(['quit' => ['ctrl+c']]),
            ['quit' => 'A Global Label Wider Than The Terminal']
        );

        $columns = 20;
        $lines = $widget->render(new RenderContext($columns, 10));
        $this->assertNotEmpty($lines);
        foreach ($lines as $line) {
            $this->assertLessThanOrEqual($columns, AnsiUtils::visibleWidth($line));
        }
    }

    // --- Gap ---

    public function testSetGapChangesSpacingBetweenItems()
    {
        [$widget] = $this->createWithTui(
            new Keybindings(['a' => ['a'], 'b' => ['b']]),
            ['a' => 'A', 'b' => 'B']
        );
        $widget->setGap(4);

        $lines = $this->renderStripped($widget);
        // The two items should have 4 spaces between them
        $this->assertMatchesRegularExpression('/A\s{4}/', $lines[0]);
    }

    // --- Separator ---

    public function testSeparatorAbsentWhenNoGlobals()
    {
        [$widget] = $this->createWithTui(
            new Keybindings(['a' => ['a']]),
            ['a' => 'Action A']
        );

        $lines = $this->renderStripped($widget);
        $this->assertStringNotContainsString('│', $lines[0]);
    }

    public function testSetSeparatorChangesCharacter()
    {
        [$widget] = $this->createWithTui(
            new Keybindings(['a' => ['a']]),
            ['a' => 'Action A']
        );
        $widget->setGlobalKeybindings(new Keybindings(['quit' => ['q']]), ['quit' => 'Quit']);
        $widget->setSeparator(' | ');

        $lines = $this->renderStripped($widget);
        $this->assertStringContainsString('|', $lines[0]);
        $this->assertStringNotContainsString('│', $lines[0]);
    }

    public function testSetSeparatorEmptyHidesIt()
    {
        [$widget] = $this->createWithTui(
            new Keybindings(['a' => ['a']]),
            ['a' => 'Action A']
        );
        $widget->setGlobalKeybindings(new Keybindings(['quit' => ['q']]), ['quit' => 'Quit']);
        $widget->setSeparator('');

        $lines = $this->renderStripped($widget);
        $this->assertStringNotContainsString('│', $lines[0]);
    }

    // --- setGlobalKeybindings() ---

    public function testGlobalKeybindingsWithExplicitLabels()
    {
        $widget = new KeyBindingWidget();
        $widget->setGlobalKeybindings(
            new Keybindings(['quit' => ['ctrl+c'], 'help' => ['?']]),
            ['quit' => 'Quit', 'help' => 'Help']
        );

        $lines = $this->renderStripped($widget);
        $this->assertStringContainsString('Quit', $lines[0]);
        $this->assertStringContainsString('Help', $lines[0]);
    }

    public function testGlobalKeybindingsWithHumanizedLabels()
    {
        $widget = new KeyBindingWidget();
        $widget->setGlobalKeybindings(
            new Keybindings(['move_up' => [Key::UP], 'page_down' => [Key::PAGE_DOWN]])
        );

        $lines = $this->renderStripped($widget);
        $this->assertStringContainsString('Move Up', $lines[0]);
        $this->assertStringContainsString('Page Down', $lines[0]);
    }

    public function testGlobalKeybindingsSkipsUnknownActions()
    {
        $widget = new KeyBindingWidget();
        $widget->setGlobalKeybindings(
            new Keybindings(['quit' => ['ctrl+c']]),
            ['quit' => 'Quit', 'nonexistent' => 'Ghost']
        );

        $this->assertStringNotContainsString('Ghost', implode('', $this->renderStripped($widget)));
    }

    // --- setKeyLabels() ---

    public function testKeyLabelOverrideAppearsInRender()
    {
        [$widget] = $this->createWithTui(
            new Keybindings(['move_up' => [Key::UP]]),
            ['move_up' => 'Move Up']
        );
        $widget->setKeyLabels([Key::UP => 'CUSTOM-UP']);

        $lines = $this->renderStripped($widget);
        $this->assertStringContainsString('CUSTOM-UP', $lines[0]);
        $this->assertStringNotContainsString('▲', $lines[0]);
    }

    public function testSetKeyLabelsNullRevertsToDefaults()
    {
        [$widget] = $this->createWithTui(
            new Keybindings(['move_up' => [Key::UP]]),
            ['move_up' => 'Move Up']
        );
        $widget->setKeyLabels([Key::UP => 'CUSTOM-UP']);
        $widget->setKeyLabels(null);

        $lines = $this->renderStripped($widget);
        $this->assertStringContainsString('▲', $lines[0]);
        $this->assertStringNotContainsString('CUSTOM-UP', $lines[0]);
    }

    // --- setKeybindingLabels() on focused widget ---

    public function testExplicitKeybindingLabelsOnFocusedWidget()
    {
        $terminal = new VirtualTerminal(80, 24);
        $tui = new Tui(terminal: $terminal);

        $stub = new StubFocusableWidget();
        $stub->setKeybindings(new Keybindings(['move_up' => [Key::UP]]));
        $stub->setKeybindingLabels(['move_up' => 'Monter']);

        $widget = new KeyBindingWidget();
        $tui->add($stub);
        $tui->add($widget);

        $lines = $this->renderStripped($widget);
        $this->assertStringContainsString('Monter', $lines[0]);
        $this->assertStringNotContainsString('Move Up', $lines[0]);
    }

    public function testSetKeybindingLabelsNullRevertsToDefaults()
    {
        $terminal = new VirtualTerminal(80, 24);
        $tui = new Tui(terminal: $terminal);

        $stub = new StubFocusableWidget();
        $stub->setKeybindings(new Keybindings(['move_up' => [Key::UP]]));
        // No labels → falls back to humanize
        $stub->setKeybindingLabels(null);

        $widget = new KeyBindingWidget();
        $tui->add($stub);
        $tui->add($widget);

        $lines = $this->renderStripped($widget);
        $this->assertStringContainsString('Move Up', $lines[0]);
    }

    public function testLabelChangeOnFocusedWidgetAppearsOnNextRender()
    {
        [$widget, $stub] = $this->createWithTui(
            new Keybindings(['move_up' => [Key::UP]]),
            ['move_up' => 'Move Up']
        );

        $this->assertStringContainsString('Move Up', $this->renderStripped($widget)[0]);

        $stub->setKeybindingLabels(['move_up' => 'Monter']);
        $widget->beforeRender();

        $lines = $this->renderStripped($widget);
        $this->assertStringContainsString('Monter', $lines[0]);
        $this->assertStringNotContainsString('Move Up', $lines[0]);
    }

    public function testLabelsChangedAfterFocusReachTheScreen()
    {
        $terminal = new VirtualTerminal(80, 24);
        $tui = new Tui(terminal: $terminal);

        $stub = new class extends StubFocusableWidget {
            public function handleInput(string $data): void
            {
                $this->setKeybindingLabels(['move_up' => 'Monter']);
            }
        };
        $stub->setKeybindings(new Keybindings(['move_up' => [Key::UP]]));
        $stub->setKeybindingLabels(['move_up' => 'Move Up']);

        $tui->add($stub);
        $tui->add(new KeyBindingWidget());
        $tui->start();
        $tui->processRender();
        $this->assertStringContainsString('Move Up', $terminal->consumeOutput());

        $tui->handleInput('r');
        $tui->processRender();

        $this->assertStringContainsString('Monter', $terminal->consumeOutput());
    }

    // --- Focus integration ---

    public function testInitialFocusedWidgetPopulatesItemsOnAttach()
    {
        [$widget] = $this->createWithTui(
            new Keybindings(['move_up' => [Key::UP]]),
            ['move_up' => 'Move Up']
        );

        $this->assertNotEmpty($this->renderStripped($widget));
    }

    public function testUpdatesOnFocusChange()
    {
        $terminal = new VirtualTerminal(80, 24);
        $tui = new Tui(terminal: $terminal);

        $stub1 = new StubFocusableWidget();
        $stub1->setKeybindings(new Keybindings(['action_a' => ['a']]));
        $stub1->setKeybindingLabels(['action_a' => 'Action A']);

        $stub2 = new StubFocusableWidget();
        $stub2->setKeybindings(new Keybindings(['action_b' => ['b']]));
        $stub2->setKeybindingLabels(['action_b' => 'Action B']);

        $widget = new KeyBindingWidget();

        $tui->add($stub1);
        $tui->add($stub2);
        $tui->add($widget);

        $lines = $this->renderStripped($widget);
        $this->assertStringContainsString('Action A', implode('', $lines));

        // F6 moves focus to next focusable widget
        $tui->handleInput("\x1b[17~");

        $lines = $this->renderStripped($widget);
        $this->assertStringContainsString('Action B', implode('', $lines));
        $this->assertStringNotContainsString('Action A', implode('', $lines));
    }

    public function testFocusCycleReturnsToFirstWidgetBindings()
    {
        $terminal = new VirtualTerminal(80, 24);
        $tui = new Tui(terminal: $terminal);

        $stub1 = new StubFocusableWidget();
        $stub1->setKeybindings(new Keybindings(['action_a' => ['a']]));
        $stub1->setKeybindingLabels(['action_a' => 'Action A']);

        $stub2 = new StubFocusableWidget();
        $stub2->setKeybindings(new Keybindings(['action_b' => ['b']]));
        $stub2->setKeybindingLabels(['action_b' => 'Action B']);

        $widget = new KeyBindingWidget();

        $tui->add($stub1);
        $tui->add($stub2);
        $tui->add($widget);

        // Navigate until back to stub1
        $tui->handleInput("\x1b[17~"); // stub2
        $tui->handleInput("\x1b[17~"); // back to stub1

        $lines = $this->renderStripped($widget);
        $this->assertStringContainsString('Action A', implode('', $lines));
        $this->assertStringNotContainsString('Action B', implode('', $lines));
    }

    // --- Sub-element styles ---

    public function testSubElementKeyStyleApplied()
    {
        $stylesheet = new StyleSheet([
            KeyBindingWidget::class.'::key' => new Style(bold: true),
        ]);

        $terminal = new VirtualTerminal(80, 24);
        $tui = new Tui(styleSheet: $stylesheet, terminal: $terminal);

        $stub = new StubFocusableWidget();
        $stub->setKeybindings(new Keybindings(['move_up' => [Key::UP]]));
        $stub->setKeybindingLabels(['move_up' => 'Move Up']);

        $widget = new KeyBindingWidget();
        $tui->add($stub);
        $tui->add($widget);

        $raw = implode('', $widget->render(new RenderContext(80, 1)));
        $this->assertStringContainsString("\x1b[1m", $raw); // bold ANSI code
    }

    public function testSubElementGlobalKeyStyleApplied()
    {
        $stylesheet = new StyleSheet([
            KeyBindingWidget::class.'::global-key' => new Style(bold: true),
        ]);

        $terminal = new VirtualTerminal(80, 24);
        $tui = new Tui(styleSheet: $stylesheet, terminal: $terminal);

        $widget = new KeyBindingWidget();
        $widget->setGlobalKeybindings(
            new Keybindings(['quit' => ['ctrl+c']]),
            ['quit' => 'Quit']
        );
        $tui->add($widget);

        $raw = implode('', $widget->render(new RenderContext(80, 1)));
        $this->assertStringContainsString("\x1b[1m", $raw); // bold ANSI code
    }

    // --- Helpers ---

    /**
     * @return array{KeyBindingWidget, StubFocusableWidget}
     */
    private function createWithTui(Keybindings $bindings, array $labels): array
    {
        $terminal = new VirtualTerminal(80, 24);
        $tui = new Tui(terminal: $terminal);

        $stub = new StubFocusableWidget();
        $stub->setKeybindings($bindings);
        $stub->setKeybindingLabels($labels);

        $widget = new KeyBindingWidget();

        $tui->add($stub);   // added first → gets focus
        $tui->add($widget); // onAttach sees stub as focused → populates items

        return [$widget, $stub];
    }

    /**
     * @return string[]
     */
    private function renderStripped(KeyBindingWidget $widget, int $columns = 80): array
    {
        return array_map(
            static fn (string $line) => AnsiUtils::stripAnsiCodes($line),
            $widget->render(new RenderContext($columns, 10))
        );
    }
}

/**
 * @internal
 */
class StubFocusableWidget extends AbstractWidget implements FocusableInterface
{
    use FocusableTrait;
    use KeybindingsTrait;

    public function render(RenderContext $context): array
    {
        return [];
    }

    public function handleInput(string $data): void
    {
    }
}
