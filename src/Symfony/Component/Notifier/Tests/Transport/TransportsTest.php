<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Tests\Transport;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Notifier\Exception\InvalidArgumentException;
use Symfony\Component\Notifier\Exception\LogicException;
use Symfony\Component\Notifier\Message\ChatMessage;
use Symfony\Component\Notifier\Message\SentMessage;
use Symfony\Component\Notifier\Transport\TransportInterface;
use Symfony\Component\Notifier\Transport\Transports;

class TransportsTest extends TestCase
{
    public function testSendToTransportDefinedByMessage()
    {
        $transports = new Transports([
            'one' => $one = $this->createMock(TransportInterface::class),
        ]);

        $message = new ChatMessage('subject');

        $one->method('supports')->with($message)->willReturn(true);

        $one->expects($this->once())->method('send')->willReturn(new SentMessage($message, 'one'));

        $sentMessage = $transports->send($message);

        $this->assertSame($message, $sentMessage->getOriginalMessage());
        $this->assertSame('one', $sentMessage->getTransport());
    }

    public function testSendToFirstSupportedTransportIfMessageDoesNotDefineATransport()
    {
        $transports = new Transports([
            'one' => $one = $this->createMock(TransportInterface::class),
            'two' => $two = $this->createMock(TransportInterface::class),
        ]);

        $message = new ChatMessage('subject');

        $one->method('supports')->with($message)->willReturn(false);
        $two->method('supports')->with($message)->willReturn(true);

        $one->method('send')->with($message)->willReturn(new SentMessage($message, 'one'));
        $two->method('send')->with($message)->willReturn(new SentMessage($message, 'two'));

        $one->expects($this->never())->method('send');
        $two->expects($this->once())->method('send')->willReturn(new SentMessage($message, 'two'));

        $sentMessage = $transports->send($message);

        $this->assertSame($message, $sentMessage->getOriginalMessage());
        $this->assertSame('two', $sentMessage->getTransport());
    }

    public function testThrowExceptionIfNoSupportedTransportWasFound()
    {
        $transports = new Transports([
            'one' => $one = $this->createMock(TransportInterface::class),
        ]);

        $message = new ChatMessage('subject');

        $one->method('supports')->with($message)->willReturn(false);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('None of the available transports support the given message (available transports: "one"');

        $transports->send($message);
    }

    public function testThrowExceptionIfTransportDefinedByMessageIsNotSupported()
    {
        $transports = new Transports([
            'one' => $one = $this->createMock(TransportInterface::class),
            'two' => $two = $this->createMock(TransportInterface::class),
        ]);

        $message = new ChatMessage('subject');
        $message->transport('one');

        $one->method('supports')->with($message)->willReturn(false);
        $two->method('supports')->with($message)->willReturn(true);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('The "one" transport does not support the given message.');

        $transports->send($message);
    }

    public function testThrowExceptionIfTransportDefinedByMessageDoesNotExist()
    {
        $transports = new Transports([
            'one' => $one = $this->createMock(TransportInterface::class),
        ]);

        $message = new ChatMessage('subject');
        $message->transport('two');

        $one->method('supports')->with($message)->willReturn(false);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The "two" transport does not exist (available transports: "one").');

        $transports->send($message);
    }
}
