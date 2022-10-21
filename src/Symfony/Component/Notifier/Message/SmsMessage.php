<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Message;

use Symfony\Component\Notifier\Exception\InvalidArgumentException;
use Symfony\Component\Notifier\Notification\Notification;
use Symfony\Component\Notifier\Recipient\SmsRecipientInterface;

/**
 * @author Fabien Potencier <fabien@symfony.com>
 */
class SmsMessage implements MessageInterface
{
    private ?string $transport = null;
    private string $subject;
    private string $phone;
    private string $from;

    public function __construct(string $phone, string $subject, string $from = '')
    {
        if ('' === $phone) {
            throw new InvalidArgumentException(sprintf('"%s" needs a phone number, it cannot be empty.', __CLASS__));
        }

        $this->subject = $subject;
        $this->phone = $phone;
        $this->from = $from;
    }

    public static function fromNotification(Notification $notification, SmsRecipientInterface $recipient): self
    {
        return new self($recipient->getPhone(), $notification->getSubject());
    }

    /**
     * @return $this
     */
    public function phone(string $phone): static
    {
        if ('' === $phone) {
            throw new InvalidArgumentException(sprintf('"%s" needs a phone number, it cannot be empty.', static::class));
        }

        $this->phone = $phone;

        return $this;
    }

    public function getPhone(): string
    {
        return $this->phone;
    }

    public function getRecipientId(): string
    {
        return $this->phone;
    }

    /**
     * @return $this
     */
    public function subject(string $subject): static
    {
        $this->subject = $subject;

        return $this;
    }

    public function getSubject(): string
    {
        return $this->subject;
    }

    /**
     * @return $this
     */
    public function transport(?string $transport): static
    {
        $this->transport = $transport;

        return $this;
    }

    public function getTransport(): ?string
    {
        return $this->transport;
    }

    /**
     * @return $this
     */
    public function from(string $from): static
    {
        $this->from = $from;

        return $this;
    }

    public function getFrom(): string
    {
        return $this->from;
    }

    public function getOptions(): ?MessageOptionsInterface
    {
        return null;
    }
}
