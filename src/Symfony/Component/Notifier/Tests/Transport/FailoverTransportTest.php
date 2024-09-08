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
        $this->expectException(LogicException::class);

        new FailoverTransport([]);
    }

    public function testToString()
    {
        $t1 = $this->createMock(TransportInterface::class);
        $t1->expects($this->once())->method('__toString')->willReturn('t1://local');

        $t2 = $this->createMock(TransportInterface::class);
        $t2->expects($this->once())->method('__toString')->willReturn('t2://local');

        $t = new FailoverTransport([$t1, $t2]);

        $this->assertEquals('t1://local || t2://local', (string) $t);
    }

    public function testSendMessageNotSupportedByAnyTransport()
    {
        $t1 = $this->createMock(TransportInterface::class);
        $t2 = $this->createMock(TransportInterface::class);

        $t = new FailoverTransport([$t1, $t2]);

        $this->expectException(LogicException::class);

        $t->send(new DummyMessage());
    }

    public function testSendFirstWork()
    {
        $message = new DummyMessage();

        $t1 = $this->createMock(TransportInterface::class);
        $t1->method('supports')->with($message)->willReturn(true);
        $t1->expects($this->exactly(3))->method('send')->with($message)->willReturn(new SentMessage($message, 'test'));

        $t2 = $this->createMock(TransportInterface::class);
        $t2->expects($this->never())->method('send');

        $t = new FailoverTransport([$t1, $t2]);

        $t->send($message);
        $t->send($message);
        $t->send($message);
    }

    public function testSendAllDead()
    {
        $message = new DummyMessage();

        $t1 = $this->createMock(TransportInterface::class);
        $t1->method('supports')->with($message)->willReturn(true);
        $t1->expects($this->once())->method('send')->with($message)->willThrowException($this->createMock(TransportExceptionInterface::class));

        $t2 = $this->createMock(TransportInterface::class);
        $t2->method('supports')->with($message)->willReturn(true);
        $t2->expects($this->once())->method('send')->with($message)->willThrowException($this->createMock(TransportExceptionInterface::class));

        $t = new FailoverTransport([$t1, $t2]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('All transports failed.');

        $t->send($message);
    }

    public function testSendOneDead()
    {
        $message = new DummyMessage();

        $t1 = $this->createMock(TransportInterface::class);
        $t1->method('supports')->with($message)->willReturn(true);
        $t1->expects($this->once())->method('send')->willThrowException($this->createMock(TransportExceptionInterface::class));

        $t2 = $this->createMock(TransportInterface::class);
        $t2->method('supports')->with($message)->willReturn(true);
        $t2->expects($this->exactly(1))->method('send')->with($message)->willReturn(new SentMessage($message, 'test'));

        $t = new FailoverTransport([$t1, $t2]);

        $t->send($message);
    }

    public function testSendAllDeadWithinRetryPeriod()
    {
        $message = new DummyMessage();

        $t1 = $this->createMock(TransportInterface::class);
        $t1->method('supports')->with($message)->willReturn(true);
        $t1->method('send')->willThrowException($this->createMock(TransportExceptionInterface::class));
        $t1->expects($this->once())->method('send');
        $t2 = $this->createMock(TransportInterface::class);
        $t2->method('supports')->with($message)->willReturn(true);

        $matcher = $this->exactly(3);
        $t2->expects($matcher)
            ->method('send')
            ->willReturnCallback(function () use ($matcher, $message) {
                if (3 === $matcher->getInvocationCount()) {
                    throw $this->createMock(TransportExceptionInterface::class);
                }

                return new SentMessage($message, 't2');
            });
        $t = new FailoverTransport([$t1, $t2], 40);
        $t->send($message);
        sleep(4);
        $t->send($message);
        sleep(4);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('All transports failed.');

        $t->send($message);
    }

    public function testSendOneDeadButRecover()
    {
        $message = new DummyMessage();

        $t1 = $this->createMock(TransportInterface::class);
        $t1->method('supports')->with($message)->willReturn(true);

        $t1Matcher = $this->exactly(2);
        $t1->expects($t1Matcher)->method('send')
            ->willReturnCallback(function () use ($t1Matcher, $message) {
                if (1 === $t1Matcher->getInvocationCount()) {
                    throw $this->createMock(TransportExceptionInterface::class);
                }

                return new SentMessage($message, 't1');
            });
        $t2 = $this->createMock(TransportInterface::class);
        $t2->method('supports')->with($message)->willReturn(true);

        $t2Matcher = $this->exactly(2);
        $t2->expects($t2Matcher)->method('send')->willReturnCallback(function () use ($t2Matcher, $message) {
            if (1 === $t2Matcher->getInvocationCount()) {
                return new SentMessage($message, 't1');
            }

            throw $this->createMock(TransportExceptionInterface::class);
        });

        $t = new FailoverTransport([$t1, $t2], 1);

        $t->send($message);
        sleep(2);
        $t->send($message);
    }
}
