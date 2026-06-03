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
use Symfony\Component\Tui\Event\SelectionChangeEvent;
use Symfony\Component\Tui\Input\Key;
use Symfony\Component\Tui\Input\Keybindings;
use Symfony\Component\Tui\Render\RenderContext;

/**
 * Interactive selection list with keyboard navigation.
 *
 * Item `label`, `description`, and `value` are rendered to the terminal
 * as-is and are not sanitized. See {@see TextWidget} for the raw-passthrough
 * contract: never pass untrusted bytes here; sanitize upstream via
 * {@see Util\StringUtils::stripControlBytes()}.
 *
 * @experimental
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class SelectListWidget extends AbstractWidget implements FocusableInterface
{
    use FocusableTrait;
    use KeybindingsTrait;

    /** @var array<array{value: string, label: string, description?: string}> */
    private array $filteredItems;

    private int $selectedIndex = 0;
    private bool $selected = false;

    /**
     * @param array<array{value: string, label: string, description?: string}> $items
     */
    public function __construct(
        private array $items,
        private int $maxVisible = 5,
        ?Keybindings $keybindings = null,
    ) {
        $this->filteredItems = $items;
        if (null !== $keybindings) {
            $this->setKeybindings($keybindings);
        }
    }

    /**
     * @param array<array{value: string, label: string}> $items
     *
     * @return $this
     */
    public function setItems(array $items): static
    {
        $this->items = $items;
        $this->filteredItems = $items;
        $this->selectedIndex = 0;
        $this->invalidate();

        return $this;
    }

    /**
     * @return $this
     */
    public function setFilter(string $filter): static
    {
        $filter = strtolower($filter);

        $filteredItems = array_values(array_filter(
            $this->items,
            static fn ($item) => str_starts_with(strtolower($item['value']), $filter),
        ));

        if ($filteredItems !== $this->filteredItems) {
            $this->filteredItems = $filteredItems;
            $this->selectedIndex = 0;
            $this->invalidate();
        }

        return $this;
    }

    /**
     * @return $this
     */
    public function setSelectedIndex(int $index): static
    {
        $index = max(0, min($index, \count($this->filteredItems) - 1));
        if ($this->selectedIndex !== $index) {
            $this->selectedIndex = $index;
            $this->invalidate();
        }

        return $this;
    }

    /**
     * Get the currently selected item.
     *
     * @return array{value: string, label: string, description?: string}|null
     */
    public function getSelectedItem(): ?array
    {
        return $this->filteredItems[$this->selectedIndex] ?? null;
    }

    /**
     * Check if an item was selected (Enter pressed) vs cancelled (Escape pressed).
     */
    public function wasSelected(): bool
    {
        return $this->selected;
    }

    /**
     * @param callable(SelectEvent): void $callback
     *
     * @return $this
     */
    public function onSelect(callable $callback): static
    {
        return $this->on(SelectEvent::class, $callback);
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

    /**
     * @param callable(SelectionChangeEvent): void $callback
     *
     * @return $this
     */
    public function onSelectionChange(callable $callback): static
    {
        return $this->on(SelectionChangeEvent::class, $callback);
    }

    public function handleInput(string $data): void
    {
        if (null !== $this->onInput && ($this->onInput)($data)) {
            return;
        }

        $kb = $this->getKeybindings();

        if ($this->filteredItems) {
            // Up - wrap to bottom when at top
            if ($kb->matches($data, 'select_up')) {
                $this->selectedIndex = 0 === $this->selectedIndex ? \count($this->filteredItems) - 1 : $this->selectedIndex - 1;
                $this->notifySelectionChange();

                return;
            }

            // Down - wrap to top when at bottom
            if ($kb->matches($data, 'select_down')) {
                $this->selectedIndex = $this->selectedIndex === \count($this->filteredItems) - 1 ? 0 : $this->selectedIndex + 1;
                $this->notifySelectionChange();

                return;
            }

            if ($kb->matches($data, 'select_page_up') || $kb->matches($data, 'cursor_left')) {
                $this->selectedIndex = max(0, $this->selectedIndex - $this->maxVisible);
                $this->notifySelectionChange();

                return;
            }

            if ($kb->matches($data, 'select_page_down') || $kb->matches($data, 'cursor_right')) {
                $this->selectedIndex = min(\count($this->filteredItems) - 1, $this->selectedIndex + $this->maxVisible);
                $this->notifySelectionChange();

                return;
            }

            // Confirm selection
            if ($kb->matches($data, 'select_confirm')) {
                $this->confirmSelection();

                return;
            }
        }

        // Cancel
        if ($kb->matches($data, 'select_cancel')) {
            $this->selected = false;
            $this->dispatch(new CancelEvent($this));
        }
    }

    /**
     * @return string[]
     */
    public function render(RenderContext $context): array
    {
        $columns = $context->getColumns();
        $lines = [];

        // No items match filter
        if (!$this->filteredItems) {
            $line = $this->applyElement('no-match', '  No matching items');
            $lines[] = $line;

            return $lines;
        }

        // Calculate visible range with scrolling
        $startIndex = max(
            0,
            min(
                $this->selectedIndex - (int) floor($this->maxVisible / 2),
                \count($this->filteredItems) - $this->maxVisible,
            ),
        );
        $endIndex = min($startIndex + $this->maxVisible, \count($this->filteredItems));

        // Compute max label width from visible items for alignment
        $maxLabelWidth = 0;
        for ($i = $startIndex; $i < $endIndex; ++$i) {
            $maxLabelWidth = max($maxLabelWidth, AnsiUtils::visibleWidth($this->filteredItems[$i]['label']));
        }
        $labelColumnWidth = min(30, $maxLabelWidth);

        // Render visible items
        for ($i = $startIndex; $i < $endIndex; ++$i) {
            $item = $this->filteredItems[$i];
            $isSelected = $i === $this->selectedIndex;
            $description = isset($item['description']) ? $this->normalizeDescription($item['description']) : null;
            $line = $this->renderItem($item, $isSelected, $description, $columns, $labelColumnWidth);
            $lines[] = $line;
        }

        // Add scroll indicator if needed
        if ($startIndex > 0 || $endIndex < \count($this->filteredItems)) {
            $scrollText = \sprintf('  (%d/%d)', $this->selectedIndex + 1, \count($this->filteredItems));
            $line = $this->applyElement('scroll-info', AnsiUtils::truncateToWidth($scrollText, $columns - 2, ''));
            $lines[] = $line;
        }

        return $lines;
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
     * @param array{value: string, label: string, description?: string} $item
     */
    private function renderItem(array $item, bool $isSelected, ?string $description, int $columns, int $labelColumnWidth): string
    {
        $displayValue = $item['label'];
        $alignedWidth = $labelColumnWidth + 2;

        if ($isSelected) {
            $prefix = '→ ';
            $selectedStyle = $this->resolveElement('selected');

            if (null !== $description && $columns > 40) {
                $maxValueColumns = min($labelColumnWidth, $columns - \strlen($prefix) - 4);
                $truncatedValue = AnsiUtils::truncateToWidth($displayValue, $maxValueColumns, '');
                $spacing = str_repeat(' ', max(1, $alignedWidth - AnsiUtils::visibleWidth($truncatedValue)));

                $descriptionStart = \strlen($prefix) + AnsiUtils::visibleWidth($truncatedValue) + \strlen($spacing);
                $remainingColumns = $columns - $descriptionStart - 2;

                if ($remainingColumns > 10) {
                    $truncatedDesc = AnsiUtils::truncateToWidth($description, $remainingColumns, '');

                    return $selectedStyle->apply("→ {$truncatedValue}{$spacing}{$truncatedDesc}");
                }
            }

            $maxColumns = $columns - \strlen($prefix) - 2;

            return $selectedStyle->apply($prefix.AnsiUtils::truncateToWidth($displayValue, $maxColumns, ''));
        }

        // Non-selected item
        $prefix = '  ';

        if (null !== $description && $columns > 40) {
            $maxValueColumns = min($labelColumnWidth, $columns - \strlen($prefix) - 4);
            $truncatedValue = AnsiUtils::truncateToWidth($displayValue, $maxValueColumns, '');
            $spacing = str_repeat(' ', max(1, $alignedWidth - AnsiUtils::visibleWidth($truncatedValue)));

            $descriptionStart = \strlen($prefix) + AnsiUtils::visibleWidth($truncatedValue) + \strlen($spacing);
            $remainingColumns = $columns - $descriptionStart - 2;

            if ($remainingColumns > 10) {
                $truncatedDesc = AnsiUtils::truncateToWidth($description, $remainingColumns, '');
                $labelText = $this->applyElement('label', $truncatedValue);
                $descText = $this->applyElement('description', $spacing.$truncatedDesc);

                return $prefix.$labelText.$descText;
            }
        }

        $maxColumns = $columns - \strlen($prefix) - 2;

        return $prefix.AnsiUtils::truncateToWidth($displayValue, $maxColumns, '');
    }

    private function normalizeDescription(string $description): string
    {
        // Convert multiline to single line
        return trim(preg_replace('/[\r\n]+/', ' ', $description));
    }

    private function confirmSelection(): void
    {
        $this->selected = true;
        if (null !== $selectedItem = $this->filteredItems[$this->selectedIndex] ?? null) {
            $this->dispatch(new SelectEvent($this, $selectedItem));
        }
    }

    private function notifySelectionChange(): void
    {
        $this->invalidate();
        if (null !== $selectedItem = $this->filteredItems[$this->selectedIndex] ?? null) {
            $this->dispatch(new SelectionChangeEvent($this, $selectedItem));
        }
    }
}
