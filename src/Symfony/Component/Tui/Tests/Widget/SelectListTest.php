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

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Tui\Ansi\AnsiCodeTracker;
use Symfony\Component\Tui\Ansi\AnsiUtils;
use Symfony\Component\Tui\Event\CancelEvent;
use Symfony\Component\Tui\Event\MultiSelectEvent;
use Symfony\Component\Tui\Event\SelectEvent;
use Symfony\Component\Tui\Event\SelectionChangeEvent;
use Symfony\Component\Tui\Event\SelectionToggleEvent;
use Symfony\Component\Tui\Render\RenderContext;
use Symfony\Component\Tui\Render\Renderer;
use Symfony\Component\Tui\Style\Direction;
use Symfony\Component\Tui\Style\Style;
use Symfony\Component\Tui\Style\StyleSheet;
use Symfony\Component\Tui\Terminal\ScreenBuffer;
use Symfony\Component\Tui\Terminal\VirtualTerminal;
use Symfony\Component\Tui\Tui;
use Symfony\Component\Tui\Widget\ContainerWidget;
use Symfony\Component\Tui\Widget\SelectListWidget;
use Symfony\Component\Tui\Widget\TextWidget;

class SelectListTest extends TestCase
{
    public function testRenderShowsItems()
    {
        $list = $this->createTestList();
        $lines = $list->render(new RenderContext(80, 24));

        $this->assertStringContainsString('Option 1', $lines[0]);
    }

    public function testRenderShowsSelectedIndicator()
    {
        $list = $this->createTestList();
        $lines = $list->render(new RenderContext(80, 24));

        // First item should have arrow indicator
        $this->assertStringContainsString('→', $lines[0]);
    }

    public function testNavigateDown()
    {
        $list = $this->createTestList();

        $this->assertSame('opt1', $list->getSelectedItem()['value']);

        // Simulate down arrow
        $list->handleInput("\x1b[B");

        $this->assertSame('opt2', $list->getSelectedItem()['value']);
    }

    public function testNavigateUp()
    {
        $list = $this->createTestList();
        $list->setSelectedIndex(2);

        $this->assertSame('opt3', $list->getSelectedItem()['value']);

        // Simulate up arrow
        $list->handleInput("\x1b[A");

        $this->assertSame('opt2', $list->getSelectedItem()['value']);
    }

    public function testNavigateWrapsAtBottom()
    {
        $list = $this->createTestList();
        $list->setSelectedIndex(2);

        // Simulate down arrow at bottom
        $list->handleInput("\x1b[B");

        $this->assertSame('opt1', $list->getSelectedItem()['value']);
    }

    public function testNavigateWrapsAtTop()
    {
        $list = $this->createTestList();
        $list->setSelectedIndex(0);

        // Simulate up arrow at top
        $list->handleInput("\x1b[A");

        $this->assertSame('opt3', $list->getSelectedItem()['value']);
    }

    public function testOnSelectCallback()
    {
        [$list, $tui] = $this->createTestListWithTui();

        $selectedItem = null;
        $tui->addListener(static function (SelectEvent $e) use (&$selectedItem) {
            $selectedItem = $e->getItem();
        });

        // Simulate Enter
        $list->handleInput("\r");

        $this->assertSame('opt1', $selectedItem['value']);
    }

    public function testOnCancelCallback()
    {
        [$list, $tui] = $this->createTestListWithTui();

        $cancelled = false;
        $tui->addListener(static function (CancelEvent $e) use (&$cancelled) {
            $cancelled = true;
        });

        // Simulate Escape
        $list->handleInput("\x1b");

        $this->assertTrue($cancelled);
    }

    public function testFilter()
    {
        $list = $this->createTestList();

        $list->setFilter('opt2');

        $selected = $list->getSelectedItem();
        $this->assertSame('opt2', $selected['value']);
    }

    public function testFilterNoMatch()
    {
        $list = $this->createTestList();

        $list->setFilter('nonexistent');

        $lines = $list->render(new RenderContext(80, 24));
        $this->assertStringContainsString('No matching', $lines[0]);
    }

    public function testNormalizesMultilineDescription()
    {
        $items = [
            [
                'value' => 'test',
                'label' => 'Test',
                'description' => "Line one\nLine two\nLine three",
            ],
        ];

        $list = new SelectListWidget($items, 5);
        $lines = $list->render(new RenderContext(100, 24));

        $this->assertStringNotContainsString("\n", $lines[0]);
        $this->assertStringContainsString('Line one Line two Line three', $lines[0]);
    }

    public function testRendersWithinWidth()
    {
        $list = $this->createTestList();
        $width = 60;
        $lines = $list->render(new RenderContext($width, 24));

        foreach ($lines as $i => $line) {
            $lineWidth = AnsiUtils::visibleWidth($line);
            $this->assertLessThanOrEqual(
                $width,
                $lineWidth,
                \sprintf('Line %d exceeds width: %d > %d', $i, $lineWidth, $width),
            );
        }
    }

    public function testOnSelectionChangeCallback()
    {
        [$list, $tui] = $this->createTestListWithTui();

        $changedItem = null;
        $tui->addListener(static function (SelectionChangeEvent $e) use (&$changedItem) {
            $changedItem = $e->getItem();
        });

        // Navigate down
        $list->handleInput("\x1b[B");

        $this->assertSame('opt2', $changedItem['value']);
    }

    #[DataProvider('provideNavigationKeysOnEmptyFilteredItems')]
    public function testNavigationOnEmptyFilteredItemsDoesNotCrash(string $input)
    {
        [$list, $tui] = $this->createTestListWithTui();
        $list->setFilter('nonexistent');

        $selectionChanged = false;
        $tui->addListener(static function (SelectionChangeEvent $e) use (&$selectionChanged) {
            $selectionChanged = true;
        });

        $list->handleInput($input);

        $this->assertNull($list->getSelectedItem());
        $this->assertFalse($selectionChanged);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideNavigationKeysOnEmptyFilteredItems(): iterable
    {
        yield 'up arrow' => ["\x1b[A"];
        yield 'down arrow' => ["\x1b[B"];
        yield 'page up' => ["\x1b[5~"];
        yield 'page down' => ["\x1b[6~"];
        yield 'confirm' => ["\r"];
    }

    public function testCancelStillWorksOnEmptyFilteredItems()
    {
        [$list, $tui] = $this->createTestListWithTui();
        $list->setFilter('nonexistent');

        $cancelled = false;
        $tui->addListener(static function (CancelEvent $e) use (&$cancelled) {
            $cancelled = true;
        });

        $list->handleInput("\x1b");

        $this->assertTrue($cancelled);
    }

    public function testMultiselectRendersInitialCheckedItems()
    {
        $list = new SelectListWidget([
            ['value' => 'opt1', 'label' => 'Option 1', 'description' => 'First option'],
            ['value' => 'opt2', 'label' => 'Option 2', 'description' => 'Second option', 'checked' => true],
        ], multiselect: true);

        $lines = $list->render(new RenderContext(80, 24));

        $this->assertStringContainsString('[ ] Option 1', $lines[0]);
        $this->assertStringContainsString('[x] Option 2', $lines[1]);
        $this->assertSame([
            ['value' => 'opt2', 'label' => 'Option 2', 'description' => 'Second option', 'checked' => true],
        ], $list->getSelectedItems());
    }

    public function testMultiselectTogglesCurrentItem()
    {
        $list = $this->createTestList(multiselect: true);

        $list->handleInput(' ');

        $this->assertSame([
            ['value' => 'opt1', 'label' => 'Option 1', 'description' => 'First option', 'checked' => true],
        ], $list->getSelectedItems());
        $this->assertStringContainsString('[x] Option 1', $list->render(new RenderContext(80, 24))[0]);

        $list->handleInput(' ');

        $this->assertSame([], $list->getSelectedItems());
        $this->assertStringContainsString('[ ] Option 1', $list->render(new RenderContext(80, 24))[0]);
    }

    public function testSingleSelectIgnoresSpaceToggle()
    {
        $list = $this->createTestList();

        $list->handleInput(' ');
        $lines = $list->render(new RenderContext(80, 24));

        $this->assertSame([], $list->getSelectedItems());
        $this->assertStringNotContainsString('[ ]', $lines[0]);
        $this->assertStringNotContainsString('[x]', $lines[0]);
    }

    public function testMultiselectPreservesTogglesAcrossFilter()
    {
        $list = $this->createTestList(multiselect: true);

        $list->setFilter('opt2');
        $list->handleInput(' ');
        $list->setFilter('');

        $this->assertSame([
            ['value' => 'opt2', 'label' => 'Option 2', 'description' => 'Second option', 'checked' => true],
        ], $list->getSelectedItems());

        $lines = $list->render(new RenderContext(80, 24));
        $this->assertStringContainsString('[ ] Option 1', $lines[0]);
        $this->assertStringContainsString('[x] Option 2', $lines[1]);
    }

    public function testOnSelectionToggleCallback()
    {
        [$list, $tui] = $this->createTestListWithTui(multiselect: true);

        $toggle = null;
        $tui->addListener(static function (SelectionToggleEvent $e) use (&$toggle) {
            $toggle = [
                'value' => $e->getValue(),
                'checked' => $e->isChecked(),
                'selectedItems' => $e->getSelectedItems(),
                'item' => $e->getItem(),
            ];
        });

        $list->handleInput(' ');

        $this->assertSame([
            'value' => 'opt1',
            'checked' => true,
            'selectedItems' => [
                ['value' => 'opt1', 'label' => 'Option 1', 'description' => 'First option', 'checked' => true],
            ],
            'item' => ['value' => 'opt1', 'label' => 'Option 1', 'description' => 'First option', 'checked' => true],
        ], $toggle);
    }

    public function testOnMultiSelectCallback()
    {
        [$list, $tui] = $this->createTestListWithTui(multiselect: true);

        $event = null;
        $tui->addListener(static function (MultiSelectEvent $e) use (&$event) {
            $event = $e;
        });

        $list->handleInput(' ');
        $list->handleInput("\r");

        $this->assertInstanceOf(MultiSelectEvent::class, $event);
        $this->assertFalse($event->isEmpty());
        $this->assertSame(['opt1'], $event->getValues());
        $this->assertSame([
            ['value' => 'opt1', 'label' => 'Option 1', 'description' => 'First option', 'checked' => true],
        ], $event->getItems());
    }

    public function testMultiSelectEventDispatchesWhenEmpty()
    {
        [$list, $tui] = $this->createTestListWithTui(multiselect: true);

        $event = null;
        $tui->addListener(static function (MultiSelectEvent $e) use (&$event) {
            $event = $e;
        });

        $list->handleInput("\r");

        $this->assertInstanceOf(MultiSelectEvent::class, $event);
        $this->assertTrue($event->isEmpty());
        $this->assertSame([], $event->getValues());
        $this->assertSame([], $event->getItems());
    }

    public function testMultiselectRendersWithinWidth()
    {
        $list = $this->createTestList(multiselect: true);
        $width = 60;
        $lines = $list->render(new RenderContext($width, 24));

        foreach ($lines as $i => $line) {
            $lineWidth = AnsiUtils::visibleWidth($line);
            $this->assertLessThanOrEqual(
                $width,
                $lineWidth,
                \sprintf('Line %d exceeds width: %d > %d', $i, $lineWidth, $width),
            );
        }
    }

    private function createTestList(bool $multiselect = false): SelectListWidget
    {
        $items = [
            ['value' => 'opt1', 'label' => 'Option 1', 'description' => 'First option'],
            ['value' => 'opt2', 'label' => 'Option 2', 'description' => 'Second option'],
            ['value' => 'opt3', 'label' => 'Option 3', 'description' => 'Third option'],
        ];

        return new SelectListWidget($items, 5, multiselect: $multiselect);
    }

    public function testNoMatchLineIsTruncatedToTheWidth()
    {
        $list = new SelectListWidget([['value' => 'alpha', 'label' => 'alpha']]);
        $list->setFilter('zzz');

        foreach ([40, 19, 10, 5, 1] as $columns) {
            $lines = $list->render(new RenderContext($columns, 24));

            $this->assertLessThanOrEqual($columns, AnsiUtils::visibleWidth($lines[0]), \sprintf('The no-match line fits in %d columns.', $columns));
        }
    }

    public function testItemsAreTruncatedToTheWidth()
    {
        $list = new SelectListWidget([
            ['value' => 'a', 'label' => 'alpha', 'description' => 'the first one'],
            ['value' => 'b', 'label' => 'beta', 'description' => 'the second one'],
        ]);

        foreach ([60, 40, 20, 6, 4, 2, 1] as $columns) {
            foreach ($list->render(new RenderContext($columns, 24)) as $line) {
                $this->assertLessThanOrEqual($columns, AnsiUtils::visibleWidth($line), \sprintf('Every row fits in %d columns.', $columns));
            }
        }
    }

    public function testCompactItemsStayOnOneRowWhenColumnsFit()
    {
        $list = new SelectListWidget([
            ['value' => 'd', 'label' => 'Development', 'description' => 'Local development server'],
            ['value' => 'p', 'label' => 'Production', 'description' => 'Public production server'],
            ['value' => 'n', 'label' => 'Normal', 'description' => 'Normal'],
        ]);

        $lines = $list->render(new RenderContext(60, 10));

        $this->assertSame(
            [
                (new Style())->withBold()->apply('→ Development  Local development server'),
                '  Production   '.(new Style())->withColor('gray')->apply('Public production server'),
                '  Normal       '.(new Style())->withColor('gray')->apply('Normal'),
            ],
            $lines,
        );
    }

    public function testLongLabelWrapsWithinLabelColumnBesideDescription()
    {
        $list = new SelectListWidget([
            ['value' => 'a', 'label' => 'alpha beta gamma delta epsilon zeta', 'description' => 'Public production server'],
            ['value' => 'n', 'label' => 'Normal option', 'description' => 'Normal'],
        ]);

        $lines = $list->render(new RenderContext(80, 10));

        $this->assertSame('→ alpha beta gamma delta epsilon  Public production server', AnsiUtils::stripAnsiCodes($lines[0]));
        $this->assertSame('  zeta', AnsiUtils::stripAnsiCodes($lines[1]));
        $this->assertSame('  Normal option                   Normal', AnsiUtils::stripAnsiCodes($lines[2]));
        $this->assertSame((new Style())->withBold()->apply('→ alpha beta gamma delta epsilon  Public production server'), $lines[0]);
        $this->assertSame((new Style())->withBold()->apply('  zeta'), $lines[1]);
    }

    public function testLongDescriptionWrapsUnderDescriptionColumn()
    {
        $list = new SelectListWidget([
            ['value' => 'd', 'label' => 'Development', 'description' => 'Local development server is long'],
            ['value' => 'p', 'label' => 'Production is long', 'description' => 'Public production server'],
            ['value' => 'n', 'label' => 'Normal', 'description' => 'Normal'],
        ]);

        $lines = $list->render(new RenderContext(48, 10));

        $this->assertSame(
            [
                '→ Development         Local development server',
                '                      is long',
                '  Production is long  Public production server',
                '  Normal              Normal',
            ],
            array_map(AnsiUtils::stripAnsiCodes(...), $lines),
        );
        $this->assertStringNotContainsString('(1/', AnsiUtils::stripAnsiCodes(implode("\n", $lines)));
    }

    public function testLabelOnlyListWrapsAcrossAvailableWidth()
    {
        $list = new SelectListWidget([
            ['value' => 'v', 'label' => 'alpha beta gamma delta epsilon zeta'],
        ]);

        $lines = $list->render(new RenderContext(30, 10));

        $this->assertSame(
            [
                (new Style())->withBold()->apply('→ alpha beta gamma delta'),
                (new Style())->withBold()->apply('  epsilon zeta'),
            ],
            $lines,
        );
    }

    public function testMultiselectWrapsWithAlignedContinuationPrefix()
    {
        $list = new SelectListWidget([
            ['value' => 'a', 'label' => 'alpha beta gamma delta epsilon zeta', 'description' => 'Public production server', 'checked' => true],
            ['value' => 'n', 'label' => 'Normal option', 'description' => 'Normal'],
        ], multiselect: true);

        $lines = $list->render(new RenderContext(80, 10));
        $plain = array_map(AnsiUtils::stripAnsiCodes(...), $lines);

        $this->assertSame('→ [x] alpha beta gamma delta epsilon  Public production server', $plain[0]);
        $this->assertSame('      zeta', $plain[1]);
        $this->assertSame('  [ ] Normal option                   Normal', $plain[2]);
        $this->assertSame((new Style())->withBold()->apply('→ [x] alpha beta gamma delta epsilon  Public production server'), $lines[0]);
        $this->assertSame((new Style())->withBold()->apply('      zeta'), $lines[1]);
    }

    public function testWrappedWindowReclaimsTrailingItemsAfterLeadingTrim()
    {
        $list = new SelectListWidget([
            ['value' => 'a', 'label' => str_repeat('a', 80)],
            ['value' => 'b', 'label' => 'sel'],
            ['value' => 'c', 'label' => 'tail'],
        ], 3);
        $list->setSelectedIndex(1);

        $plain = array_map(AnsiUtils::stripAnsiCodes(...), $list->render(new RenderContext(30, 5)));

        $this->assertLessThanOrEqual(5, \count($plain));
        $this->assertSame('→ sel', $plain[0]);
        $this->assertStringContainsString('tail', $plain[1], 'Trailing items must re-enter the window after leading items are trimmed.');
        $this->assertStringContainsString('(2/3)', implode("\n", $plain));
    }

    public function testWrappedWindowDropsItemsPastTheSelectedOneWhenRowsRunOut()
    {
        $items = [];
        for ($i = 1; $i <= 5; ++$i) {
            $items[] = ['value' => 'v'.$i, 'label' => \sprintf('Item %d ', $i).str_repeat('x', 40)];
        }
        $list = new SelectListWidget($items, 5);

        $lines = $list->render(new RenderContext(30, 4));

        // Three wrapped rows for the selected item plus the scroll indicator.
        $this->assertCount(4, $lines);
        $this->assertStringContainsString('Item 1', AnsiUtils::stripAnsiCodes($lines[0]));
        $this->assertStringContainsString('(1/5)', AnsiUtils::stripAnsiCodes($lines[3]));
    }

    public function testWrappedWindowKeepsSelectedItemInsideAvailableRows()
    {
        $items = [];
        for ($i = 1; $i <= 5; ++$i) {
            $items[] = ['value' => 'v'.$i, 'label' => \sprintf('Item %d ', $i).str_repeat('word ', 17)];
        }
        $list = new SelectListWidget($items, 5);
        $list->setSelectedIndex(2);

        $lines = $list->render(new RenderContext(30, 4));

        $this->assertLessThanOrEqual(4, \count($lines), 'Wrapped rendering must not emit more rows than the context provides.');
        $visible = implode("\n", array_map(AnsiUtils::stripAnsiCodes(...), $lines));
        $this->assertStringContainsString('→ Item 3', $visible, 'The selected item must stay visible inside the available rows.');
    }

    public function testWrappedWindowClampsSelectedItemTallerThanViewport()
    {
        $list = new SelectListWidget([
            ['value' => 'a', 'label' => str_repeat('word ', 20)],
        ]);

        $lines = $list->render(new RenderContext(30, 3));

        $this->assertLessThanOrEqual(3, \count($lines), 'A selected item taller than the viewport must be clamped to the available rows.');
        $this->assertStringContainsString('→ word', AnsiUtils::stripAnsiCodes($lines[0]), 'The first row of the clamped selected item stays anchored at the top.');
    }

    public function testExactFitDoesNotShowScrollIndicator()
    {
        $list = new SelectListWidget([
            ['value' => 'a', 'label' => 'alpha'],
            ['value' => 'b', 'label' => 'beta'],
        ]);

        $lines = $list->render(new RenderContext(20, 2));

        $this->assertCount(2, $lines, 'Both items fit the two available rows exactly; nothing is left to scroll.');
        $this->assertStringContainsString('alpha', AnsiUtils::stripAnsiCodes($lines[0]));
        $this->assertStringContainsString('beta', AnsiUtils::stripAnsiCodes($lines[1]));
    }

    public function testOneRowViewportShowsOnlyTheSelectedLabel()
    {
        $list = new SelectListWidget([
            ['value' => 'a', 'label' => 'alpha'],
            ['value' => 'b', 'label' => 'beta'],
        ]);

        $lines = $list->render(new RenderContext(20, 1));

        $this->assertCount(1, $lines, 'A one-row viewport renders exactly one row.');
        $this->assertStringContainsString('→ alpha', AnsiUtils::stripAnsiCodes($lines[0]), 'The selected label keeps the row; the indicator is suppressed.');
    }

    public function testWrappedRowsFitEveryWidth()
    {
        $list = new SelectListWidget([
            ['value' => 'a', 'label' => 'alpha beta gamma ★ étoile verified', 'description' => 'the first one'],
            ['value' => 'b', 'label' => '日本語のオプション with CJK and ascii mixed', 'description' => 'the second one'],
        ]);

        foreach ([60, 40, 20, 6, 4, 2, 1] as $columns) {
            foreach ($list->render(new RenderContext($columns, 24)) as $line) {
                $this->assertLessThanOrEqual($columns, AnsiUtils::visibleWidth($line), \sprintf('Every wrapped row fits in %d columns.', $columns));
            }
        }
    }

    /**
     * The selected row is prefixed with an arrow: three bytes, two columns.
     * Budgeting in bytes would cost that row two columns of description.
     */
    public function testSelectedRowGetsTheSameWidthBudgetAsTheOthers()
    {
        $description = str_repeat('D', 120);
        $list = new SelectListWidget([
            ['value' => 'alpha', 'label' => 'alpha', 'description' => $description],
            ['value' => 'beta', 'label' => 'beta', 'description' => $description],
        ]);

        $list->setSelectedIndex(0);
        $selected = $list->render(new RenderContext(60, 24));
        $list->setSelectedIndex(1);
        $unselected = $list->render(new RenderContext(60, 24));

        $this->assertStringContainsString('→ alpha', AnsiUtils::stripAnsiCodes($selected[0]));
        $this->assertStringContainsString('  alpha', AnsiUtils::stripAnsiCodes($unselected[0]));
        $this->assertSame(
            AnsiUtils::visibleWidth($unselected[0]),
            AnsiUtils::visibleWidth($selected[0]),
            'The selected first row is budgeted to the same width as the unselected first row of the same item.',
        );
    }

    public function testSelectedRowWithoutDescriptionGetsTheSameWidthBudget()
    {
        $label = str_repeat('L', 120);
        $list = new SelectListWidget([
            ['value' => 'alpha', 'label' => $label],
            ['value' => 'beta', 'label' => $label],
        ]);

        $list->setSelectedIndex(0);
        $selected = $list->render(new RenderContext(60, 24));
        $list->setSelectedIndex(1);
        $unselected = $list->render(new RenderContext(60, 24));

        $this->assertStringStartsWith('→ ', AnsiUtils::stripAnsiCodes($selected[0]));
        $this->assertStringStartsWith('  ', AnsiUtils::stripAnsiCodes($unselected[0]));
        $this->assertSame(
            AnsiUtils::visibleWidth($unselected[0]),
            AnsiUtils::visibleWidth($selected[0]),
            'The selected first row is budgeted to the same width as the unselected first row of the same item.',
        );
    }

    public function testKeybindingLabelsOmitToggleWithoutMultiselect()
    {
        $labels = $this->createTestList()->getKeybindingLabels();

        $this->assertArrayNotHasKey('choice_toggle', $labels);
        $this->assertSame('Select', $labels['select_confirm']);
    }

    public function testKeybindingLabelsIncludeToggleWithMultiselect()
    {
        $labels = $this->createTestList(multiselect: true)->getKeybindingLabels();

        $this->assertSame('Toggle', $labels['choice_toggle']);
    }

    public function testExplicitKeybindingLabelsWin()
    {
        $labels = $this->createTestList()->setKeybindingLabels(['select_confirm' => 'OK'])->getKeybindingLabels();

        $this->assertSame(['select_confirm' => 'OK'], $labels);
    }

    public function testPageDownDoesNotSkipUnseenWrappedOptions()
    {
        $items = [];
        for ($i = 0; $i < 10; ++$i) {
            $items[] = ['value' => 'v'.$i, 'label' => 'Item'.$i.' '.str_repeat('word ', 20)];
        }
        $list = new SelectListWidget($items, 5);

        $before = array_map(AnsiUtils::stripAnsiCodes(...), $list->render(new RenderContext(40, 8)));
        $this->assertStringContainsString('→ Item0', $before[0]);
        $this->assertStringContainsString('Item1', implode("\n", $before));
        $this->assertStringNotContainsString('Item2', implode("\n", $before));

        $list->handleInput("\x1b[6~");

        $after = array_map(AnsiUtils::stripAnsiCodes(...), $list->render(new RenderContext(40, 8)));
        $visible = implode("\n", $after);

        $this->assertStringContainsString('Item2', $visible, 'PageDown must bring the next unseen option into view.');
        $this->assertNotSame('v5', $list->getSelectedItem()['value'], 'PageDown must not jump to an option that was never shown.');
    }

    public function testPageUpDoesNotSkipUnseenWrappedOptions()
    {
        $items = [];
        for ($i = 0; $i < 10; ++$i) {
            $items[] = ['value' => 'v'.$i, 'label' => 'Item'.$i.' '.str_repeat('word ', 20)];
        }
        $list = new SelectListWidget($items, 5);
        $list->setSelectedIndex(5);

        $before = array_map(AnsiUtils::stripAnsiCodes(...), $list->render(new RenderContext(40, 8)));
        $this->assertStringContainsString('→ Item5', implode("\n", $before));
        $this->assertStringNotContainsString('Item2', implode("\n", $before));

        $list->handleInput("\x1b[5~");

        $after = array_map(AnsiUtils::stripAnsiCodes(...), $list->render(new RenderContext(40, 8)));
        $visible = implode("\n", $after);

        $this->assertStringContainsString('Item2', $visible, 'PageUp must bring a previously unseen option into view.');
        $this->assertNotSame('v0', $list->getSelectedItem()['value'], 'PageUp must not jump over unseen options to the first item.');
    }

    public function testPageDownInTallViewportStaysWithinMaxVisible()
    {
        $items = [];
        for ($i = 0; $i < 20; ++$i) {
            $items[] = ['value' => 'v'.$i, 'label' => 'Item'.$i];
        }
        $list = new SelectListWidget($items, 5);

        $list->render(new RenderContext(40, 24));
        $list->handleInput("\x1b[6~");

        $this->assertSame('v5', $list->getSelectedItem()['value']);
    }

    public function testMixedHeightPageDownAndPageUpKeepAdjacentUnseenItems()
    {
        $items = [
            ['value' => 'v0', 'label' => 'short0'],
            ['value' => 'v1', 'label' => 'Item1 '.str_repeat('word ', 20)],
            ['value' => 'v2', 'label' => 'short2'],
            ['value' => 'v3', 'label' => 'Item3 '.str_repeat('word ', 20)],
            ['value' => 'v4', 'label' => 'short4'],
            ['value' => 'v5', 'label' => 'short5'],
            ['value' => 'v6', 'label' => 'short6'],
        ];
        $list = new SelectListWidget($items, 5);

        $before = array_map(AnsiUtils::stripAnsiCodes(...), $list->render(new RenderContext(40, 8)));
        $this->assertStringContainsString('→ short0', implode("\n", $before));
        $this->assertStringContainsString('short2', implode("\n", $before));
        $this->assertStringNotContainsString('Item3', implode("\n", $before));

        $list->handleInput("\x1b[6~");
        $afterDown = array_map(AnsiUtils::stripAnsiCodes(...), $list->render(new RenderContext(40, 8)));
        $visibleDown = implode("\n", $afterDown);

        $this->assertSame('v3', $list->getSelectedItem()['value']);
        $this->assertStringContainsString('Item3', $visibleDown);
        $this->assertStringNotContainsString('→ short0', $visibleDown);

        $list->handleInput("\x1b[5~");
        $afterUp = array_map(AnsiUtils::stripAnsiCodes(...), $list->render(new RenderContext(40, 8)));
        $visibleUp = implode("\n", $afterUp);

        $this->assertSame('v0', $list->getSelectedItem()['value']);
        $this->assertStringContainsString('→ short0', $visibleUp);
        $this->assertStringContainsString('short2', $visibleUp);
    }

    public function testSelectListDoesNotPadTheLayoutByDefault()
    {
        $terminal = new VirtualTerminal(40, 8);
        $tui = new Tui(terminal: $terminal);
        $list = new SelectListWidget([
            ['value' => 'a', 'label' => 'alpha'],
            ['value' => 'b', 'label' => 'beta'],
        ], maxVisible: 5);
        $tui->add(new TextWidget('heading1'));
        $tui->add($list);
        $tui->add(new TextWidget('footer1'));

        try {
            $tui->start();
            $tui->processRender();

            $screen = new ScreenBuffer(40, 8);
            $screen->write($terminal->getOutput());
            $visible = array_map(rtrim(...), explode("\n", $screen->getScreen()));

            $this->assertFalse($list->isVerticallyExpanded());
            $this->assertSame('heading1', $visible[0]);
            $this->assertStringContainsString('→ alpha', $visible[1]);
            $this->assertStringContainsString('beta', $visible[2]);
            $this->assertSame('footer1', $visible[3]);
        } finally {
            $tui->stop();
        }
    }

    public function testExpandedSelectListPadsTheLayoutBelowItsContent()
    {
        $terminal = new VirtualTerminal(40, 8);
        $tui = new Tui(terminal: $terminal);
        $list = (new SelectListWidget([
            ['value' => 'a', 'label' => 'alpha'],
            ['value' => 'b', 'label' => 'beta'],
        ], maxVisible: 5))->expandVertically(true);
        $tui->add(new TextWidget('heading1'));
        $tui->add($list);
        $tui->add(new TextWidget('footer1'));

        try {
            $tui->start();
            $tui->processRender();

            $screen = new ScreenBuffer(40, 8);
            $screen->write($terminal->getOutput());
            $visible = array_map(rtrim(...), explode("\n", $screen->getScreen()));

            $this->assertTrue($list->isVerticallyExpanded());
            $this->assertSame('heading1', $visible[0]);
            $this->assertStringContainsString('→ alpha', $visible[1]);
            $this->assertStringContainsString('beta', $visible[2]);
            $this->assertSame(['', '', '', ''], \array_slice($visible, 3, 4));
            $this->assertSame('footer1', $visible[7]);
        } finally {
            $tui->stop();
        }
    }

    public function testCustomSelectedStyleIsRestoredAcrossWrappedAnsiLabelBoundary()
    {
        $label = "\x1b[31m".'alpha beta gamma delta epsilon zeta eta theta'."\x1b[39;49m";
        $list = new SelectListWidget([
            ['value' => 'a', 'label' => $label, 'description' => 'plain description text here'],
            ['value' => 'b', 'label' => 'Normal', 'description' => 'Normal'],
        ]);

        $terminal = new VirtualTerminal(80, 10);
        $tui = new Tui(terminal: $terminal);
        $tui->addStyleSheet(new StyleSheet([
            SelectListWidget::class.'::selected' => (new Style())->withBold()->withColor('cyan')->withBackground('blue'),
            SelectListWidget::class.'::selected:focus' => (new Style())->withBold()->withColor('cyan')->withBackground('blue'),
        ]));
        $tui->add($list);

        try {
            $tui->start();
            $lines = $list->render(new RenderContext(80, 10));
        } finally {
            $tui->stop();
        }

        $plain = AnsiUtils::stripAnsiCodes($lines[0]);
        $descCol = strpos($plain, 'plain');
        $this->assertNotFalse($descCol);
        $descCol = AnsiUtils::visibleWidth(substr($plain, 0, $descCol));
        $activeAtDescription = $this->activeCodesAtColumn($lines[0], $descCol);
        $this->assertStringNotContainsString('31', $activeAtDescription);
        $this->assertStringContainsString('1', $activeAtDescription);
        $this->assertStringContainsString('36', $activeAtDescription);
        $this->assertStringContainsString('44', $activeAtDescription);
    }

    public function testSuccessivePageDownDoesNotSkipItemsBeforeTallOption()
    {
        $items = [];
        for ($i = 0; $i < 15; ++$i) {
            $items[] = [
                'value' => 'v'.$i,
                'label' => 10 === $i ? str_repeat('word ', 40) : 'Item'.$i,
            ];
        }
        $list = new SelectListWidget($items, 5);
        $seen = [];

        for ($step = 0; $step < 3; ++$step) {
            $lines = array_map(AnsiUtils::stripAnsiCodes(...), $list->render(new RenderContext(40, 8)));
            foreach ($lines as $line) {
                if (preg_match('/Item(\d+)/', $line, $match)) {
                    $seen[(int) $match[1]] = true;
                }
            }
            if ('v10' === $list->getSelectedItem()['value']) {
                $seen[10] = true;
            }
            $list->handleInput("\x1b[6~");
        }

        $this->assertArrayHasKey(8, $seen, 'Successive PageDown must expose Item8 before the tall Item10 window.');
        $this->assertArrayHasKey(9, $seen, 'Successive PageDown must expose Item9 before or with the tall Item10 window.');
    }

    public function testSuccessivePageUpDoesNotSkipTallOption()
    {
        $items = [];
        for ($i = 0; $i < 15; ++$i) {
            $items[] = [
                'value' => 'v'.$i,
                'label' => 10 === $i ? str_repeat('word ', 40) : 'Item'.$i,
            ];
        }
        $list = new SelectListWidget($items, 5);
        $list->setSelectedIndex(12);
        $seen = [];

        for ($step = 0; $step < 3; ++$step) {
            $lines = array_map(AnsiUtils::stripAnsiCodes(...), $list->render(new RenderContext(40, 8)));
            foreach ($lines as $line) {
                if (preg_match('/Item(\d+)/', $line, $match)) {
                    $seen[(int) $match[1]] = true;
                }
            }
            if ('v10' === $list->getSelectedItem()['value']) {
                $seen[10] = true;
            }
            $list->handleInput("\x1b[5~");
        }

        $this->assertArrayHasKey(10, $seen, 'Successive PageUp from below the tall option must land on or show Item10 instead of jumping over it.');
    }

    public function testLabelOnlyWrappedAnsiDoesNotLeakIntoHorizontalSibling()
    {
        $label = "\x1b[31;42m".str_repeat('word ', 20)."\x1b[39;49m";
        $list = new SelectListWidget([
            ['value' => 'a', 'label' => $label],
            ['value' => 'b', 'label' => 'plain'],
        ], maxVisible: 5);
        $right = new TextWidget("RIGHT\nRIGHT\nRIGHT\nRIGHT\nRIGHT\nRIGHT\nRIGHT\nRIGHT");
        $root = (new ContainerWidget())->setStyle(new Style(direction: Direction::Horizontal));
        $root->add($list);
        $root->add($right);

        $frame = (new Renderer())->renderFrame($root, 40, 10)->toArray();
        $checkedRows = 0;
        foreach ($frame as $line) {
            $plain = AnsiUtils::stripAnsiCodes($line);
            if (!str_contains($plain, 'RIGHT')) {
                continue;
            }
            ++$checkedRows;
            $activeAtSibling = $this->activeCodesAtColumn($line, 20);
            $this->assertStringNotContainsString('31', $activeAtSibling);
            $this->assertStringNotContainsString('42', $activeAtSibling);
        }
        $this->assertGreaterThan(0, $checkedRows, 'Horizontal sibling pane must be present in the rendered frame.');
    }

    public function testDescriptionOnlyContinuationRowsCloseFieldLocalAnsi()
    {
        $list = new SelectListWidget([
            ['value' => 'a', 'label' => 'short', 'description' => "\x1b[31m".str_repeat('desc ', 20)."\x1b[39m"],
        ], maxVisible: 5);

        $lines = $list->render(new RenderContext(80, 10));
        $this->assertGreaterThan(1, \count($lines));

        foreach ($lines as $index => $line) {
            $tracker = new AnsiCodeTracker();
            $tracker->processText($line);
            $this->assertSame(
                '',
                $tracker->getActiveCodes(),
                \sprintf('Physical row %d must close description field styles before the line ends.', $index),
            );
        }
    }

    /**
     * @return iterable<string, array{0: string, 1: string}>
     */
    public static function wrappedAnsiLabelLeakProvider(): iterable
    {
        yield 'foreground' => ["\x1b[31m", '31'];
        yield 'background' => ["\x1b[42m", '42'];
    }

    #[DataProvider('wrappedAnsiLabelLeakProvider')]
    public function testWrappedAnsiLabelDoesNotLeakStyleIntoDescriptionCells(string $openCode, string $forbiddenParam)
    {
        $label = $openCode.'alpha beta gamma delta epsilon zeta eta theta'."\x1b[39;49m";
        $list = new SelectListWidget([
            ['value' => 'a', 'label' => $label, 'description' => 'plain description text here'],
            ['value' => 'b', 'label' => 'Normal', 'description' => 'Normal'],
        ]);

        $lines = $list->render(new RenderContext(80, 10));
        $plain = AnsiUtils::stripAnsiCodes($lines[0]);
        $descCol = strpos($plain, 'plain');
        $this->assertNotFalse($descCol);
        $descCol = AnsiUtils::visibleWidth(substr($plain, 0, $descCol));
        $activeAtDescription = $this->activeCodesAtColumn($lines[0], $descCol);
        $this->assertSame(
            "\x1b[1m",
            $activeAtDescription,
            \sprintf('Description cells may keep selected bold, but must not keep label SGR param %s (active=%s).', $forbiddenParam, json_encode($activeAtDescription)),
        );
    }

    public function testPageUpAfterSetItemsWithoutRenderStaysWithinCurrentItems()
    {
        $items = [];
        for ($i = 0; $i < 20; ++$i) {
            $items[] = ['value' => 'v'.$i, 'label' => 'Item'.$i];
        }
        $list = new SelectListWidget($items, 5);
        $list->setSelectedIndex(15);
        $list->render(new RenderContext(40, 8));

        $list->setItems(\array_slice($items, 0, 2));
        $list->handleInput("\x1b[5~");

        $this->assertNotNull($list->getSelectedItem());
        $this->assertSame('v0', $list->getSelectedItem()['value']);
    }

    public function testPageUpAfterFilterWithoutRenderStaysWithinFilteredItems()
    {
        $items = [];
        for ($i = 0; $i < 20; ++$i) {
            $items[] = [
                'value' => $i < 2 ? 'keep'.$i : 'drop'.$i,
                'label' => 'Item'.$i,
            ];
        }
        $list = new SelectListWidget($items, 5);
        $list->setSelectedIndex(15);
        $list->render(new RenderContext(40, 8));

        $list->setFilter('keep');
        $list->handleInput("\x1b[5~");

        $this->assertNotNull($list->getSelectedItem());
        $this->assertSame('keep0', $list->getSelectedItem()['value']);
    }

    public function testConsecutivePageDownWithoutRenderAdvancesUsingRefittedWindow()
    {
        $items = [];
        for ($i = 0; $i < 20; ++$i) {
            $items[] = ['value' => 'v'.$i, 'label' => 'Item'.$i];
        }
        $list = new SelectListWidget($items, 5);
        $list->render(new RenderContext(40, 8));

        $list->handleInput("\x1b[6~");
        $this->assertSame('v5', $list->getSelectedItem()['value']);

        $list->handleInput("\x1b[6~");
        $this->assertSame('v8', $list->getSelectedItem()['value'], 'A second PageDown before repaint must advance from a refitted window, not reuse the stale end.');
    }

    public function testBatchedPageDownAroundTallOptionDoesNotSkipNeighbors()
    {
        $items = [];
        for ($i = 0; $i < 15; ++$i) {
            $items[] = [
                'value' => 'v'.$i,
                'label' => 10 === $i ? str_repeat('word ', 40) : 'Item'.$i,
            ];
        }
        $list = new SelectListWidget($items, 5);
        $list->render(new RenderContext(40, 8));

        $selected = [];
        for ($step = 0; $step < 4; ++$step) {
            $list->handleInput("\x1b[6~");
            $selected[] = $list->getSelectedItem()['value'];
        }

        $this->assertContains('v8', $selected, 'Batched PageDown must select Item8 before jumping around the tall option.');
        $this->assertNotSame(['v5', 'v5', 'v5', 'v5'], $selected, 'Batched PageDown must keep advancing instead of reusing a stale window end.');

        $indexes = array_map(static fn (string $value): int => (int) substr($value, 1), $selected);
        for ($i = 1; $i < \count($indexes); ++$i) {
            $this->assertFalse(
                $indexes[$i - 1] < 8 && $indexes[$i] > 9,
                'Batched PageDown must not jump from before Item8 to after Item9 in one step.',
            );
        }
    }

    /**
     * @return array{SelectListWidget, Tui}
     */
    private function createTestListWithTui(bool $multiselect = false): array
    {
        $terminal = new VirtualTerminal(80, 24);
        $tui = new Tui(terminal: $terminal);
        $list = $this->createTestList($multiselect);
        $tui->add($list);

        return [$list, $tui];
    }

    private function activeCodesAtColumn(string $line, int $column): string
    {
        $tracker = new AnsiCodeTracker();
        foreach (AnsiUtils::walkCells($line) as $token) {
            if (0 === $token['width']) {
                $tracker->process($token['text']);
                continue;
            }
            if ($token['col'] === $column) {
                return $tracker->getActiveCodes();
            }
        }

        $this->fail(\sprintf('No visible cell found at column %d in line %s.', $column, json_encode($line)));
    }
}
