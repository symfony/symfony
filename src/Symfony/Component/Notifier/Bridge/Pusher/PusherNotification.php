<?php

declare(strict_types=1);

namespace Symfony\Component\Notifier\Bridge\Pusher;

use Symfony\Component\Notifier\Message\PushMessage;
use Symfony\Component\Notifier\Notification\Notification;
use Symfony\Component\Notifier\Notification\PushNotificationInterface;
use Symfony\Component\Notifier\Recipient\RecipientInterface;

class PusherNotification extends Notification implements PushNotificationInterface
{
    public function asPushMessage(RecipientInterface $recipient, string $transport = null): ?PushMessage
    {
        return new PushMessage($this->getSubject(), $this->getContent(), new PusherOptions($recipient->getChannels()));
    }
}
