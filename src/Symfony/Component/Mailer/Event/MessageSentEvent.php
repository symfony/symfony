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

use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mime\RawMessage;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Allows access to SentMessage after the mail has been sent.
 */
final class MessageSentEvent extends Event
{
    private $message;
    private $transport;

    public function __construct(SentMessage $message, string $transport)
    {
        $this->message = $message;
        $this->transport = $transport;
    }

    public function getMessage(): SentMessage
    {
        return $this->message;
    }

    public function setMessage(SentMessage $message): void
    {
        $this->message = $message;
    }

    public function getTransport(): string
    {
        return $this->transport;
    }
}
