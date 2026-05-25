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
use Symfony\Component\Tui\Event\CancelEvent;
use Symfony\Component\Tui\Event\SelectEvent;
use Symfony\Component\Tui\Event\SettingChangeEvent;
use Symfony\Component\Tui\Render\RenderContext;
use Symfony\Component\Tui\Render\Renderer;
use Symfony\Component\Tui\Style\Border;
use Symfony\Component\Tui\Style\Padding;
use Symfony\Component\Tui\Style\Style;
use Symfony\Component\Tui\Terminal\VirtualTerminal;
use Symfony\Component\Tui\Tui;
use Symfony\Component\Tui\Widget\SelectListWidget;
use Symfony\Component\Tui\Widget\SettingItem;
use Symfony\Component\Tui\Widget\SettingsListWidget;

class SettingsListTest extends TestCase
{
    public function testRenderShowsItems()
    {
        $widget = new SettingsListWidget($this->createItems());
        $lines = $widget->render(new RenderContext(80, 24));

        $this->assertStringContainsString('Theme', $lines[0]);
    }

    public function testCycleValueRight()
    {
        [$widget, $tui] = $this->createWithTui();

        $this->assertSame('dark', $widget->getValue('theme'));

        // Right arrow cycles forward
        $widget->handleInput("\x1b[C");

        $this->assertSame('auto', $widget->getValue('theme'));
    }

    public function testCycleValueLeft()
    {
        [$widget, $tui] = $this->createWithTui();

        $this->assertSame('dark', $widget->getValue('theme'));

        // Left arrow cycles backward
        $widget->handleInput("\x1b[D");

        $this->assertSame('light', $widget->getValue('theme'));
    }

    public function testOnChangeCallback()
    {
        [$widget, $tui] = $this->createWithTui();

        $changedId = null;
        $changedValue = null;
        $tui->addListener(static function (SettingChangeEvent $e) use (&$changedId, &$changedValue) {
            $changedId = $e->getId();
            $changedValue = $e->getValue();
        });

        // Cycle theme value
        $widget->handleInput("\x1b[C");

        $this->assertSame('theme', $changedId);
        $this->assertSame('auto', $changedValue);
    }

    public function testOnCancelCallback()
    {
        [$widget, $tui] = $this->createWithTui();

        $cancelled = false;
        $tui->addListener(static function (CancelEvent $e) use (&$cancelled) {
            $cancelled = true;
        });

        // Escape
        $widget->handleInput("\x1b");

        $this->assertTrue($cancelled);
    }

    public function testNavigateDown()
    {
        [$widget, $tui] = $this->createWithTui();

        // Navigate down
        $widget->handleInput("\x1b[B");

        // Cycle the now-selected item (Font Size)
        $widget->handleInput("\x1b[C");

        $this->assertSame('large', $widget->getValue('fontSize'));
    }

    public function testSubmenuRenderedThroughRenderer()
    {
        // Create a settings widget with a submenu that has a border
        $submenuWidget = null;
        $items = [
            new SettingItem(
                id: 'model',
                label: 'Model',
                currentValue: 'gpt-4',
                submenu: static function (string $currentValue, callable $onDone) use (&$submenuWidget) {
                    $list = new SelectListWidget([
                        ['value' => 'gpt-4', 'label' => 'GPT-4'],
                        ['value' => 'claude', 'label' => 'Claude'],
                    ], 5);

                    // Events are wired by SettingsListWidget via the dispatcher

                    // Add a border to the submenu to verify it's applied through the Renderer
                    $list->setStyle(new Style(padding: new Padding(1, 2, 1, 2)));

                    $submenuWidget = $list;

                    return $list;
                },
            ),
        ];

        $widget = new SettingsListWidget($items);
        [$widget, $tui] = $this->createWithTui($widget);

        // Activate the submenu
        $widget->handleInput("\r");

        // Verify the submenu is now a child
        $this->assertCount(1, $widget->all());
        $this->assertSame($submenuWidget, $widget->all()[0]);

        // Render through the Renderer (which applies chrome)
        $renderer = new Renderer();
        $lines = $renderer->renderWidget($widget, new RenderContext(60, 20));

        // The submenu's padding should have been applied by the Renderer.
        // Without the fix, render() would call submenu->render() directly,
        // skipping the Renderer pipeline and its chrome application.
        // With padding (top: 1, left: 2), the first line should be blank padding
        // and content lines should have left padding
        $firstNonEmpty = null;
        foreach ($lines as $i => $line) {
            if ('' !== trim(AnsiUtils::stripAnsiCodes($line))) {
                $firstNonEmpty = $i;
                break;
            }
        }

        // With top padding of 1, the first non-empty line should be at index 1 or later
        $this->assertGreaterThanOrEqual(1, $firstNonEmpty, 'Submenu padding should be applied by the Renderer');
    }

    public function testSubmenuCloseSetsChildrenEmpty()
    {
        $onDoneCallback = null;
        $items = [
            new SettingItem(
                id: 'model',
                label: 'Model',
                currentValue: 'gpt-4',
                submenu: static function (string $currentValue, callable $onDone) use (&$onDoneCallback) {
                    $onDoneCallback = $onDone;

                    $list = new SelectListWidget([
                        ['value' => 'gpt-4', 'label' => 'GPT-4'],
                    ], 5);

                    // Events are wired by SettingsListWidget via the dispatcher

                    return $list;
                },
            ),
        ];

        $widget = new SettingsListWidget($items);
        [$widget, $tui] = $this->createWithTui($widget);

        // Open submenu
        $widget->handleInput("\r");
        $this->assertCount(1, $widget->all());

        // Close submenu via callback
        ($onDoneCallback)(null);
        $this->assertSame([], $widget->all());
    }

    public function testDetachClearsActiveSubmenu()
    {
        $items = [
            new SettingItem(
                id: 'model',
                label: 'Model',
                currentValue: 'gpt-4',
                submenu: static function (string $currentValue, callable $onDone) {
                    $list = new SelectListWidget([
                        ['value' => 'gpt-4', 'label' => 'GPT-4'],
                        ['value' => 'claude', 'label' => 'Claude'],
                    ], 5);

                    // Events are wired by SettingsListWidget via the dispatcher

                    return $list;
                },
            ),
        ];

        $widget = new SettingsListWidget($items);
        [$widget, $tui] = $this->createWithTui($widget);

        // Open submenu
        $widget->handleInput("\r");
        $this->assertCount(1, $widget->all());

        // Detach the settings widget (simulating overlay close)
        $tui->remove($widget);

        // The active submenu reference should be cleared
        $this->assertSame([], $widget->all());
    }

    public function testSubmenuListenersCleanedUpOnDetach()
    {
        $items = [
            new SettingItem(
                id: 'model',
                label: 'Model',
                currentValue: 'gpt-4',
                submenu: static fn (string $currentValue, callable $onDone) => new SelectListWidget([
                    ['value' => 'gpt-4', 'label' => 'GPT-4'],
                    ['value' => 'claude', 'label' => 'Claude'],
                ], 5),
            ),
        ];

        $widget = new SettingsListWidget($items);
        [$widget, $tui] = $this->createWithTui($widget);

        $dispatcher = $tui->getEventDispatcher();

        $selectBefore = $dispatcher->getListeners(SelectEvent::class);
        $cancelBefore = $dispatcher->getListeners(CancelEvent::class);

        // Open submenu; adds listeners to global dispatcher
        $widget->handleInput("\r");
        $this->assertCount(\count($selectBefore) + 1, $dispatcher->getListeners(SelectEvent::class));
        $this->assertCount(\count($cancelBefore) + 1, $dispatcher->getListeners(CancelEvent::class));

        // Detach the settings widget while submenu is open
        $tui->remove($widget);

        // Submenu listeners should be cleaned up
        $this->assertCount(\count($selectBefore), $dispatcher->getListeners(SelectEvent::class));
        $this->assertCount(\count($cancelBefore), $dispatcher->getListeners(CancelEvent::class));
    }

    public function testUpdateValue()
    {
        $widget = new SettingsListWidget($this->createItems());

        $widget->updateValue('theme', 'auto');

        $this->assertSame('auto', $widget->getValue('theme'));
    }

    public function testGetValueReturnsNullForUnknownId()
    {
        $widget = new SettingsListWidget($this->createItems());

        $this->assertNull($widget->getValue('nonexistent'));
    }

    /**
     * @return list<SettingItem>
     */
    private function createItems(): array
    {
        return [
            new SettingItem(
                id: 'theme',
                label: 'Theme',
                currentValue: 'dark',
                description: 'Color theme',
                values: ['light', 'dark', 'auto'],
            ),
            new SettingItem(
                id: 'fontSize',
                label: 'Font Size',
                currentValue: 'medium',
                values: ['small', 'medium', 'large'],
            ),
        ];
    }

    /**
     * @return array{SettingsListWidget, Tui}
     */
    private function createWithTui(?SettingsListWidget $widget = null): array
    {
        $terminal = new VirtualTerminal(80, 24);
        $tui = new Tui(terminal: $terminal);
        $widget ??= new SettingsListWidget($this->createItems());
        $tui->add($widget);

        return [$widget, $tui];
    }
}
