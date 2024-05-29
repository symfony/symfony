<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Test;

use PHPUnit\Framework\Constraint\LogicalNot;
use Symfony\Component\Notifier\Event\MessageEvent;
use Symfony\Component\Notifier\Event\NotificationEvents;
use Symfony\Component\Notifier\Message\MessageInterface;
use Symfony\Component\Notifier\Test\Constraint as NotifierConstraint;

/*
 * @author Smaïne Milianni <smaine.milianni@gmail.com>
 */
trait NotificationAssertionsTrait
{
    public static function assertNotificationCount(int $count, ?string $transportName = null, string $message = ''): void
    {
        self::assertThat(self::getNotificationEvents(), new NotifierConstraint\NotificationCount($count, $transportName), $message);
    }

    public static function assertQueuedNotificationCount(int $count, ?string $transportName = null, string $message = ''): void
    {
        self::assertThat(self::getNotificationEvents(), new NotifierConstraint\NotificationCount($count, $transportName, true), $message);
    }

    public static function assertNotificationIsQueued(MessageEvent $event, string $message = ''): void
    {
        self::assertThat($event, new NotifierConstraint\NotificationIsQueued(), $message);
    }

    public static function assertNotificationIsNotQueued(MessageEvent $event, string $message = ''): void
    {
        self::assertThat($event, new LogicalNot(new NotifierConstraint\NotificationIsQueued()), $message);
    }

    public static function assertNotificationSubjectContains(MessageInterface $notification, string $text, string $message = ''): void
    {
        self::assertThat($notification, new NotifierConstraint\NotificationSubjectContains($text), $message);
    }

    public static function assertNotificationSubjectNotContains(MessageInterface $notification, string $text, string $message = ''): void
    {
        self::assertThat($notification, new LogicalNot(new NotifierConstraint\NotificationSubjectContains($text)), $message);
    }

    public static function assertNotificationTransportIsEqual(MessageInterface $notification, ?string $transportName = null, string $message = ''): void
    {
        self::assertThat($notification, new NotifierConstraint\NotificationTransportIsEqual($transportName), $message);
    }

    public static function assertNotificationTransportIsNotEqual(MessageInterface $notification, ?string $transportName = null, string $message = ''): void
    {
        self::assertThat($notification, new LogicalNot(new NotifierConstraint\NotificationTransportIsEqual($transportName)), $message);
    }

    /**
     * @return MessageEvent[]
     */
    public static function getNotifierEvents(?string $transportName = null): array
    {
        return self::getNotificationEvents()->getEvents($transportName);
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
        return self::getNotificationEvents()->getMessages($transportName);
    }

    public static function getNotifierMessage(int $index = 0, ?string $transportName = null): ?MessageInterface
    {
        return self::getNotifierMessages($transportName)[$index] ?? null;
    }

    public static function getNotificationEvents(): NotificationEvents
    {
        $container = static::getContainer();
        if ($container->has('notifier.notification_logger_listener')) {
            return $container->get('notifier.notification_logger_listener')->getEvents();
        }

        static::fail('A client must have Notifier enabled to make notifications assertions. Did you forget to require symfony/notifier?');
    }
}
