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
 * Event dispatched when items are selected in a multi-select SelectList.
 *
 * @experimental
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class MultiSelectEvent extends AbstractEvent
{
    /**
     * @param list<array{value: string, label: string, description?: string, checked?: bool}> $items
     */
    public function __construct(
        SelectListWidget $target,
        private readonly array $items,
    ) {
        parent::__construct($target);
    }

    /**
     * Get the full selected item arrays.
     *
     * @return list<array{value: string, label: string, description?: string, checked?: bool}>
     */
    public function getItems(): array
    {
        return $this->items;
    }

    /**
     * Get the selected item values.
     *
     * @return list<string>
     */
    public function getValues(): array
    {
        return array_column($this->items, 'value');
    }

    public function isEmpty(): bool
    {
        return !$this->items;
    }
}
