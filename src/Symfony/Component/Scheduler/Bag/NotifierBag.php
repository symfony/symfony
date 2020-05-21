<?php

declare(strict_types=1);

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Scheduler\Bag;

use Symfony\Component\Notifier\Notification\Notification;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class NotifierBag implements BagInterface
{
    /**
     * @var array<string,Notification>
     */
    private $notifications;

    /**
     * @param Notification[] $beforeNotifications
     * @param Notification[] $afterNotifications
     * @param Notification[] $failureNotifications
     */
    public function __construct(array $beforeNotifications = [], array $afterNotifications = [], array $failureNotifications = [])
    {
        $this->notifications['before'] = $beforeNotifications;
        $this->notifications['after'] = $afterNotifications;
        $this->notifications['failure'] = $failureNotifications;
    }

    /**
     * @return array<string,Notification>
     */
    public function getContent(): array
    {
        return $this->notifications;
    }

    public function getName(): string
    {
        return 'notifier';
    }
}
