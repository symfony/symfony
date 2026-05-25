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
use Symfony\Component\Tui\Widget\FocusableInterface;

/**
 * Event dispatched when focus changes to a new widget.
 *
 * Not dispatched when focus is cleared to null. Listeners that need
 * to know when focus is lost can observe widget removal directly or
 * track the previous focus via {@see getPrevious()} on subsequent
 * focus-change events.
 *
 * @experimental
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class FocusEvent extends AbstractEvent
{
    public function __construct(
        AbstractWidget&FocusableInterface $target,
        private readonly ?FocusableInterface $previous,
    ) {
        parent::__construct($target);
    }

    /**
     * Get the previously focused widget, if any.
     */
    public function getPrevious(): ?FocusableInterface
    {
        return $this->previous;
    }
}
