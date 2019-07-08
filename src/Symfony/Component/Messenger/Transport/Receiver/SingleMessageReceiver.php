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

/**
 * Receiver that decorates another, but receives only 1 specific message.
 *
 * @author Ryan Weaver <ryan@symfonycasts.com>
 *
 * @internal
 */
class SingleMessageReceiver implements ReceiverInterface
{
    private $receiver;
    private $envelope;
    private $hasReceived = false;

    public function __construct(ReceiverInterface $receiver, Envelope $envelope)
    {
        $this->receiver = $receiver;
        $this->envelope = $envelope;
    }

    public function get(): iterable
    {
        if ($this->hasReceived) {
            return [];
        }

        $this->hasReceived = true;

        return [$this->envelope];
    }

    public function ack(Envelope $envelope): void
    {
        $this->receiver->ack($envelope);
    }

    public function reject(Envelope $envelope): void
    {
        $this->receiver->reject($envelope);
    }
}
