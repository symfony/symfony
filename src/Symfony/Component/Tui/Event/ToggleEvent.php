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

use Symfony\Component\Tui\Widget\AbstractWidget;

/**
 * Dispatched on a popover, a dialog or a collapsible widget, like a <details>, once it is shown or hidden, opened or
 * closed, like the toggle event of HTML.
 *
 * @experimental
 */
class ToggleEvent extends AbstractEvent
{
    /**
     * @param 'open'|'closed' $oldState
     * @param 'open'|'closed' $newState
     */
    public function __construct(
        AbstractWidget $target,
        private readonly string $oldState,
        private readonly string $newState,
    ) {
        parent::__construct($target);
    }

    /**
     * @return 'open'|'closed'
     */
    public function getOldState(): string
    {
        return $this->oldState;
    }

    /**
     * @return 'open'|'closed'
     */
    public function getNewState(): string
    {
        return $this->newState;
    }
}
