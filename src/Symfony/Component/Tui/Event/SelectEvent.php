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
 * Event dispatched when an item is selected in a SelectList.
 *
 * @experimental
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class SelectEvent extends AbstractEvent
{
    /**
     * @param array{value: string, label: string, description?: string} $item
     */
    public function __construct(
        SelectListWidget $target,
        private readonly array $item,
    ) {
        parent::__construct($target);
    }

    /**
     * Get the full selected item array.
     *
     * @return array{value: string, label: string, description?: string}
     */
    public function getItem(): array
    {
        return $this->item;
    }

    /**
     * Get the selected item's value.
     */
    public function getValue(): string
    {
        return $this->item['value'];
    }

    /**
     * Get the selected item's label.
     */
    public function getLabel(): string
    {
        return $this->item['label'];
    }

    /**
     * Get the selected item's description, if any.
     */
    public function getDescription(): ?string
    {
        return $this->item['description'] ?? null;
    }
}
