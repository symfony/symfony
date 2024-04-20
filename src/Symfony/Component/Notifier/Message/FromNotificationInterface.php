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
interface FromNotificationInterface
{
    public function getNotification(): ?Notification;
}
