<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Debug;

use Symfony\Component\Tui\Ansi\AnsiUtils;
use Symfony\Component\Tui\Event\SelectionChangeEvent;
use Symfony\Component\Tui\Render\RenderContext;
use Symfony\Component\Tui\Widget\SelectListWidget;

/**
 * Result list tailored for the interactive debug command.
 *
 * The built-in SelectListWidget renders a hardcoded arrow prefix and applies
 * selected styles only to the text. This command needs a full-width selection
 * band while keeping the generic Tui component unchanged.
 *
 * @internal
 */
final class DebugResultListWidget extends SelectListWidget
{
    /** @var array<array{value: string, label: string}|array{header: true, label: string}> */
    private array $debugItems = [];

    private int $debugSelectedIndex = 0;

    public function __construct(
        private int $debugMaxVisible = 5,
    ) {
        parent::__construct([], $debugMaxVisible);
    }

    public function setMaxVisible(int $maxVisible): void
    {
        $this->debugMaxVisible = max(1, $maxVisible);
        $this->invalidate();
    }

    /**
     * @param array<array{value: string, label: string}|array{header: true, label: string}> $items
     */
    public function setItems(array $items): static
    {
        $this->debugItems = $items;
        $this->debugSelectedIndex = $this->seekSelectable(0, 1);
        $this->invalidate();

        return $this;
    }

    /**
     * @return array{value: string, label: string}|null
     */
    public function getSelectedItem(): ?array
    {
        $item = $this->debugItems[$this->debugSelectedIndex] ?? null;

        if (!$item || isset($item['header'])) {
            return null;
        }

        return [
            'value' => $item['value'],
            'label' => $item['label'],
        ];
    }

    public function handleInput(string $data): void
    {
        if (!$this->debugItems) {
            return;
        }

        $keybindings = $this->getKeybindings();
        if ($keybindings->matches($data, 'select_up')) {
            $this->debugSelectedIndex = $this->seekSelectable($this->debugSelectedIndex - 1, -1);
            $this->notifyDebugSelectionChange();

            return;
        }

        if ($keybindings->matches($data, 'select_down')) {
            $this->debugSelectedIndex = $this->seekSelectable($this->debugSelectedIndex + 1, 1);
            $this->notifyDebugSelectionChange();
        }
    }

    /**
     * @return string[]
     */
    public function render(RenderContext $context): array
    {
        $columns = $context->getColumns();

        if (!$this->debugItems) {
            return [$this->applyElement('no-match', AnsiUtils::truncateToWidth('No matching items', $columns, ''))];
        }

        $startIndex = max(
            0,
            min(
                $this->debugSelectedIndex - (int) floor($this->debugMaxVisible / 2),
                \count($this->debugItems) - $this->debugMaxVisible,
            ),
        );
        $endIndex = min($startIndex + $this->debugMaxVisible, \count($this->debugItems));

        $lines = [];
        for ($i = $startIndex; $i < $endIndex; ++$i) {
            $label = $this->debugItems[$i]['label'];

            if (isset($this->debugItems[$i]['header'])) {
                $lines[] = "\e[2m".$this->truncateLabel($label, $columns)."\e[22m";

                continue;
            }

            if ($i === $this->debugSelectedIndex) {
                $lines[] = $this->applyElement('selected', $this->truncateLabel($label, $columns));

                continue;
            }

            $lines[] = $this->truncateLabel($label, $columns);
        }

        if ($startIndex > 0 || $endIndex < \count($this->debugItems)) {
            [$position, $total] = $this->selectablePosition();
            $scrollText = \sprintf('(%d/%d)', $position, $total);
            $lines[] = $this->applyElement('scroll-info', AnsiUtils::truncateToWidth($scrollText, $columns, ''));
        }

        return $lines;
    }

    private function notifyDebugSelectionChange(): void
    {
        $this->invalidate();

        if (null !== $selectedItem = $this->getSelectedItem()) {
            $this->dispatch(new SelectionChangeEvent($this, $selectedItem));
        }
    }

    /**
     * Returns the nearest selectable (non-header) index from $from following $step,
     * wrapping around. Returns $from when no selectable item exists.
     */
    private function seekSelectable(int $from, int $step): int
    {
        $count = \count($this->debugItems);
        if (0 === $count) {
            return 0;
        }

        $index = ($from % $count + $count) % $count;
        for ($i = 0; $i < $count; ++$i) {
            if (!isset($this->debugItems[$index]['header'])) {
                return $index;
            }
            $index = ($index + $step % $count + $count) % $count;
        }

        return $from;
    }

    /**
     * @return array{0: int, 1: int} The 1-based rank of the selected item and the total selectable count
     */
    private function selectablePosition(): array
    {
        $total = 0;
        $position = 0;
        foreach ($this->debugItems as $i => $item) {
            if (isset($item['header'])) {
                continue;
            }
            ++$total;
            if ($i === $this->debugSelectedIndex) {
                $position = $total;
            }
        }

        return [$position, $total];
    }

    private function truncateLabel(string $label, int $columns): string
    {
        if (AnsiUtils::visibleWidth($label) <= $columns) {
            return $label;
        }

        if ($columns <= 1) {
            return AnsiUtils::sliceByColumn('…', 0, $columns, true);
        }

        if ($columns <= 5) {
            $labelWidth = AnsiUtils::visibleWidth($label);

            return AnsiUtils::sliceByColumn($label, max(0, $labelWidth - $columns), $columns, true);
        }

        $prefix = AnsiUtils::sliceByColumn($label, 0, 3, true);
        $ellipsis = "\e[2m…\e[22m";
        $labelWidth = AnsiUtils::visibleWidth($label);
        $suffixColumns = max(1, $columns - AnsiUtils::visibleWidth($prefix) - AnsiUtils::visibleWidth($ellipsis));
        $suffixStart = max(0, $labelWidth - $suffixColumns);

        return $prefix.$ellipsis.AnsiUtils::sliceByColumn($label, $suffixStart, $suffixColumns, true);
    }
}
