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

use Symfony\Component\Notifier\Message\SmsMessage;
use Symfony\Component\Notifier\Recipient\Recipient;

/**
 * @author Fabien Potencier <fabien@symfony.com>
 *
 * @experimental in 5.0
 */
interface SmsNotificationInterface
{
    public function asSmsMessage(Recipient $recipient, string $transport = null): ?SmsMessage;
}
