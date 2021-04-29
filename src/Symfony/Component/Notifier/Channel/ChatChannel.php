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

use Symfony\Component\Notifier\Message\ChatMessage;
use Symfony\Component\Notifier\Notification\ChatNotificationInterface;
use Symfony\Component\Notifier\Notification\NotificationInterface;
use Symfony\Component\Notifier\Recipient\RecipientInterface;

/**
 * @author Fabien Potencier <fabien@symfony.com>
 */
class ChatChannel extends AbstractChannel
{
    public function notify(NotificationInterface $notification, RecipientInterface $recipient, string $transportName = null): void
    {
        $message = null;
        if ($notification instanceof ChatNotificationInterface) {
            $message = $notification->asChatMessage($recipient, $transportName);
        }

        if (null === $message) {
            $message = ChatMessage::fromNotification($notification);
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

    public function supports(NotificationInterface $notification, RecipientInterface $recipient): bool
    {
        return true;
    }
}
