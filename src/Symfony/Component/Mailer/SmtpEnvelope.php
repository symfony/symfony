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
use Symfony\Component\Mime\Address;
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
        return new DelayedSmtpEnvelope($message);
    }

    public function setSender(Address $sender): void
    {
        $this->sender = new Address($sender->getAddress());
    }

    public function getSender(): Address
    {
        return $this->sender;
    }

    /**
     * @param Address[] $recipients
     */
    public function setRecipients(array $recipients): void
    {
        if (!$recipients) {
            throw new InvalidArgumentException('An envelope must have at least one recipient.');
        }

        $this->recipients = [];
        foreach ($recipients as $recipient) {
            if (!$recipient instanceof Address) {
                throw new InvalidArgumentException(sprintf('A recipient must be an instance of "%s" (got "%s").', Address::class, \is_object($recipient) ? \get_class($recipient) : \gettype($recipient)));
            }
            $this->recipients[] = new Address($recipient->getAddress());
        }
    }

    /**
     * @return Address[]
     */
    public function getRecipients(): array
    {
        return $this->recipients;
    }
}
