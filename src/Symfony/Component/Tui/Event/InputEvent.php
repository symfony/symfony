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

use Symfony\Contracts\EventDispatcher\Event;

/**
 * Event dispatched when raw terminal input is received.
 *
 * Dispatched before focus navigation and before the focused widget
 * receives input. Call {@see stopPropagation()} to consume the input
 * and prevent further processing.
 *
 * @experimental
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class InputEvent extends Event
{
    public function __construct(
        private readonly string $data,
    ) {
    }

    /**
     * The raw input data from the terminal.
     */
    public function getData(): string
    {
        return $this->data;
    }
}
