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

use Symfony\Component\Notifier\Notification\NotificationInterface;
use Symfony\Component\Notifier\Recipient\RecipientInterface;

/**
 * @author Fabien Potencier <fabien@symfony.com>
 */
interface ChannelInterface
{
    public function notify(NotificationInterface $notification, RecipientInterface $recipient, string $transportName = null): void;

    public function supports(NotificationInterface $notification, RecipientInterface $recipient): bool;
}
