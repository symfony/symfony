<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Tests\Message;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Notifier\Message\PushMessage;
use Symfony\Component\Notifier\Notification\Notification;

/**
 * @author Tomas Norkūnas <norkunas.tom@gmail.com>
 */
class PushMessageTest extends TestCase
{
    public function testCanBeConstructed()
    {
        $message = new PushMessage('Hello', 'World');

        self::assertSame('Hello', $message->getSubject());
        self::assertSame('World', $message->getContent());
    }

    public function testSetSubject()
    {
        $message = new PushMessage('Hello', 'World');
        $message->subject('dlrow olleH');

        self::assertSame('dlrow olleH', $message->getSubject());
    }

    public function testSetContent()
    {
        $message = new PushMessage('Hello', 'World');
        $message->content('dlrow olleH');

        self::assertSame('dlrow olleH', $message->getContent());
    }

    public function testSetTransport()
    {
        $message = new PushMessage('Hello', 'World');
        $message->transport('next_one');

        self::assertSame('next_one', $message->getTransport());
    }

    public function testCreateFromNotification()
    {
        $notification = new Notification('Hello');
        $notification->content('World');

        $message = PushMessage::fromNotification($notification);

        self::assertSame('Hello', $message->getSubject());
        self::assertSame('World', $message->getContent());
        self::assertSame($notification, $message->getNotification());
    }
}
