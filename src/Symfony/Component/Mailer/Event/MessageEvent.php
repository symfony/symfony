<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mailer\Event;

use Symfony\Component\EventDispatcher\Event;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mime\RawMessage;

/**
 * Allows the transformation of a Message and the Envelope before the email is sent.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
final class MessageEvent extends Event
{
    private $message;
    private $envelope;
    private $transport;
    private $queued;

    public function __construct(RawMessage $message, Envelope $envelope, string $transport, bool $queued = false)
    {
        $this->message = $message;
        $this->envelope = $envelope;
        $this->transport = $transport;
        $this->queued = $queued;
    }

    public function getMessage(): RawMessage
    {
        return $this->message;
    }

    public function setMessage(RawMessage $message): void
    {
        $this->message = $message;
    }

    public function getEnvelope(): Envelope
    {
        return $this->envelope;
    }

    public function setEnvelope(Envelope $envelope): void
    {
        $this->envelope = $envelope;
    }

    public function getTransport(): string
    {
        return $this->transport;
    }

    public function isQueued(): bool
    {
        return $this->queued;
    }
}
