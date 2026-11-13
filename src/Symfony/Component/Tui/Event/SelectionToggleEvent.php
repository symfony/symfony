<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Tui\Event;

use Symfony\Component\Tui\Widget\SelectListWidget;

/**
 * Event dispatched when an item is checked or unchecked in a multi-select SelectList.
 *
 * @experimental
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class SelectionToggleEvent extends AbstractEvent
{
    /**
     * @param array{value: string, label: string, description?: string, checked?: bool}       $item
     * @param list<array{value: string, label: string, description?: string, checked?: bool}> $selectedItems
     */
    public function __construct(
        SelectListWidget $target,
        private readonly array $item,
        private readonly bool $checked,
        private readonly array $selectedItems,
    ) {
        parent::__construct($target);
    }

    /**
     * Get the toggled item.
     *
     * @return array{value: string, label: string, description?: string, checked?: bool}
     */
    public function getItem(): array
    {
        return $this->item;
    }

    /**
     * Get the toggled item value.
     */
    public function getValue(): string
    {
        return $this->item['value'];
    }

    public function isChecked(): bool
    {
        return $this->checked;
    }

    /**
     * Get the currently selected item arrays after the toggle.
     *
     * @return list<array{value: string, label: string, description?: string, checked?: bool}>
     */
    public function getSelectedItems(): array
    {
        return $this->selectedItems;
    }
}
