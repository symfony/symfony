<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Notification;

use Symfony\Component\Notifier\Message\DesktopMessage;
use Symfony\Component\Notifier\Recipient\RecipientInterface;

/**
 * @author Ahmed Ghanem <ahmedghanem7361@gmail.com>
 */
interface DesktopNotificationInterface
{
    public function asDesktopMessage(RecipientInterface $recipient, ?string $transport = null): ?DesktopMessage;
}
