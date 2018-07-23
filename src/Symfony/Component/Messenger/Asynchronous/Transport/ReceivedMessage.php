<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Asynchronous\Transport;

use Symfony\Component\Messenger\Asynchronous\Middleware\SendMessageMiddleware;
use Symfony\Component\Messenger\EnvelopeItemInterface;

/**
 * Marker config for a received message.
 * This is mainly used by the `SendMessageMiddleware` middleware to identify
 * a message should not be sent if it was just received.
 *
 * @see SendMessageMiddleware
 *
 * @author Samuel Roze <samuel.roze@gmail.com>
 */
final class ReceivedMessage implements EnvelopeItemInterface
{
    public function serialize()
    {
        return '';
    }

    public function unserialize($serialized)
    {
        // noop
    }
}
