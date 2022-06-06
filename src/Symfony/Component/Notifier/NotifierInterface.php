<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier;

use Symfony\Component\Notifier\Notification\Notification;
use Symfony\Component\Notifier\Recipient\RecipientInterface;

/**
 * Interface for the Notifier system.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
interface NotifierInterface
{
    public function send(Notification $notification, RecipientInterface ...$recipients): void;
}
