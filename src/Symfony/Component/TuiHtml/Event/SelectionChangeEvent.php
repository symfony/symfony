<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\TuiHtml\Event;

use Symfony\Component\Tui\Event\AbstractEvent;
use Symfony\Component\Tui\Widget\AbstractWidget;

/**
 * Event dispatched when the highlighted item changes in a SelectList.
 *
 * This fires when the user moves the cursor (arrow keys, scroll), not
 * when they confirm a selection (that's {@see SelectEvent}).
 *
 * @experimental
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class SelectionChangeEvent extends AbstractEvent
{
    /**
     * @param array{value: string, label: string, description?: string, checked?: bool, group?: string} $item
     */
    public function __construct(AbstractWidget $target, private readonly array $item)
    {
        parent::__construct($target);
    }

    /**
     * Get the full highlighted item array.
     *
     * @return array{value: string, label: string, description?: string, checked?: bool, group?: string}
     */
    public function getItem(): array
    {
        return $this->item;
    }

    /**
     * Get the highlighted item's value.
     */
    public function getValue(): string
    {
        return $this->item['value'];
    }

    /**
     * Get the highlighted item's label.
     */
    public function getLabel(): string
    {
        return $this->item['label'];
    }

    /**
     * Get the highlighted item's description, if any.
     */
    public function getDescription(): ?string
    {
        return $this->item['description'] ?? null;
    }
}
