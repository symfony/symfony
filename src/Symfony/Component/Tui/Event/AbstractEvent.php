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
use Symfony\Contracts\EventDispatcher\Event as BaseEvent;

/**
 * Base class for all TUI widget events.
 *
 * Extends Symfony's Event so it can be dispatched through
 * Symfony's EventDispatcher. Carries the target widget that
 * originated the event.
 *
 * @experimental
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
abstract class AbstractEvent extends BaseEvent
{
    public function __construct(
        private readonly AbstractWidget $target,
    ) {
    }

    public function getTarget(): AbstractWidget
    {
        return $this->target;
    }
}
