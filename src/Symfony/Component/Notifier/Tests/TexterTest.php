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
use Symfony\Component\Notifier\Message\SentMessage;
use Symfony\Component\Notifier\Tests\Transport\DummyMessage;
use Symfony\Component\Notifier\Texter;
use Symfony\Component\Notifier\Transport\TransportInterface;

class TexterTest extends TestCase
{
    /** @var MockObject&TransportInterface */
    private $transport;

    /** @var MockObject&MessageBusInterface */
    private $bus;

    protected function setUp(): void
    {
        $this->transport = $this->createMock(TransportInterface::class);
        $this->bus = $this->createMock(MessageBusInterface::class);
    }

    public function testSendWithoutBus()
    {
        $message = new DummyMessage();
        $sentMessage = new SentMessage($message, 'any');

        $this->transport
            ->expects($this->once())
            ->method('send')
            ->with($message)
            ->willReturn($sentMessage);

        $texter = new Texter($this->transport);
        $this->assertSame($sentMessage, $texter->send($message));
        $this->assertSame($message, $sentMessage->getOriginalMessage());
    }

    public function testSendWithBus()
    {
        $message = new DummyMessage();

        $this->transport
            ->expects($this->never())
            ->method('send')
            ->with($message);

        $this->bus
            ->expects($this->once())
            ->method('dispatch')
            ->with($message)
            ->willReturn(new Envelope(new \stdClass()));

        $texter = new Texter($this->transport, $this->bus);
        $this->assertNull($texter->send($message));
    }
}
