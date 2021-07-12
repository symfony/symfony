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
 * @author Tomas Norkūnas <norkunas.tom@gmail.com>
 */
final class PushMessage implements MessageInterface
{
    private $transport;
    private $subject;
    private $content;
    private $options;
    private $notification;

    public function __construct(string $subject, string $content, MessageOptionsInterface $options = null)
    {
        $this->subject = $subject;
        $this->content = $content;
        $this->options = $options;
    }

    public static function fromNotification(Notification $notification): self
    {
        $message = new self($notification->getSubject(), $notification->getContent());
        $message->notification = $notification;

        return $message;
    }

    public function getRecipientId(): ?string
    {
        return $this->options ? $this->options->getRecipientId() : null;
    }

    public function subject(string $subject): self
    {
        $this->subject = $subject;

        return $this;
    }

    public function getSubject(): string
    {
        return $this->subject;
    }

    public function content(string $content): self
    {
        $this->content = $content;

        return $this;
    }

    public function getContent(): string
    {
        return $this->content;
    }

    public function options(MessageOptionsInterface $options): self
    {
        $this->options = $options;

        return $this;
    }

    public function getOptions(): ?MessageOptionsInterface
    {
        return $this->options;
    }

    public function transport(?string $transport): self
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
