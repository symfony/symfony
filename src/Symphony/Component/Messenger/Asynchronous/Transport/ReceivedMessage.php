<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\Messenger\Asynchronous\Transport;

use Symphony\Component\Messenger\Asynchronous\Middleware\SendMessageMiddleware;

/**
 * Wraps a received message. This is mainly used by the `SendMessageMiddleware` middleware to identify
 * a message should not be sent if it was just received.
 *
 * @see SendMessageMiddleware
 *
 * @author Samuel Roze <samuel.roze@gmail.com>
 */
final class ReceivedMessage
{
    private $message;

    public function __construct($message)
    {
        $this->message = $message;
    }

    public function getMessage()
    {
        return $this->message;
    }
}
