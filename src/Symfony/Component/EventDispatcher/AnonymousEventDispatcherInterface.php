<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\EventDispatcher;

/**
 * Dispatch events without the need to specify an event name.
 *
 * @author Daniel Santamaría <santaka87@gmail.com>
 */
interface AnonymousEventDispatcherInterface
{
    /**
     * Dispatches an event to all registered listeners.
     *
     * @param object $event The event to pass to the event handlers/listeners
     */
    public function dispatchAnonymousEvent($event);
}
