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

use Symfony\Component\Notifier\Exception\LogicException;
use Symfony\Component\Notifier\Message\ChatMessage;
use Symfony\Component\Notifier\Notification\ChatNotificationInterface;
use Symfony\Component\Notifier\Notification\Notification;
use Symfony\Component\Notifier\Recipient\Recipient;

/**
 * @author Fabien Potencier <fabien@symfony.com>
 *
 * @experimental in 5.1
 */
class ChatChannel extends AbstractChannel
{
    public function notify(Notification $notification, Recipient $recipient, string $transportName = null): void
    {
        if (null === $transportName) {
            throw new LogicException('A Chat notification must have a transport defined.');
        }

        $message = null;
        if ($notification instanceof ChatNotificationInterface) {
            $message = $notification->asChatMessage($recipient, $transportName);
        }

        if (null === $message) {
            $message = ChatMessage::fromNotification($notification);
        }

        $message->transport($transportName);

        if (null === $this->bus) {
            $this->transport->send($message);
        } else {
            $this->bus->dispatch($message);
        }
    }

    public function supports(Notification $notification, Recipient $recipient): bool
    {
        return true;
    }
}
