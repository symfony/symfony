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
 *
 * @experimental in 4.3
 */
class SentMessage
{
    private $original;
    private $raw;
    private $envelope;

    /**
     * @internal
     */
    public function __construct(RawMessage $message, SmtpEnvelope $envelope)
    {
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

    public function getEnvelope(): SmtpEnvelope
    {
        return $this->envelope;
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
