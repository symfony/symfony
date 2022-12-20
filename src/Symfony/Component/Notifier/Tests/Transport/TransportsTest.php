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
            'one' => $one = self::createMock(TransportInterface::class),
        ]);

        $message = new ChatMessage('subject');

        $one->method('supports')->with($message)->willReturn(true);

        $one->expects(self::once())->method('send')->willReturn(new SentMessage($message, 'one'));

        $sentMessage = $transports->send($message);

        self::assertSame($message, $sentMessage->getOriginalMessage());
        self::assertSame('one', $sentMessage->getTransport());
    }

    public function testSendToFirstSupportedTransportIfMessageDoesNotDefineATransport()
    {
        $transports = new Transports([
            'one' => $one = self::createMock(TransportInterface::class),
            'two' => $two = self::createMock(TransportInterface::class),
        ]);

        $message = new ChatMessage('subject');

        $one->method('supports')->with($message)->willReturn(false);
        $two->method('supports')->with($message)->willReturn(true);

        $one->method('send')->with($message)->willReturn(new SentMessage($message, 'one'));
        $two->method('send')->with($message)->willReturn(new SentMessage($message, 'two'));

        $one->expects(self::never())->method('send');
        $two->expects(self::once())->method('send')->willReturn(new SentMessage($message, 'two'));

        $sentMessage = $transports->send($message);

        self::assertSame($message, $sentMessage->getOriginalMessage());
        self::assertSame('two', $sentMessage->getTransport());
    }

    public function testThrowExceptionIfNoSupportedTransportWasFound()
    {
        $transports = new Transports([
            'one' => $one = self::createMock(TransportInterface::class),
        ]);

        $message = new ChatMessage('subject');

        $one->method('supports')->with($message)->willReturn(false);

        self::expectException(LogicException::class);
        self::expectExceptionMessage('None of the available transports support the given message (available transports: "one"');

        $transports->send($message);
    }

    public function testThrowExceptionIfTransportDefinedByMessageIsNotSupported()
    {
        $transports = new Transports([
            'one' => $one = self::createMock(TransportInterface::class),
            'two' => $two = self::createMock(TransportInterface::class),
        ]);

        $message = new ChatMessage('subject');
        $message->transport('one');

        $one->method('supports')->with($message)->willReturn(false);
        $two->method('supports')->with($message)->willReturn(true);

        self::expectException(LogicException::class);
        self::expectExceptionMessage('The "one" transport does not support the given message.');

        $transports->send($message);
    }

    public function testThrowExceptionIfTransportDefinedByMessageDoesNotExist()
    {
        $transports = new Transports([
            'one' => $one = self::createMock(TransportInterface::class),
        ]);

        $message = new ChatMessage('subject');
        $message->transport('two');

        $one->method('supports')->with($message)->willReturn(false);

        self::expectException(InvalidArgumentException::class);
        self::expectExceptionMessage('The "two" transport does not exist (available transports: "one").');

        $transports->send($message);
    }
}
