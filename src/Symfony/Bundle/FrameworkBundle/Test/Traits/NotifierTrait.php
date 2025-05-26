<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Test\Traits;

use Symfony\Component\Notifier\Event\MessageEvent;
use Symfony\Component\Notifier\Event\NotificationEvents;
use Symfony\Component\Notifier\Message\MessageInterface;

/**
 * @author Smaïne Milianni <smaine.milianni@gmail.com>
 */
trait NotifierTrait
{
    /**
     * @return MessageEvent[]
     */
    public static function getNotifierEvents(?string $transportName = null): array
    {
        return self::getNotifierNotificationEvents()->getEvents($transportName);
    }

    public static function getNotifierEvent(int $index = 0, ?string $transportName = null): ?MessageEvent
    {
        return self::getNotifierEvents($transportName)[$index] ?? null;
    }

    /**
     * @return MessageInterface[]
     */
    public static function getNotifierMessages(?string $transportName = null): array
    {
        return self::getNotifierNotificationEvents()->getMessages($transportName);
    }

    public static function getNotifierMessage(int $index = 0, ?string $transportName = null): ?MessageInterface
    {
        return self::getNotifierMessages($transportName)[$index] ?? null;
    }

    public static function getNotificationEvents(): NotificationEvents
    {
        trigger_deprecation('symfony/framework-bundle', '7.4', 'Use getNotifierNotificationEvents() instead.');
        return self::getNotifierNotificationEvents();
    }

    public static function getNotifierNotificationEvents(): NotificationEvents
    {
        $container = static::getContainer();
        if ($container->has('notifier.notification_logger_listener')) {
            return $container->get('notifier.notification_logger_listener')->getEvents();
        }

        static::fail('A client must have Notifier enabled to make notifications assertions. Did you forget to require symfony/notifier?');
    }
}
