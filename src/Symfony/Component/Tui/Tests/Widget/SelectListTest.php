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
use Symfony\Component\Tui\Ansi\AnsiUtils;
use Symfony\Component\Tui\Event\CancelEvent;
use Symfony\Component\Tui\Event\SelectEvent;
use Symfony\Component\Tui\Event\SelectionChangeEvent;
use Symfony\Component\Tui\Render\RenderContext;
use Symfony\Component\Tui\Terminal\VirtualTerminal;
use Symfony\Component\Tui\Tui;
use Symfony\Component\Tui\Widget\SelectListWidget;

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

    private function createTestList(): SelectListWidget
    {
        $items = [
            ['value' => 'opt1', 'label' => 'Option 1', 'description' => 'First option'],
            ['value' => 'opt2', 'label' => 'Option 2', 'description' => 'Second option'],
            ['value' => 'opt3', 'label' => 'Option 3', 'description' => 'Third option'],
        ];

        return new SelectListWidget($items, 5);
    }

    /**
     * @return array{SelectListWidget, Tui}
     */
    private function createTestListWithTui(): array
    {
        $terminal = new VirtualTerminal(80, 24);
        $tui = new Tui(terminal: $terminal);
        $list = $this->createTestList();
        $tui->add($list);

        return [$list, $tui];
    }
}
