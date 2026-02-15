<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Event;

use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Transport\Sender\SenderInterface;

/**
 * Event is dispatched after a message is sent to the transport.
 *
 * The event is *only* dispatched if the message was actually
 * sent to at least one transport. If the message was sent
 * to multiple transports, the event is dispatched only once.
 */
final class MessageSentToTransportsEvent
{
    /**
     * @param array<string, SenderInterface> $senders
     */
    public function __construct(
        private Envelope $envelope,
        private array $senders,
    ) {
    }

    public function getEnvelope(): Envelope
    {
        return $this->envelope;
    }

    /**
     * @return array<string, SenderInterface>
     */
    public function getSenders(): array
    {
        return $this->senders;
    }
}
