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
    public static function assertNotificationCount(int $count, string $transport = null, string $message = ''): void
    {
        self::assertThat(self::getNotificationEvents(), new NotifierConstraint\NotificationCount($count, $transport), $message);
    }

    public static function assertQueuedNotificationCount(int $count, string $transport = null, string $message = ''): void
    {
        self::assertThat(self::getMessageMailerEvents(), new NotifierConstraint\NotificationCount($count, $transport, true), $message);
    }

    public static function assertNotificationIsQueued(MessageEvent $event, string $message = ''): void
    {
        self::assertThat($event, new NotifierConstraint\NotificationIsQueued(), $message);
    }

    public static function assertNotificationIsNotQueued(MessageEvent $event, string $message = ''): void
    {
        self::assertThat($event, new LogicalNot(new NotifierConstraint\NotificationIsQueued()), $message);
    }

    public static function assertNotificationSubjectContains(MessageInterface $messageObject, string $text, string $message = ''): void
    {
        self::assertThat($messageObject, new NotifierConstraint\NotificationSubjectContains($text), $message);
    }

    public static function assertNotificationSubjectNotContains(MessageInterface $messageObject, string $text, string $message = ''): void
    {
        self::assertThat($messageObject, new LogicalNot(new NotifierConstraint\NotificationSubjectContains($text)), $message);
    }

    public static function assertNotificationTransportIsEqual(MessageInterface $messageObject, string $text, string $message = ''): void
    {
        self::assertThat($messageObject, new NotifierConstraint\NotificationTransportIsEqual($text), $message);
    }

    public static function assertNotificationTransportIsNotEqual(MessageInterface $messageObject, string $text, string $message = ''): void
    {
        self::assertThat($messageObject, new LogicalNot(new NotifierConstraint\NotificationTransportIsEqual($text)), $message);
    }

    /**
     * @return MessageEvent[]
     */
    public static function getNotifierEvents(string $transport = null): array
    {
        return self::getNotificationEvents()->getEvents($transport);
    }

    public static function getNotifierEvent(int $index = 0, string $transport = null): ?MessageEvent
    {
        return self::getNotifierEvents($transport)[$index] ?? null;
    }

    /**
     * @return MessageInterface[]
     */
    public static function getNotifierMessages(string $transport = null): array
    {
        return self::getNotificationEvents()->getMessages($transport);
    }

    public static function getNotifierMessage(int $index = 0, string $transport = null): ?MessageInterface
    {
        return self::getNotifierMessages($transport)[$index] ?? null;
    }

    public static function getNotificationEvents(): NotificationEvents
    {
        $container = static::getContainer();
        if ($container->has('notifier.logger_notification_listener')) {
            return $container->get('notifier.logger_notification_listener')->getEvents();
        }

        static::fail('A client must have Notifier enabled to make notifications assertions. Did you forget to require symfony/notifier?');
    }
}
