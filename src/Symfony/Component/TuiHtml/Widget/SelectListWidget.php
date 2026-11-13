<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\TuiHtml\Widget;

use Symfony\Component\Tui\Ansi\AnsiCodeTracker;
use Symfony\Component\Tui\Ansi\AnsiUtils;
use Symfony\Component\Tui\Ansi\Bidi;
use Symfony\Component\Tui\Ansi\TextWrapper;
use Symfony\Component\Tui\Event\CancelEvent;
use Symfony\Component\Tui\Input\Key;
use Symfony\Component\Tui\Input\Keybindings;
use Symfony\Component\Tui\Widget\AbstractWidget;
use Symfony\Component\Tui\Widget\FocusableInterface;
use Symfony\Component\Tui\Widget\FocusableTrait;
use Symfony\Component\Tui\Widget\KeybindingsTrait;
use Symfony\Component\Tui\Widget\Util\StringUtils;
use Symfony\Component\Tui\Widget\VerticallyExpandableInterface;
use Symfony\Component\TuiHtml\Event\MultiSelectEvent;
use Symfony\Component\TuiHtml\Event\SelectEvent;
use Symfony\Component\TuiHtml\Event\SelectionChangeEvent;
use Symfony\Component\TuiHtml\Event\SelectionToggleEvent;
use Symfony\Component\TuiHtml\Internal\ContextBridge;
use Symfony\Component\TuiHtml\Internal\StyleBridge;
use Symfony\Component\TuiHtml\Render\RenderContext;
use Symfony\Component\TuiHtml\Style\Style;
use Symfony\Component\TuiHtml\Widget\Text\ElementContentInterface;

/**
 * Interactive selection list with keyboard navigation.
 *
 * In single-select mode (default), Enter confirms the highlighted item.
 * In multiselect mode, Space toggles the highlighted item and Enter confirms
 * all checked items.
 *
 * Item `label`, `description`, and `value` are rendered to the terminal
 * as-is and are not sanitized. See {@see TextWidget} for the raw-passthrough
 * contract: never pass untrusted bytes here; sanitize upstream via
 * {@see StringUtils::stripControlBytes()}.
 *
 * @experimental
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class SelectListWidget extends AbstractWidget implements FocusableInterface, VerticallyExpandableInterface, ElementContentInterface
{
    use FocusableTrait;
    use KeybindingsTrait;
    /** The marker of the selected item, styled by the checkmark sub-element, null for the default one */
    private ?string $checkmark = null;
    /** @var list<string> The labels the items are drawn with, see setLabels() */
    private array $displayLabels = [];
    /** @var array{?string, ?Style} The checkmark of the dropdown the list is in: its content and style */
    private array $outerCheckmark = [null, null];
    /** @var list<int> */
    private array $filteredItemIndices;
    private int $selectedIndex = 0;
    private bool $selected = false;
    private bool $verticallyExpanded = false;
    private int $lastWindowStart = 0;
    private int $lastWindowEnd = 0;
    private int $lastRenderColumns = 0;
    private int $lastRenderRows = 0;

    /**
     * @param list<array{value: string, label: string, description?: string, checked?: bool, group?: string}> $items
     */
    public function __construct(private array $items, private int $maxVisible = 5, private bool $multiselect = false, ?Keybindings $keybindings = null)
    {
        $this->resetFilteredItems();
        if (null !== $keybindings) {
            $this->setKeybindings($keybindings);
        }
    }

    /**
     * @return $this
     */
    public function expandVertically(bool $expand): static
    {
        if ($this->verticallyExpanded !== $expand) {
            $this->verticallyExpanded = $expand;
            $this->invalidate();
        }

        return $this;
    }

    public function isVerticallyExpanded(): bool
    {
        return $this->verticallyExpanded;
    }

    /**
     * @param list<array{value: string, label: string, description?: string, checked?: bool, group?: string}> $items
     *
     * @return $this
     */
    public function setItems(array $items): static
    {
        $this->items = $items;
        $this->displayLabels = [];
        $this->resetFilteredItems();
        $this->selectedIndex = 0;
        $this->invalidateFittedWindow();
        $this->invalidate();

        return $this;
    }

    /**
     * Draws the items with other labels, in order, like the styled content of <option>s; the labels of the items,
     * which events carry, stay as they are.
     *
     * @param list<string> $labels
     *
     * @internal
     */
    public function setLabels(array $labels): void
    {
        if ($labels !== $this->displayLabels) {
            $this->displayLabels = $labels;
            $this->invalidateFittedWindow();
            $this->invalidate();
        }
    }

    /**
     * The label an item is drawn with: its styled one, or its label.
     *
     * @internal
     */
    public function getDisplayLabel(int $index): ?string
    {
        return $this->displayLabels[$index] ?? $this->items[$index]['label'] ?? null;
    }

    /**
     * @return $this
     */
    public function setFilter(string $filter): static
    {
        $filter = strtolower($filter);
        $filteredItemIndices = array_keys(array_filter($this->items, static fn ($item) => str_starts_with(strtolower($item['value']), $filter)));
        if ($filteredItemIndices !== $this->filteredItemIndices) {
            $this->filteredItemIndices = $filteredItemIndices;
            $this->selectedIndex = 0;
            $this->invalidateFittedWindow();
            $this->invalidate();
        }

        return $this;
    }

    /**
     * @return $this
     */
    public function setSelectedIndex(int $index): static
    {
        $index = max(0, min($index, \count($this->filteredItemIndices) - 1));
        if ($this->selectedIndex !== $index) {
            $this->selectedIndex = $index;
            $this->invalidate();
        }

        return $this;
    }

    /**
     * @return list<array{value: string, label: string, description?: string, checked?: bool, group?: string}>
     */
    public function getItems(): array
    {
        return array_values($this->items);
    }

    /**
     * The index of the highlighted item among the visible (filtered) ones.
     */
    public function getSelectedIndex(): int
    {
        return $this->selectedIndex;
    }

    /**
     * Get the currently selected item.
     *
     * @return array{value: string, label: string, description?: string, checked?: bool, group?: string}|null
     */
    public function getSelectedItem(): ?array
    {
        return $this->getFilteredItem($this->selectedIndex);
    }

    /**
     * @return list<array{value: string, label: string, description?: string, checked?: bool, group?: string}>
     */
    public function getSelectedItems(): array
    {
        if (!$this->multiselect) {
            return [];
        }

        return array_values(array_filter($this->items, static fn (array $item) => $item['checked'] ?? false));
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
     * @param callable(MultiSelectEvent): void $callback
     *
     * @return $this
     */
    public function onMultiSelect(callable $callback): static
    {
        return $this->on(MultiSelectEvent::class, $callback);
    }

    /**
     * @param callable(SelectionToggleEvent): void $callback
     *
     * @return $this
     */
    public function onSelectionToggle(callable $callback): static
    {
        return $this->on(SelectionToggleEvent::class, $callback);
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
        if ($this->filteredItemIndices) {
            // Up - wrap to bottom when at top
            if ($kb->matches($data, 'select_up')) {
                $this->selectedIndex = 0 === $this->selectedIndex ? \count($this->filteredItemIndices) - 1 : $this->selectedIndex - 1;
                $this->notifySelectionChange();

                return;
            }
            // Down - wrap to top when at bottom
            if ($kb->matches($data, 'select_down')) {
                $this->selectedIndex = $this->selectedIndex === \count($this->filteredItemIndices) - 1 ? 0 : $this->selectedIndex + 1;
                $this->notifySelectionChange();

                return;
            }
            if ($kb->matches($data, 'select_page_up') || $kb->matches($data, 'cursor_left')) {
                $this->selectedIndex = $this->pageToIndex(-1);
                $this->notifySelectionChange();

                return;
            }
            if ($kb->matches($data, 'select_page_down') || $kb->matches($data, 'cursor_right')) {
                $this->selectedIndex = $this->pageToIndex(1);
                $this->notifySelectionChange();

                return;
            }
            if ($this->multiselect && $kb->matches($data, 'choice_toggle')) {
                $this->toggleCurrentItem();

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
    public function render(\Symfony\Component\Tui\Render\RenderContext|RenderContext $context): array
    {
        $context = ContextBridge::computed($context);
        $columns = $context->getColumns();
        $this->lastRenderColumns = max(1, $columns);
        $this->lastRenderRows = max(1, $context->getRows());
        $lines = [];
        // No items match filter
        if (!$this->filteredItemIndices) {
            $line = StyleBridge::applyElement($this, 'no-match', '  No matching items');
            $lines[] = AnsiUtils::truncateToWidth($line, $columns, '');

            return $lines;
        }
        // Render visible items
        // Items may wrap across several physical rows, so fit the logical
        // window against the context rows before emitting anything.
        [$lines, $startIndex, $endIndex, $indicatorRoom] = $this->renderWindow($columns, $context->getRows());
        $this->lastWindowStart = $startIndex;
        $this->lastWindowEnd = $endIndex;
        // Add scroll indicator if needed
        if ($indicatorRoom && ($startIndex > 0 || $endIndex < \count($this->filteredItemIndices))) {
            $scrollText = \sprintf('  (%d/%d)', $this->selectedIndex + 1, \count($this->filteredItemIndices));
            $line = StyleBridge::applyElement($this, 'scroll-info', AnsiUtils::truncateToWidth($scrollText, $columns - 2, ''));
            $lines[] = $line;
        }

        return $lines;
    }

    /**
     * @return array<string, string[]>
     */
    protected static function getDefaultKeybindings(): array
    {
        return ['select_up' => [Key::UP], 'select_down' => [Key::DOWN], 'select_page_up' => [Key::PAGE_UP], 'select_page_down' => [Key::PAGE_DOWN], 'select_confirm' => [Key::ENTER], 'select_cancel' => [Key::ESCAPE, 'ctrl+c'], 'cursor_left' => [Key::LEFT, 'ctrl+b'], 'cursor_right' => [Key::RIGHT, 'ctrl+f'], 'choice_toggle' => [Key::SPACE]];
    }

    /**
     * @return array<string, string>
     */
    public function getKeybindingLabels(): array
    {
        if (null !== $this->keybindingLabels) {
            return $this->keybindingLabels;
        }
        $labels = static::getDefaultKeybindingLabels();
        if (!$this->multiselect) {
            unset($labels['choice_toggle']);
        }

        return $labels;
    }

    /**
     * @return array<string, string>
     */
    protected static function getDefaultKeybindingLabels(): array
    {
        return ['select_down' => 'Down', 'select_up' => 'Up', 'select_page_down' => 'Page Down', 'select_page_up' => 'Page Up', 'choice_toggle' => 'Toggle', 'select_confirm' => 'Select', 'select_cancel' => 'Cancel'];
    }

    public function getContentElements(): array
    {
        return ['checkmark'];
    }

    /**
     * Sets the checkmark of the dropdown the list is in, under the one of the list itself.
     *
     * @internal
     */
    public function setOuterCheckmark(?string $content, ?Style $style): void
    {
        $content = null !== $content ? StringUtils::stripControlBytes(StringUtils::sanitizeUtf8($content)) : null;
        if ($content !== $this->outerCheckmark[0] || $style != $this->outerCheckmark[1]) {
            $this->outerCheckmark = [$content, $style];
            $this->invalidate();
        }
    }

    public function setElementContent(string $element, ?string $content): void
    {
        // Content from a style sheet is text: no control bytes, which would write to the terminal
        $content = null !== $content ? StringUtils::stripControlBytes(StringUtils::sanitizeUtf8($content)) : null;
        if ('checkmark' === $element && $content !== $this->checkmark) {
            $this->checkmark = $content;
            $this->invalidate();
        }
    }

    /**
     * Render the logical window as physical rows fitted to the context.
     *
     * Wrapped items consume a variable number of rows, so trailing items
     * are dropped and leading items are trimmed until the selected item
     * fits inside the budget; a selected item taller than the budget is
     * clamped to its first rows. One row is reserved for the scroll
     * indicator only when items actually fall outside the fitted window,
     * so a window that fits exactly renders without an indicator.
     *
     * A one-row viewport has no room next to the selection, so the
     * indicator is suppressed and its row goes to the selected label.
     *
     * @return array{0: list<string>, 1: int, 2: int, 3: bool} the rendered rows, the fitted [start, end) range and whether a row is left for the indicator
     */
    private function renderWindow(int $columns, int $contextRows): array
    {
        $startIndex = max(0, min($this->selectedIndex - (int) floor($this->maxVisible / 2), \count($this->filteredItemIndices) - $this->maxVisible));
        $endIndex = min($startIndex + $this->maxVisible, \count($this->filteredItemIndices));
        $maxLabelWidth = 0;
        for ($i = $startIndex; $i < $endIndex; ++$i) {
            $maxLabelWidth = max($maxLabelWidth, AnsiUtils::visibleWidth($this->getDisplayLabel($this->filteredItemIndices[$i]) ?? ''));
        }
        $labelColumnWidth = min(30, $maxLabelWidth);
        $rowsByIndex = [];
        for ($i = $startIndex; $i < $endIndex; ++$i) {
            $item = $this->getFilteredItem($i);
            $item['label'] = $this->getDisplayLabel($this->filteredItemIndices[$i]) ?? $item['label'];
            $description = isset($item['description']) ? $this->normalizeDescription($item['description']) : null;
            $rowsByIndex[$i] = $this->renderItemRows($item, $i === $this->selectedIndex, $description, $columns, $labelColumnWidth);
            // The first item of a group carries its heading, like <optgroup>: headings are not items
            $group = $item['group'] ?? null;
            if (null !== $group && $group !== (0 === $i ? null : $this->getFilteredItem($i - 1)['group'] ?? null)) {
                array_unshift($rowsByIndex[$i], StyleBridge::applyElement($this, 'group', AnsiUtils::truncateToWidth($group, $columns, '…')));
            }
        }
        $total = \count($this->filteredItemIndices);
        $fit = function (int $budget) use ($rowsByIndex, $startIndex, $endIndex): array {
            // Keep every item through the selection first, then drop trailing
            // items once the budget is spent.
            $end = $endIndex;
            $used = 0;
            foreach ($rowsByIndex as $i => $rows) {
                if ($i > $this->selectedIndex && $used + \count($rows) > $budget) {
                    $end = $i;
                    break;
                }
                $used += \count($rows);
            }
            // Trim leading items while they push the selection out of view.
            $start = $startIndex;
            while ($start < $this->selectedIndex && $used > $budget) {
                $used -= \count($rowsByIndex[$start]);
                ++$start;
            }
            // After reclaiming rows from leading items, try trailing items
            // that were dropped before that trim.
            while ($end < $endIndex && $used + \count($rowsByIndex[$end]) <= $budget) {
                $used += \count($rowsByIndex[$end]);
                ++$end;
            }
            // The selected item alone exceeds the budget: clamp it to its
            // first rows so the arrow and the label start stay visible.
            $clamp = max(0, $used - $budget);

            return [$start, $end, $clamp];
        };
        [$start, $end, $clamp] = $fit(max(1, $contextRows));
        $indicatorRoom = $contextRows >= 2;
        if ($indicatorRoom && ($start > 0 || $end < $total)) {
            // Something scrolled out, so one row goes to the indicator.
            [$start, $end, $clamp] = $fit(max(1, $contextRows - 1));
        }
        $lines = [];
        for ($i = $start; $i < $end; ++$i) {
            $rows = $rowsByIndex[$i];
            if ($i === $start && $clamp > 0) {
                $rows = \array_slice($rows, 0, \count($rows) - $clamp);
            }
            // The prefix is wider than very narrow widgets, so clamp every
            // physical row to the pane width.
            foreach ($rows as $row) {
                $lines[] = AnsiUtils::truncateToWidth($row, $columns, '');
            }
        }

        return [$lines, $start, $end, $indicatorRoom];
    }

    /**
     * Render one item with label and description wrapped independently in
     * their side-by-side columns. Continuation rows keep the arrow only on
     * the first physical row and align under each column.
     *
     * @param array{value: string, label: string, description?: string, checked?: bool, group?: string} $item
     *
     * @return list<string>
     */
    private function renderItemRows(array $item, bool $isSelected, ?string $description, int $columns, int $labelColumnWidth): array
    {
        $checkbox = $this->multiselect ? $item['checked'] ?? false ? '[x] ' : '[ ] ' : '';
        $selectedStyle = $isSelected ? StyleBridge::element($this, 'selected') : null;
        // The marker of the selected item is the checkmark sub-element: its content and style, over the selected one
        $marker = $this->checkmark ?? $this->outerCheckmark[0] ?? '→';
        $markerWidth = AnsiUtils::visibleWidth($marker);
        $markerStyle = StyleBridge::element($this, 'checkmark');
        if (null !== $this->outerCheckmark[1] && 'x' === $markerStyle->apply('x')) {
            $markerStyle = $this->outerCheckmark[1];
        }
        if ($isSelected && '' !== $marker && $marker !== $styled = $markerStyle->apply($marker)) {
            $marker = $styled."\x1b[0m".$selectedStyle?->getAnsiRestore();
        }
        $prefix = ('' === $marker ? '' : ($isSelected ? $marker : str_repeat(' ', $markerWidth)).' ').$checkbox;
        $prefixWidth = AnsiUtils::visibleWidth($prefix);
        // Keep the existing side-by-side layout when there is room for a
        // description column; otherwise fall back to a full-width label.
        $showDescription = null !== $description && $columns > 40;
        $labelWidth = max(1, min($labelColumnWidth, $columns - $prefixWidth - 4));
        $alignedWidth = $labelWidth + 2;
        $descriptionStart = $prefixWidth + $alignedWidth;
        $descriptionWidth = $columns - $descriptionStart - 2;
        if (!$showDescription || $descriptionWidth <= 10) {
            // A label that fits is not wrapped, the list as wide as its widest label measures; a longer one keeps
            // two columns clear at the end of its lines
            $fits = AnsiUtils::visibleWidth($item['label']) <= $columns - $prefixWidth;
            $labelWidth = max(1, $columns - $prefixWidth - ($fits ? 0 : 2));
            $alignedWidth = 0;
            $descriptionRows = [];
        } else {
            $descriptionRows = TextWrapper::wrapTextWithAnsi($description, max(1, $descriptionWidth));
        }
        $labelRows = TextWrapper::wrapTextWithAnsi($item['label'], $labelWidth);
        // Right-to-left labels in visual order, their Arabic letters joined, like the text of a page
        if (Bidi::isAvailable() && Bidi::hasRightToLeft($item['label'])) {
            $level = Bidi::direction($item['label']) ?? 0;
            $labelRows = array_map(static fn (string $row) => Bidi::reorderLine($row, $level), $labelRows);
        }
        $rowCount = max(\count($labelRows), \count($descriptionRows));
        $rows = [];
        for ($i = 0; $i < $rowCount; ++$i) {
            $labelRow = $labelRows[$i] ?? '';
            $descriptionRow = $descriptionRows[$i] ?? '';
            $rowPrefix = 0 === $i ? $prefix : str_repeat(' ', $prefixWidth);
            if ('' === $descriptionRow) {
                $content = '' !== $labelRow && null === $selectedStyle ? StyleBridge::applyElement($this, 'label', $labelRow) : $labelRow;
                $row = $rowPrefix.$content.$this->fieldStyleBoundary($content, $selectedStyle);
                $rows[] = null !== $selectedStyle ? $selectedStyle->apply($row) : $row;
                continue;
            }
            // Pad to the shared label column so descriptions stay aligned
            // across items, matching the previous single-row layout.
            $labelPadding = str_repeat(' ', max(1, $alignedWidth - AnsiUtils::visibleWidth($labelRow)));
            $labelContent = '' !== $labelRow && null === $selectedStyle ? StyleBridge::applyElement($this, 'label', $labelRow) : $labelRow;
            $labelBoundary = $this->fieldStyleBoundary($labelContent, $selectedStyle);
            if (null !== $selectedStyle) {
                $row = $rowPrefix.$labelRow.$labelBoundary.$labelPadding.$descriptionRow;
                $rows[] = $selectedStyle->apply($row.$this->fieldStyleBoundary($row, $selectedStyle));
                continue;
            }
            // Keep the inter-column gap outside the description style so the
            // gray color does not paint the alignment spaces.
            $row = $rowPrefix.$labelContent.$labelBoundary.$labelPadding.StyleBridge::applyElement($this, 'description', $descriptionRow);
            $rows[] = $row.$this->fieldStyleBoundary($row, null);
        }

        return $rows;
    }

    /**
     * Move selection to the first item after the fitted window (PageDown)
     * or the last item before it (PageUp). Fall back to maxVisible steps
     * before the first render. Clamp at the ends.
     */
    private function pageToIndex(int $direction): int
    {
        $last = \count($this->filteredItemIndices) - 1;
        if ($last <= 0) {
            return 0;
        }
        $this->ensureFittedWindow();
        if ($this->lastWindowEnd > $this->lastWindowStart) {
            if ($direction > 0) {
                return min($last, $this->lastWindowEnd);
            }

            return max(0, min($last, $this->lastWindowStart - 1));
        }
        $step = max(1, $this->maxVisible);
        if ($direction > 0) {
            return min($last, $this->selectedIndex + $step);
        }

        return max(0, $this->selectedIndex - $step);
    }

    /**
     * Drop cached fitted-window bounds so the next page action must rebuild
     * them for the current items and selection.
     */
    private function invalidateFittedWindow(): void
    {
        $this->lastWindowStart = 0;
        $this->lastWindowEnd = 0;
    }

    /**
     * Rebuild fitted window bounds from the last rendered viewport when the
     * cache is missing or no longer covers the current selection. Reuses the
     * same renderWindow() fitter as paint.
     */
    private function ensureFittedWindow(): void
    {
        $total = \count($this->filteredItemIndices);
        if ($total <= 0) {
            $this->invalidateFittedWindow();

            return;
        }
        $this->selectedIndex = max(0, min($this->selectedIndex, $total - 1));
        $cacheValid = $this->lastWindowEnd > $this->lastWindowStart && $this->lastWindowStart >= 0 && $this->lastWindowEnd <= $total && $this->selectedIndex >= $this->lastWindowStart && $this->selectedIndex < $this->lastWindowEnd && $this->lastRenderColumns > 0 && $this->lastRenderRows > 0;
        if ($cacheValid) {
            return;
        }
        if ($this->lastRenderColumns <= 0 || $this->lastRenderRows <= 0) {
            $this->invalidateFittedWindow();

            return;
        }
        [, $startIndex, $endIndex] = $this->renderWindow($this->lastRenderColumns, $this->lastRenderRows);
        $this->lastWindowStart = $startIndex;
        $this->lastWindowEnd = $endIndex;
    }

    /**
     * Close open field-local SGR on an emitted fragment/row and restore the
     * enclosing selected style when present. Style::apply() re-applies
     * selected backgrounds after full resets.
     */
    private function fieldStyleBoundary(string $text, ?Style $selectedStyle): string
    {
        if (!str_contains($text, "\x1b")) {
            return '';
        }
        $tracker = new AnsiCodeTracker();
        $tracker->processText($text);
        if (!$tracker->hasActiveCodes()) {
            return '';
        }
        $boundary = "\x1b[0m";
        if (null !== $selectedStyle) {
            $boundary .= $selectedStyle->getAnsiRestore();
        }

        return $boundary;
    }

    private function resetFilteredItems(): void
    {
        $this->filteredItemIndices = array_keys($this->items);
    }

    private function normalizeDescription(string $description): string
    {
        // Convert multiline to single line
        return trim(preg_replace('/[\r\n]+/', ' ', $description));
    }

    private function toggleCurrentItem(): void
    {
        if (!$this->multiselect) {
            return;
        }
        if (null === $itemIndex = $this->filteredItemIndices[$this->selectedIndex] ?? null) {
            return;
        }
        $this->items[$itemIndex]['checked'] = $checked = !($this->items[$itemIndex]['checked'] ?? false);
        $this->invalidate();
        $this->dispatch(new SelectionToggleEvent($this, $this->items[$itemIndex], $checked, $this->getSelectedItems()));
    }

    private function confirmSelection(): void
    {
        $this->selected = true;
        if ($this->multiselect) {
            $this->dispatch(new MultiSelectEvent($this, $this->getSelectedItems()));

            return;
        }
        if (null !== $selectedItem = $this->getFilteredItem($this->selectedIndex)) {
            $this->dispatch(new SelectEvent($this, $selectedItem));
        }
    }

    private function notifySelectionChange(): void
    {
        $this->invalidate();
        if (null !== $selectedItem = $this->getFilteredItem($this->selectedIndex)) {
            $this->dispatch(new SelectionChangeEvent($this, $selectedItem));
        }
    }

    /**
     * @return array{value: string, label: string, description?: string, checked?: bool, group?: string}|null
     */
    private function getFilteredItem(int $index): ?array
    {
        return isset($this->filteredItemIndices[$index]) ? $this->items[$this->filteredItemIndices[$index]] : null;
    }
}
