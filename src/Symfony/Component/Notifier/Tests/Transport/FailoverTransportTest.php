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
use Symfony\Component\Notifier\Exception\LogicException;
use Symfony\Component\Notifier\Exception\RuntimeException;
use Symfony\Component\Notifier\Exception\TransportExceptionInterface;
use Symfony\Component\Notifier\Message\SentMessage;
use Symfony\Component\Notifier\Transport\FailoverTransport;
use Symfony\Component\Notifier\Transport\TransportInterface;

/**
 * @group time-sensitive
 */
class FailoverTransportTest extends TestCase
{
    public function testSendNoTransports()
    {
        self::expectException(LogicException::class);

        new FailoverTransport([]);
    }

    public function testToString()
    {
        $t1 = self::createMock(TransportInterface::class);
        $t1->expects(self::once())->method('__toString')->willReturn('t1://local');

        $t2 = self::createMock(TransportInterface::class);
        $t2->expects(self::once())->method('__toString')->willReturn('t2://local');

        $t = new FailoverTransport([$t1, $t2]);

        self::assertEquals('t1://local || t2://local', (string) $t);
    }

    public function testSendMessageNotSupportedByAnyTransport()
    {
        $t1 = self::createMock(TransportInterface::class);
        $t2 = self::createMock(TransportInterface::class);

        $t = new FailoverTransport([$t1, $t2]);

        self::expectException(LogicException::class);

        $t->send(new DummyMessage());
    }

    public function testSendFirstWork()
    {
        $message = new DummyMessage();

        $t1 = self::createMock(TransportInterface::class);
        $t1->method('supports')->with($message)->willReturn(true);
        $t1->expects(self::exactly(3))->method('send')->with($message)->willReturn(new SentMessage($message, 'test'));

        $t2 = self::createMock(TransportInterface::class);
        $t2->expects(self::never())->method('send');

        $t = new FailoverTransport([$t1, $t2]);

        $t->send($message);
        $t->send($message);
        $t->send($message);
    }

    public function testSendAllDead()
    {
        $message = new DummyMessage();

        $t1 = self::createMock(TransportInterface::class);
        $t1->method('supports')->with($message)->willReturn(true);
        $t1->expects(self::once())->method('send')->with($message)->will(self::throwException(self::createMock(TransportExceptionInterface::class)));

        $t2 = self::createMock(TransportInterface::class);
        $t2->method('supports')->with($message)->willReturn(true);
        $t2->expects(self::once())->method('send')->with($message)->will(self::throwException(self::createMock(TransportExceptionInterface::class)));

        $t = new FailoverTransport([$t1, $t2]);

        self::expectException(RuntimeException::class);
        self::expectExceptionMessage('All transports failed.');

        $t->send($message);
    }

    public function testSendOneDead()
    {
        $message = new DummyMessage();

        $t1 = self::createMock(TransportInterface::class);
        $t1->method('supports')->with($message)->willReturn(true);
        $t1->expects(self::once())->method('send')->will(self::throwException(self::createMock(TransportExceptionInterface::class)));

        $t2 = self::createMock(TransportInterface::class);
        $t2->method('supports')->with($message)->willReturn(true);
        $t2->expects(self::exactly(1))->method('send')->with($message)->willReturn(new SentMessage($message, 'test'));

        $t = new FailoverTransport([$t1, $t2]);

        $t->send($message);
    }

    public function testSendAllDeadWithinRetryPeriod()
    {
        $message = new DummyMessage();

        $t1 = self::createMock(TransportInterface::class);
        $t1->method('supports')->with($message)->willReturn(true);
        $t1->method('send')->will(self::throwException(self::createMock(TransportExceptionInterface::class)));
        $t1->expects(self::once())->method('send');
        $t2 = self::createMock(TransportInterface::class);
        $t2->method('supports')->with($message)->willReturn(true);
        $t2->expects(self::exactly(3))
            ->method('send')
            ->willReturnOnConsecutiveCalls(
                new SentMessage($message, 't2'),
                new SentMessage($message, 't2'),
                self::throwException(self::createMock(TransportExceptionInterface::class))
            );
        $t = new FailoverTransport([$t1, $t2], 40);
        $t->send($message);
        sleep(4);
        $t->send($message);
        sleep(4);

        self::expectException(RuntimeException::class);
        self::expectExceptionMessage('All transports failed.');

        $t->send($message);
    }

    public function testSendOneDeadButRecover()
    {
        $message = new DummyMessage();

        $t1 = self::createMock(TransportInterface::class);
        $t1->method('supports')->with($message)->willReturn(true);
        $t1->expects(self::exactly(2))->method('send')->willReturnOnConsecutiveCalls(
            self::throwException(self::createMock(TransportExceptionInterface::class)),
            new SentMessage($message, 't1')
        );
        $t2 = self::createMock(TransportInterface::class);
        $t2->method('supports')->with($message)->willReturn(true);
        $t2->expects(self::exactly(2))->method('send')->willReturnOnConsecutiveCalls(
            new SentMessage($message, 't2'),
            self::throwException(self::createMock(TransportExceptionInterface::class))
        );

        $t = new FailoverTransport([$t1, $t2], 1);

        $t->send($message);
        sleep(2);
        $t->send($message);
    }
}
