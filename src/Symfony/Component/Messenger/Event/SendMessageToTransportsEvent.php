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
 * Event is dispatched before a message is sent to the transport.
 *
 * The event is *only* dispatched if the message will actually
 * be sent to at least one transport. If the message is sent
 * to multiple transports, the message is dispatched only one time.
 * This message is only dispatched the first time a message
 * is sent to a transport, not also if it is retried.
 *
 * @author Ryan Weaver <ryan@symfonycasts.com>
 */
final class SendMessageToTransportsEvent
{
    private Envelope $envelope;

    private array $senders;

    public function __construct(Envelope $envelope, array $senders)
    {
        $this->envelope = $envelope;
        $this->senders = $senders;
    }

    public function getEnvelope(): Envelope
    {
        return $this->envelope;
    }

    public function setEnvelope(Envelope $envelope)
    {
        $this->envelope = $envelope;
    }

    /**
     * @return array<string, SenderInterface>
     */
    public function getSenders(): array
    {
        return $this->senders;
    }
}
