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
use Symfony\Component\Mailer\SmtpEnvelope;
use Symfony\Component\Mime\RawMessage;

/**
 * Allows the transformation of a Message and the SMTP Envelope before the email is sent.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
final class MessageEvent extends Event
{
    private $message;
    private $envelope;
    private $transportName;
    private $queued;

    public function __construct(RawMessage $message, SmtpEnvelope $envelope, string $transportName, bool $queued = false)
    {
        $this->message = $message;
        $this->envelope = $envelope;
        $this->transportName = $transportName;
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

    public function getEnvelope(): SmtpEnvelope
    {
        return $this->envelope;
    }

    public function setEnvelope(SmtpEnvelope $envelope): void
    {
        $this->envelope = $envelope;
    }

    public function getTransportName(): string
    {
        return $this->transportName;
    }

    public function isQueued(): bool
    {
        return $this->queued;
    }
}
