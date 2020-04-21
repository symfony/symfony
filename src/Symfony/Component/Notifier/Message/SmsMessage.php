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

use Symfony\Component\Notifier\Exception\LogicException;
use Symfony\Component\Notifier\Notification\Notification;
use Symfony\Component\Notifier\Notification\SmsNotificationInterface;
use Symfony\Component\Notifier\Recipient\Recipient;
use Symfony\Component\Notifier\Recipient\SmsRecipientInterface;

/**
 * @author Fabien Potencier <fabien@symfony.com>
 *
 * @experimental in 5.1
 */
final class SmsMessage implements MessageInterface
{
    private $transport;
    private $subject;
    private $phone;

    public function __construct(string $phone, string $subject)
    {
        $this->subject = $subject;
        $this->phone = $phone;
    }

    public static function fromNotification(Notification $notification, Recipient $recipient): self
    {
        if (!$recipient instanceof SmsRecipientInterface) {
            throw new LogicException(sprintf('To send a SMS message, "%s" should implement "%s" or the recipient should implement "%s".', get_debug_type($notification), SmsNotificationInterface::class, SmsRecipientInterface::class));
        }

        return new self($recipient->getPhone(), $notification->getSubject());
    }

    /**
     * @return $this
     */
    public function phone(string $phone): self
    {
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
    public function subject(string $subject): self
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
    public function transport(string $transport): self
    {
        $this->transport = $transport;

        return $this;
    }

    public function getTransport(): ?string
    {
        return $this->transport;
    }

    public function getOptions(): ?MessageOptionsInterface
    {
        return null;
    }
}
