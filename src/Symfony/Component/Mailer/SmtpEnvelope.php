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

use Symfony\Component\Mailer\Exception\InvalidArgumentException;
use Symfony\Component\Mailer\Exception\LogicException;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Header\Headers;
use Symfony\Component\Mime\Message;
use Symfony\Component\Mime\NamedAddress;
use Symfony\Component\Mime\RawMessage;

/**
 * @author Fabien Potencier <fabien@symfony.com>
 *
 * @experimental in 4.3
 */
class SmtpEnvelope
{
    private $sender;
    private $recipients = [];

    /**
     * @param Address[] $recipients
     */
    public function __construct(Address $sender, array $recipients)
    {
        $this->setSender($sender);
        $this->setRecipients($recipients);
    }

    public static function create(RawMessage $message): self
    {
        if ($message instanceof Message) {
            $headers = $message->getHeaders();

            return new self(self::getSenderFromHeaders($headers), self::getRecipientsFromHeaders($headers));
        }

        // FIXME: parse the raw message to create the envelope?
        throw new InvalidArgumentException(sprintf('Unable to create an SmtpEnvelope from a "%s" message.', RawMessage::class));
    }

    public function setSender(Address $sender): void
    {
        $this->sender = $sender instanceof NamedAddress ? new Address($sender->getAddress()) : $sender;
    }

    public function getSender(): Address
    {
        return $this->sender;
    }

    public function setRecipients(array $recipients): void
    {
        if (!$recipients) {
            throw new InvalidArgumentException('An envelope must have at least one recipient.');
        }

        $this->recipients = [];
        foreach ($recipients as $recipient) {
            if ($recipient instanceof NamedAddress) {
                $recipient = new Address($recipient->getAddress());
            } elseif (!$recipient instanceof Address) {
                throw new InvalidArgumentException(sprintf('A recipient must be an instance of "%s" (got "%s").', Address::class, \is_object($recipient) ? \get_class($recipient) : \gettype($recipient)));
            }
            $this->recipients[] = $recipient;
        }
    }

    /**
     * @return Address[]
     */
    public function getRecipients(): array
    {
        return $this->recipients;
    }

    private static function getRecipientsFromHeaders(Headers $headers): array
    {
        $recipients = [];
        foreach (['to', 'cc', 'bcc'] as $name) {
            foreach ($headers->getAll($name) as $header) {
                $recipients = array_merge($recipients, $header->getAddresses());
            }
        }

        return $recipients;
    }

    private static function getSenderFromHeaders(Headers $headers): Address
    {
        if ($return = $headers->get('Return-Path')) {
            return $return->getAddress();
        }
        if ($sender = $headers->get('Sender')) {
            return $sender->getAddress();
        }
        if ($from = $headers->get('From')) {
            return $from->getAddresses()[0];
        }

        throw new LogicException('Unable to determine the sender of the message.');
    }
}
