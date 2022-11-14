<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\Pusher;

use Symfony\Component\Notifier\Message\PushMessage;
use Symfony\Component\Notifier\Notification\Notification;
use Symfony\Component\Notifier\Notification\PushNotificationInterface;
use Symfony\Component\Notifier\Recipient\RecipientInterface;

/**
 * @author Yasmany Cubela Medina <yasmanycm@gmail.com>
 */
class PusherNotification extends Notification implements PushNotificationInterface
{
    public function asPushMessage(RecipientInterface $recipient, string $transport = null): ?PushMessage
    {
        return new PushMessage($this->getSubject(), $this->getContent(), new PusherOptions($recipient->getChannels()));
    }
}
