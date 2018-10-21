<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger;

/**
 * @author Samuel Roze <samuel.roze@gmail.com>
 */
interface MessageBusInterface
{
    /**
     * Dispatches the given message.
     *
     * @param object|Envelope $message The message or the message pre-wrapped in an envelope
     * @param string|null     $topic   The topic that senders and/or handlers can subscribe to to get the message;
     *                                 if not provided, the topic is the class of the message
     */
    public function dispatch($message, string $topic = null): Envelope;
}
