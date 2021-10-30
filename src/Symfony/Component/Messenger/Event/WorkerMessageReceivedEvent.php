<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Event;

/**
 * Dispatched when a message was received from a transport but before sent to the bus.
 *
 * The event name is the class name.
 */
final class WorkerMessageReceivedEvent extends AbstractWorkerMessageEvent
{
    private bool $shouldHandle = true;

    public function shouldHandle(bool $shouldHandle = null): bool
    {
        if (null !== $shouldHandle) {
            $this->shouldHandle = $shouldHandle;
        }

        return $this->shouldHandle;
    }
}
