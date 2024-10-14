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

interface KeepaliveReceiverInterface extends ReceiverInterface
{
    /**
     * Informs the transport that the message is still being processed to avoid a timeout on the transport's side.
     *
     * @param int|null $seconds The minimum duration the message should be kept alive
     *
     * @throws TransportException If there is an issue communicating with the transport
     */
    public function keepalive(Envelope $envelope, ?int $seconds = null): void;
}
