<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Transport\Receiver;

use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\TransportException;

/**
 * Used when a receiver can make a message it delivered available again
 * without waiting for a timeout on the transport's side.
 *
 * The worker reads all the messages that a call to get() returns before it handles
 * the first of them, so that it knows which ones to release when it stops.
 */
interface ReleasableReceiverInterface extends ReceiverInterface
{
    /**
     * Makes a message returned by get() available to the receivers again, as if it had not been delivered.
     *
     * The worker calls this when it stops, for the messages it fetched but did not start handling.
     *
     * @throws TransportException If there is an issue communicating with the transport
     */
    public function release(Envelope $envelope): void;
}
