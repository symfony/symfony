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

use Symfony\Component\Tui\Input\MouseButton;
use Symfony\Component\Tui\Input\MouseEventKind;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Event dispatched when a mouse interaction is received.
 *
 * Only dispatched while mouse tracking is enabled on the terminal
 * (see {@see \Symfony\Component\Tui\Tui::enableMouseTracking()}).
 * Dispatched before the input would otherwise reach the focused widget.
 * Call {@see stopPropagation()} to consume the event.
 *
 * Coordinates are in terminal character cells, 0-indexed, with (0, 0)
 * at the top-left corner of the screen.
 *
 * @experimental
 *
 * @author Louis-Arnaud Catoire <la.catoire@gmail.com>
 */
class MouseEvent extends Event
{
    public function __construct(
        public readonly int $x,
        public readonly int $y,
        public readonly MouseButton $button,
        public readonly MouseEventKind $kind,
        public readonly bool $shift = false,
        public readonly bool $alt = false,
        public readonly bool $ctrl = false,
    ) {
    }

    /**
     * Whether this event is a scroll-wheel movement.
     */
    public function isWheel(): bool
    {
        return MouseButton::WheelUp === $this->button || MouseButton::WheelDown === $this->button;
    }
}
