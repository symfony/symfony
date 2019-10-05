<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Channel;

use Symfony\Component\Notifier\Message\SmsMessage;
use Symfony\Component\Notifier\Notification\Notification;
use Symfony\Component\Notifier\Notification\SmsNotificationInterface;
use Symfony\Component\Notifier\Recipient\Recipient;
use Symfony\Component\Notifier\Recipient\SmsRecipientInterface;

/**
 * @author Fabien Potencier <fabien@symfony.com>
 *
 * @experimental in 5.0
 */
class SmsChannel extends AbstractChannel
{
    public function notify(Notification $notification, Recipient $recipient, string $transportName = null): void
    {
        $message = null;
        if ($notification instanceof SmsNotificationInterface) {
            $message = $notification->asSmsMessage($recipient, $transportName);
        }

        if (null === $message) {
            $message = SmsMessage::fromNotification($notification, $recipient, $transportName);
        }

        if (null !== $transportName) {
            $message->transport($transportName);
        }

        if (null === $this->bus) {
            $this->transport->send($message);
        } else {
            $this->bus->dispatch($message);
        }
    }

    public function supports(Notification $notification, Recipient $recipient): bool
    {
        return $recipient instanceof SmsRecipientInterface && '' !== $recipient->getPhone();
    }
}
