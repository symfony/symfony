<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mailer;

use Symfony\Component\Mime\Message;
use Symfony\Component\Mime\RawMessage;

/**
 * @author Fabien Potencier <fabien@symfony.com>
 */
class SentMessage
{
    private $original;
    private $raw;
    private $envelope;
    private $debug = '';

    /**
     * @internal
     */
    public function __construct(RawMessage $message, Envelope $envelope)
    {
        $message->ensureValidity();

        $this->raw = $message instanceof Message ? new RawMessage($message->toIterable()) : $message;
        $this->original = $message;
        $this->envelope = $envelope;
    }

    public function getMessage(): RawMessage
    {
        return $this->raw;
    }

    public function getOriginalMessage(): RawMessage
    {
        return $this->original;
    }

    public function getEnvelope(): Envelope
    {
        return $this->envelope;
    }

    public function getDebug(): string
    {
        return $this->debug;
    }

    public function appendDebug(string $debug): void
    {
        $this->debug .= $debug;
    }

    public function toString(): string
    {
        return $this->raw->toString();
    }

    public function toIterable(): iterable
    {
        return $this->raw->toIterable();
    }
}
