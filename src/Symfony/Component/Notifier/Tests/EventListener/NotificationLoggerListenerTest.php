<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Tests\EventListener;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Notifier\Event\MessageEvent;
use Symfony\Component\Notifier\EventListener\NotificationLoggerListener;
use Symfony\Component\Notifier\Message\SmsMessage;

class NotificationLoggerListenerTest extends TestCase
{
    public function testNotificationsAreLoggedByDefault()
    {
        $listener = new NotificationLoggerListener();
        $listener->onNotification($this->createEvent());

        $this->assertCount(1, $listener->getEvents()->getEvents());
    }

    public function testNotificationsAreDiscardedWhileDisabled()
    {
        $listener = new NotificationLoggerListener(static fn (): bool => true);
        $listener->onNotification($this->createEvent());

        $this->assertSame([], $listener->getEvents()->getEvents());
    }

    public function testDisabledStateIsCheckedOnEachNotification()
    {
        $disabled = true;
        $listener = new NotificationLoggerListener(static function () use (&$disabled): bool { return $disabled; });

        $listener->onNotification($this->createEvent());
        $disabled = false;
        $listener->onNotification($this->createEvent());

        $this->assertCount(1, $listener->getEvents()->getEvents());
    }

    public function testResetDropsLoggedNotifications()
    {
        $listener = new NotificationLoggerListener();
        $listener->onNotification($this->createEvent());
        $listener->reset();

        $this->assertSame([], $listener->getEvents()->getEvents());
    }

    private function createEvent(): MessageEvent
    {
        return new MessageEvent(new SmsMessage('+37255555555', 'Hello!'));
    }
}
