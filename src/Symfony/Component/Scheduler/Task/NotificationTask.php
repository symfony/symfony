<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Scheduler\Task;

use Symfony\Component\Notifier\Notification\Notification;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class NotificationTask extends AbstractTask
{
    public function __construct(string $name, Notification $notification = null, array $options = [], array $additionalOptions = [])
    {
        parent::__construct($name, array_merge($options, [
            'notification' => $notification,
            'type' => 'null',
        ]), array_merge($additionalOptions, [
            'notification' => ['null', Notification::class]
        ]));
    }
}
