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

use PHPUnit\Framework\Constraint\LogicalNot;
use Symfony\Component\Notifier\Event\MessageEvent;
use Symfony\Component\Notifier\Message\MessageInterface;
use Symfony\Component\Notifier\Test\Constraint as NotifierConstraint;

/**
 * @author Smaïne Milianni <smaine.milianni@gmail.com>
 */
trait NotifierAssertionsTrait
{
    public static function assertNotificationCount(int $count, ?string $transportName = null, string $message = ''): void
    {
        self::assertThat(self::getNotifierNotificationEvents(), new NotifierConstraint\NotificationCount($count, $transportName), $message);
    }

    public static function assertQueuedNotificationCount(int $count, ?string $transportName = null, string $message = ''): void
    {
        self::assertThat(self::getNotifierNotificationEvents(), new NotifierConstraint\NotificationCount($count, $transportName, true), $message);
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
}
