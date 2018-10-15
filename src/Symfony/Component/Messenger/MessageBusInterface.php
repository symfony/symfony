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
     * @param string|null     $name    The name to use as dispatching key; when not provided,
     *                                 this name is derived from the type of the message
     */
    public function dispatch($message, string $name = null): void;
}
