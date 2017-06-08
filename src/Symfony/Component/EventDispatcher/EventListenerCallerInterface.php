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
 *
 */
interface EventListenerCallerInterface
{
    /**
     * @param callable                 $listener
     * @param Event                    $event
     * @param string                   $eventName
     * @param EventDispatcherInterface $eventDispatcher
     */
    public function call(callable $listener, Event $event, $eventName, EventDispatcherInterface $eventDispatcher);
}
