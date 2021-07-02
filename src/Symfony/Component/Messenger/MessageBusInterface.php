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

use Symfony\Component\Messenger\Stamp\StampInterface;

/**
 * @author Samuel Roze <samuel.roze@gmail.com>
 */
interface MessageBusInterface
{
    /**
     * Dispatches the given message.
     *
     * @param object|Envelope  $message The message or the message pre-wrapped in an envelope
     * @param StampInterface[] $stamps
     */
    public function dispatch(object $message, array $stamps = []): Envelope;
}
