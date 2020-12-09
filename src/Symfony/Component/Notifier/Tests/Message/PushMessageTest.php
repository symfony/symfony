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
use Symfony\Component\Notifier\Recipient\PushRecipientInterface;
use Symfony\Component\Notifier\Recipient\PushRecipientTrait;

/**
 * @author Tomas Norkūnas <norkunas.tom@gmail.com>
 */
class PushMessageTest extends TestCase
{
    public function testCanBeConstructed()
    {
        $message = new PushMessage('6392d91a-b206-4b7b-a620-cd68e32c3a76', 'Hello', 'World');

        $this->assertSame('6392d91a-b206-4b7b-a620-cd68e32c3a76', $message->getRecipientId());
        $this->assertSame('Hello', $message->getSubject());
        $this->assertSame('World', $message->getContent());
    }

    public function testEnsureNonEmptyRecipientIdOnConstruction()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('"Symfony\Component\Notifier\Message\PushMessage" needs a recipient id, it cannot be empty.');

        new PushMessage('', 'Hello', 'World');
    }

    public function testSetRecipientId()
    {
        $message = new PushMessage('6392d91a-b206-4b7b-a620-cd68e32c3a76', 'Hello', 'World');

        $this->assertSame('6392d91a-b206-4b7b-a620-cd68e32c3a76', $message->getRecipientId());

        $message->recipientId('76ece62b-bcfe-468c-8a78-839aeaa8c5fa');

        $this->assertSame('76ece62b-bcfe-468c-8a78-839aeaa8c5fa', $message->getRecipientId());
    }

    public function testEnsureNonEmptyRecipientIdOnSet()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('"Symfony\Component\Notifier\Message\PushMessage" needs a recipient id, it cannot be empty.');

        $message = new PushMessage('6392d91a-b206-4b7b-a620-cd68e32c3a76', 'Hello', 'World');

        $this->assertSame('6392d91a-b206-4b7b-a620-cd68e32c3a76', $message->getRecipientId());

        $message->recipientId('');
    }

    public function testSetSubject()
    {
        $message = new PushMessage('6392d91a-b206-4b7b-a620-cd68e32c3a76', 'Hello', 'World');
        $message->subject('dlrow olleH');

        $this->assertSame('dlrow olleH', $message->getSubject());
    }

    public function testSetContent()
    {
        $message = new PushMessage('6392d91a-b206-4b7b-a620-cd68e32c3a76', 'Hello', 'World');
        $message->content('dlrow olleH');

        $this->assertSame('dlrow olleH', $message->getContent());
    }

    public function testSetTransport()
    {
        $message = new PushMessage('6392d91a-b206-4b7b-a620-cd68e32c3a76', 'Hello', 'World');
        $message->transport('next_one');

        $this->assertSame('next_one', $message->getTransport());
    }

    public function testCreateFromNotification()
    {
        $notification = new Notification('Hello');
        $notification->content('World');

        $message = PushMessage::fromNotification($notification, new PushRecipient('6392d91a-b206-4b7b-a620-cd68e32c3a76'));

        $this->assertSame('Hello', $message->getSubject());
        $this->assertSame('World', $message->getContent());
        $this->assertSame($notification, $message->getNotification());
    }
}

final class PushRecipient implements PushRecipientInterface
{
    use PushRecipientTrait;

    public function __construct(string $pushId)
    {
        $this->pushId = $pushId;
    }
}
