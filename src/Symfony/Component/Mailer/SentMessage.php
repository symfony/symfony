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
    private RawMessage $original;
    private RawMessage $raw;
    private string $messageId;
    private ?string $messageIdHeader = null;
    private string $debug = '';

    /**
     * @internal
     */
    public function __construct(
        RawMessage $message,
        private Envelope $envelope,
    ) {
        $message->ensureValidity();

        $this->original = $message;

        if ($message instanceof Message) {
            $message = clone $message;
            $headers = $message->getHeaders();
            if (!$headers->has('Message-ID')) {
                $headers->addIdHeader('Message-ID', $message->generateMessageId());
            }
            $this->messageIdHeader = $this->messageId = $headers->get('Message-ID')->getId();
            $this->raw = new RawMessage($message->toIterable());
        } else {
            $this->raw = $message;
        }
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

    /**
     * @deprecated since Symfony 8.3, use setTransportMessageId() instead
     */
    public function setMessageId(string $id): void
    {
        trigger_deprecation('symfony/mailer', '8.3', 'Method "%s()" is deprecated, use "setTransportMessageId()" instead.', __METHOD__);

        $this->setTransportMessageId($id);
    }

    /**
     * @deprecated since Symfony 8.3, use getTransportMessageId() or getMessageIdHeader() instead
     */
    public function getMessageId(): string
    {
        trigger_deprecation('symfony/mailer', '8.3', 'Method "%s()" is deprecated, use "getTransportMessageId()" or "getMessageIdHeader()" instead.', __METHOD__);

        return $this->getTransportMessageId();
    }

    /**
     * Sets the id that the transport gave to the message, e.g. the queue id of an SMTP server or the id returned by an API.
     */
    public function setTransportMessageId(string $id): void
    {
        $this->messageId = $id;
    }

    /**
     * Gets the id that the transport gave to the message.
     *
     * Until the transport sets it, this is the value of the Message-ID header.
     */
    public function getTransportMessageId(): string
    {
        return $this->messageId;
    }

    /**
     * Gets the value of the Message-ID header that is sent, whether the message had one or it was generated.
     *
     * Returns null when the message is not a Message, e.g. a RawMessage.
     */
    public function getMessageIdHeader(): ?string
    {
        return $this->messageIdHeader;
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
