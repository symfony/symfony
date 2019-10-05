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

use Symfony\Component\Notifier\Message\ChatMessage;
use Symfony\Component\Notifier\Recipient\Recipient;

/**
 * @author Fabien Potencier <fabien@symfony.com>
 *
 * @experimental in 5.0
 */
interface ChatNotificationInterface
{
    public function asChatMessage(Recipient $recipient, string $transport = null): ?ChatMessage;
}
