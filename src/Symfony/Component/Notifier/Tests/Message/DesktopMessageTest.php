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
use Symfony\Component\Notifier\Message\DesktopMessage;
use Symfony\Component\Notifier\Notification\Notification;

/**
 * @author Ahmed Ghanem <ahmedghanem7361@gmail.com>
 */
class DesktopMessageTest extends TestCase
{
    public function testCanBeConstructed()
    {
        $message = new DesktopMessage('Hello', 'World');

        $this->assertSame('Hello', $message->getSubject());
        $this->assertSame('World', $message->getContent());
    }

    public function testSetSubject()
    {
        $message = new DesktopMessage('Hello', 'World');

        $message->setSubject('dlrow olleH');

        $this->assertSame('dlrow olleH', $message->getSubject());
    }

    public function testSetContent()
    {
        $message = new DesktopMessage('Hello', 'World');

        $message->setContent('dlrow olleH');

        $this->assertSame('dlrow olleH', $message->getContent());
    }

    public function testSetTransport()
    {
        $message = new DesktopMessage('Hello', 'World');

        $message->setTransport('next_one');

        $this->assertSame('next_one', $message->getTransport());
    }

    public function testCreateFromNotification()
    {
        $notification = (new Notification('Hello'))->content('World');
        $message = DesktopMessage::fromNotification($notification);

        $this->assertSame('Hello', $message->getSubject());
        $this->assertSame('World', $message->getContent());
        $this->assertSame($notification, $message->getNotification());
    }
}
