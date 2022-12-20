<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Tests;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Notifier\Chatter;
use Symfony\Component\Notifier\Message\SentMessage;
use Symfony\Component\Notifier\Tests\Transport\DummyMessage;
use Symfony\Component\Notifier\Transport\TransportInterface;

class ChatterTest extends TestCase
{
    /** @var MockObject&TransportInterface */
    private $transport;

    /** @var MockObject&MessageBusInterface */
    private $bus;

    protected function setUp(): void
    {
        $this->transport = self::createMock(TransportInterface::class);
        $this->bus = self::createMock(MessageBusInterface::class);
    }

    public function testSendWithoutBus()
    {
        $message = new DummyMessage();

        $sentMessage = new SentMessage($message, 'any');

        $this->transport
            ->expects(self::once())
            ->method('send')
            ->with($message)
            ->willReturn($sentMessage);

        $chatter = new Chatter($this->transport);
        self::assertSame($sentMessage, $chatter->send($message));
        self::assertSame($message, $sentMessage->getOriginalMessage());
    }

    public function testSendWithBus()
    {
        $message = new DummyMessage();

        $this->transport
            ->expects(self::never())
            ->method('send')
            ->with($message);

        $this->bus
            ->expects(self::once())
            ->method('dispatch')
            ->with($message)
            ->willReturn(new Envelope(new \stdClass()));

        $chatter = new Chatter($this->transport, $this->bus);
        self::assertNull($chatter->send($message));
    }
}
