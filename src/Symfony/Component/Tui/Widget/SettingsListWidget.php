<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Tui\Widget;

use Symfony\Component\Tui\Ansi\AnsiUtils;
use Symfony\Component\Tui\Event\CancelEvent;
use Symfony\Component\Tui\Event\SelectEvent;
use Symfony\Component\Tui\Event\SettingChangeEvent;
use Symfony\Component\Tui\Input\Key;
use Symfony\Component\Tui\Input\Keybindings;
use Symfony\Component\Tui\Render\RenderContext;

/**
 * Settings panel with value cycling and submenus.
 *
 * @experimental
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class SettingsListWidget extends AbstractWidget implements FocusableInterface, ParentInterface
{
    use FocusableTrait;
    use KeybindingsTrait;

    private int $selectedIndex = 0;

    // Submenu state
    private (FocusableInterface&AbstractWidget)|null $activeSubmenu = null;

    /** @var list<array{string, callable}> Listeners to remove from the global dispatcher on cleanup */
    private array $submenuListeners = [];

    /**
     * @param list<SettingItem> $items
     */
    public function __construct(
        private array $items,
        private int $maxVisible = 10,
        ?Keybindings $keybindings = null,
    ) {
        if (null !== $keybindings) {
            $this->setKeybindings($keybindings);
        }
    }

    /**
     * Update the value for a setting.
     */
    public function updateValue(string $id, string $value): void
    {
        foreach ($this->items as $item) {
            if ($item->getId() === $id) {
                if ($item->getCurrentValue() !== $value) {
                    $item->setCurrentValue($value);
                    $this->invalidate();
                }
                break;
            }
        }
    }

    /**
     * Get the current value for a setting.
     */
    public function getValue(string $id): ?string
    {
        foreach ($this->items as $item) {
            if ($item->getId() === $id) {
                return $item->getCurrentValue();
            }
        }

        return null;
    }

    /**
     * @param callable(SettingChangeEvent): void $callback
     *
     * @return $this
     */
    public function onChange(callable $callback): static
    {
        return $this->on(SettingChangeEvent::class, $callback);
    }

    /**
     * @param callable(CancelEvent): void $callback
     *
     * @return $this
     */
    public function onCancel(callable $callback): static
    {
        return $this->on(CancelEvent::class, $callback);
    }

    public function all(): array
    {
        if (null !== $this->activeSubmenu) {
            return [$this->activeSubmenu];
        }

        return [];
    }

    public function handleInput(string $data): void
    {
        if (null !== $this->onInput && ($this->onInput)($data)) {
            return;
        }

        // If submenu is active, forward input to it
        if (null !== $this->activeSubmenu) {
            $this->activeSubmenu->handleInput($data);
            $this->invalidate();

            return;
        }

        if (!$this->items) {
            return;
        }

        $kb = $this->getKeybindings();

        // Navigation
        if ($kb->matches($data, 'select_up')) {
            $nextIndex = max(0, $this->selectedIndex - 1);
            if ($this->selectedIndex !== $nextIndex) {
                $this->selectedIndex = $nextIndex;
                $this->invalidate();
            }

            return;
        }

        if ($kb->matches($data, 'select_down')) {
            $nextIndex = min(\count($this->items) - 1, $this->selectedIndex + 1);
            if ($this->selectedIndex !== $nextIndex) {
                $this->selectedIndex = $nextIndex;
                $this->invalidate();
            }

            return;
        }

        if ($kb->matches($data, 'select_page_up')) {
            $nextIndex = max(0, $this->selectedIndex - $this->maxVisible);
            if ($this->selectedIndex !== $nextIndex) {
                $this->selectedIndex = $nextIndex;
                $this->invalidate();
            }

            return;
        }

        if ($kb->matches($data, 'select_page_down')) {
            $nextIndex = min(\count($this->items) - 1, $this->selectedIndex + $this->maxVisible);
            if ($this->selectedIndex !== $nextIndex) {
                $this->selectedIndex = $nextIndex;
                $this->invalidate();
            }

            return;
        }

        // Activate (cycle value or open submenu)
        if ($kb->matches($data, 'select_confirm') || ' ' === $data) {
            $this->activateCurrentItem();

            return;
        }

        // Cycle value forward (Right arrow)
        if ($kb->matches($data, 'cursor_right')) {
            $this->cycleValue(1);

            return;
        }

        // Cycle value backward (Left arrow)
        if ($kb->matches($data, 'cursor_left')) {
            $this->cycleValue(-1);

            return;
        }

        // Cancel
        if ($kb->matches($data, 'select_cancel')) {
            $this->dispatch(new CancelEvent($this));
        }
    }

    /**
     * @return string[]
     */
    public function render(RenderContext $context): array
    {
        $columns = $context->getColumns();

        // If submenu is active, render it through the Renderer pipeline
        // so its style (padding, border, background) is properly applied
        if (null !== $this->activeSubmenu && null !== ($widgetContext = $this->getContext())) {
            return $widgetContext->renderWidget($this->activeSubmenu, $context);
        }

        $lines = [];

        // Calculate visible range
        $startIndex = max(
            0,
            min(
                $this->selectedIndex - (int) floor($this->maxVisible / 2),
                \count($this->items) - $this->maxVisible,
            ),
        );
        $endIndex = min($startIndex + $this->maxVisible, \count($this->items));

        // Render items
        for ($i = $startIndex; $i < $endIndex; ++$i) {
            $item = $this->items[$i];
            $isSelected = $i === $this->selectedIndex;

            $line = $this->renderItem($item, $isSelected, $columns);
            $lines[] = $line;

            // Add description for selected item
            if ($isSelected && null !== $item->getDescription()) {
                $descLine = '  '.$this->applyElement('description', $item->getDescription());
                $descLine = AnsiUtils::truncateToWidth($descLine, $columns);
                $lines[] = $descLine;
            }
        }

        // Add hint
        $hint = $this->applyElement('hint', '  ↑↓ Navigate  Enter/Space Activate  Esc Cancel');
        $hint = AnsiUtils::truncateToWidth($hint, $columns);
        $lines[] = $hint;

        return $lines;
    }

    protected function onDetach(): void
    {
        $this->removeSubmenuListeners();
        $this->activeSubmenu = null;
    }

    /**
     * @return array<string, string[]>
     */
    protected static function getDefaultKeybindings(): array
    {
        return [
            'select_up' => [Key::UP],
            'select_down' => [Key::DOWN],
            'select_page_up' => [Key::PAGE_UP],
            'select_page_down' => [Key::PAGE_DOWN],
            'select_confirm' => [Key::ENTER],
            'select_cancel' => [Key::ESCAPE, 'ctrl+c'],
            'cursor_left' => [Key::LEFT, 'ctrl+b'],
            'cursor_right' => [Key::RIGHT, 'ctrl+f'],
        ];
    }

    /**
     * Cycle the current item's value forward or backward.
     */
    private function cycleValue(int $direction): void
    {
        if (!isset($this->items[$this->selectedIndex])) {
            return;
        }

        $item = $this->items[$this->selectedIndex];

        // Only cycle if item has predefined values
        if (!$item->hasValues()) {
            return;
        }

        $values = $item->getValues();
        $valueCount = \count($values);
        $currentIndex = array_search($item->getCurrentValue(), $values, true) ?: 0;

        // Calculate next index with wrapping
        $nextIndex = ($currentIndex + $direction + $valueCount) % $valueCount;
        $newValue = $values[$nextIndex];

        $item->setCurrentValue($newValue);
        $this->invalidate();
        $this->dispatch(new SettingChangeEvent($this, $item->getId(), $newValue));
    }

    private function renderItem(SettingItem $item, bool $isSelected, int $columns): string
    {
        $cursor = $isSelected ? '→ ' : '  ';
        $label = $isSelected
            ? $this->applyElement('label-selected', $item->getLabel())
            : $item->getLabel();
        $value = $isSelected
            ? $this->applyElement('value-selected', $item->getCurrentValue())
            : $this->applyElement('value', $item->getCurrentValue());

        // Calculate spacing
        $labelWidth = AnsiUtils::visibleWidth($cursor.$label);
        $valueWidth = AnsiUtils::visibleWidth($value);
        $spacing = max(1, $columns - $labelWidth - $valueWidth - 2);

        $line = $cursor.$label.str_repeat(' ', $spacing).$value;

        return AnsiUtils::truncateToWidth($line, $columns);
    }

    private function activateCurrentItem(): void
    {
        if (!isset($this->items[$this->selectedIndex])) {
            return;
        }

        $item = $this->items[$this->selectedIndex];

        // If item has predefined values, cycle through them
        if ($item->hasValues()) {
            $values = $item->getValues();
            $currentIndex = array_search($item->getCurrentValue(), $values, true);
            $nextIndex = (false === $currentIndex ? 0 : $currentIndex + 1) % \count($values);
            $newValue = $values[$nextIndex];

            $item->setCurrentValue($newValue);
            $this->invalidate();
            $this->dispatch(new SettingChangeEvent($this, $item->getId(), $newValue));

            return;
        }

        // If item has a submenu, open it
        if ($item->hasSubmenu()) {
            $onDone = function (?string $selectedValue) use ($item): void {
                $this->removeSubmenuListeners();

                if (null !== $this->activeSubmenu) {
                    $this->getContext()?->detachChild($this->activeSubmenu);
                }
                $this->activeSubmenu = null;

                if (null !== $selectedValue) {
                    $item->setCurrentValue($selectedValue);
                    $this->invalidate();
                    $this->dispatch(new SettingChangeEvent($this, $item->getId(), $selectedValue));
                } else {
                    $this->invalidate();
                }
            };

            $this->activeSubmenu = ($item->getSubmenu())(
                $item->getCurrentValue(),
                $onDone,
            );

            $submenu = $this->activeSubmenu;
            $context = $this->getContext();
            if (null !== $context) {
                $context->attachChild($this, $submenu);

                // Wire submenu events: when the inner widget dispatches
                // SelectEvent or CancelEvent, route to the onDone callback
                $dispatcher = $context->getEventDispatcher();
                $selectListener = static function (SelectEvent $e) use ($submenu, $onDone): void {
                    if ($e->getTarget() === $submenu) {
                        $onDone($e->getValue());
                    }
                };
                $cancelListener = static function (CancelEvent $e) use ($submenu, $onDone): void {
                    if ($e->getTarget() === $submenu) {
                        $onDone(null);
                    }
                };
                $dispatcher->addListener(SelectEvent::class, $selectListener);
                $dispatcher->addListener(CancelEvent::class, $cancelListener);
                $this->submenuListeners = [
                    [SelectEvent::class, $selectListener],
                    [CancelEvent::class, $cancelListener],
                ];
            }
            $this->invalidate();
        }
    }

    private function removeSubmenuListeners(): void
    {
        if (!$this->submenuListeners) {
            return;
        }

        $context = $this->getContext();
        if (null !== $context) {
            $dispatcher = $context->getEventDispatcher();
            foreach ($this->submenuListeners as [$eventClass, $listener]) {
                $dispatcher->removeListener($eventClass, $listener);
            }
        }
        $this->submenuListeners = [];
    }
}
