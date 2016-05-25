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
final class StandardEventListenerCaller implements EventListenerCallerInterface
{
    /**
     * {@inheritdoc}
     */
    public function call(callable $listener, Event $event, $eventName, EventDispatcherInterface $eventDispatcher)
    {
        call_user_func($listener, $event, $eventName, $eventDispatcher);
    }
}
