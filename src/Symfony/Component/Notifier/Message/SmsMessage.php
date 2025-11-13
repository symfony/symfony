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
class SmsMessage implements MessageInterface, FromNotificationInterface
{
    private ?string $transport = null;
    private ?Notification $notification = null;

    public function __construct(
        private string $phone,
        private string $subject,
        private string $from = '',
        private ?MessageOptionsInterface $options = null,
    ) {
        if ('' === $phone) {
            throw new InvalidArgumentException(\sprintf('"%s" needs a phone number, it cannot be empty.', __CLASS__));
        }
    }

    public static function fromNotification(Notification $notification, SmsRecipientInterface $recipient): self
    {
        $message = new self($recipient->getPhone(), $notification->getSubject());
        $message->notification = $notification;

        return $message;
    }

    /**
     * @return $this
     */
    public function phone(string $phone): static
    {
        if ('' === $phone) {
            throw new InvalidArgumentException(\sprintf('"%s" needs a phone number, it cannot be empty.', static::class));
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

    /**
     * @return $this
     */
    public function options(MessageOptionsInterface $options): static
    {
        $this->options = $options;

        return $this;
    }

    public function getOptions(): ?MessageOptionsInterface
    {
        return $this->options;
    }

    public function getNotification(): ?Notification
    {
        return $this->notification;
    }
}
