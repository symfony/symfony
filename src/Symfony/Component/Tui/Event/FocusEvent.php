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
