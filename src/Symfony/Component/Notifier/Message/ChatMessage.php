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

use Symfony\Component\Notifier\Notification\Notification;

/**
 * @author Fabien Potencier <fabien@symfony.com>
 */
class ChatMessage implements MessageInterface
{
    private ?string $transport = null;
    private string $subject;
    private ?MessageOptionsInterface $options;
    private ?Notification $notification = null;

    public function __construct(string $subject, MessageOptionsInterface $options = null)
    {
        $this->subject = $subject;
        $this->options = $options;
    }

    public static function fromNotification(Notification $notification): self
    {
        $message = new self($notification->getSubject());
        $message->notification = $notification;

        return $message;
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

    public function getRecipientId(): ?string
    {
        return $this->options?->getRecipientId();
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

    public function getNotification(): ?Notification
    {
        return $this->notification;
    }
}
