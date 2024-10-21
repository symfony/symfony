<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mailer\Tests\Transport;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\Transport\FailoverTransport;
use Symfony\Component\Mailer\Transport\RoundRobinTransport;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Symfony\Component\Mime\RawMessage;

/**
 * @group time-sensitive
 */
class FailoverTransportTest extends TestCase
{
    public function testSendNoTransports()
    {
        $this->expectException(TransportException::class);
        new FailoverTransport([]);
    }

    public function testToString()
    {
        $t1 = $this->createMock(TransportInterface::class);
        $t1->expects($this->once())->method('__toString')->willReturn('t1://local');
        $t2 = $this->createMock(TransportInterface::class);
        $t2->expects($this->once())->method('__toString')->willReturn('t2://local');
        $t = new FailoverTransport([$t1, $t2]);
        $this->assertEquals('failover(t1://local t2://local)', (string) $t);
    }

    public function testSendFirstWork()
    {
        $t1 = $this->createMock(TransportInterface::class);
        $t1->expects($this->exactly(3))->method('send');
        $t2 = $this->createMock(TransportInterface::class);
        $t2->expects($this->never())->method('send');
        $t = new FailoverTransport([$t1, $t2]);
        $t->send(new RawMessage(''));
        $this->assertTransports($t, 1, []);
        $t->send(new RawMessage(''));
        $this->assertTransports($t, 1, []);
        $t->send(new RawMessage(''));
        $this->assertTransports($t, 1, []);
    }

    public function testSendAllDead()
    {
        $t1 = $this->createMock(TransportInterface::class);
        $t1->expects($this->once())->method('send')->willThrowException(new TransportException());
        $t2 = $this->createMock(TransportInterface::class);
        $t2->expects($this->once())->method('send')->willThrowException(new TransportException());
        $t = new FailoverTransport([$t1, $t2]);
        $this->expectException(TransportException::class);
        $this->expectExceptionMessage('All transports failed.');
        $t->send(new RawMessage(''));
        $this->assertTransports($t, 0, [$t1, $t2]);
    }

    public function testSendOneDead()
    {
        $t1 = $this->createMock(TransportInterface::class);
        $t1->expects($this->once())->method('send')->willThrowException(new TransportException());
        $t2 = $this->createMock(TransportInterface::class);
        $t2->expects($this->exactly(3))->method('send');
        $t = new FailoverTransport([$t1, $t2]);
        $t->send(new RawMessage(''));
        $this->assertTransports($t, 0, [$t1]);
        $t->send(new RawMessage(''));
        $this->assertTransports($t, 0, [$t1]);
        $t->send(new RawMessage(''));
        $this->assertTransports($t, 0, [$t1]);
    }

    public function testSendOneDeadAndRecoveryWithinRetryPeriod()
    {
        $t1 = $this->createMock(TransportInterface::class);

        $t1Matcher = $this->any();
        $t1->expects($t1Matcher)
            ->method('send')
            ->willReturnCallback(function () use ($t1Matcher) {
                if (1 === $t1Matcher->getInvocationCount()) {
                    throw new TransportException();
                }

                return null;
            });

        $t2 = $this->createMock(TransportInterface::class);
        $t2Matcher = $this->exactly(4);
        $t2->expects($t2Matcher)
            ->method('send')
            ->willReturnCallback(function () use ($t2Matcher) {
                if (4 === $t2Matcher->getInvocationCount()) {
                    throw new TransportException();
                }

                return null;
            });

        $t = new FailoverTransport([$t1, $t2], 6);
        $t->send(new RawMessage('')); // t1>fail - t2>sent
        $this->assertTransports($t, 0, [$t1]);
        sleep(4);
        $t->send(new RawMessage('')); // t2>sent
        $this->assertTransports($t, 0, [$t1]);
        sleep(4);
        $t->send(new RawMessage('')); // t2>sent
        $this->assertTransports($t, 0, [$t1]);
        sleep(4);
        $t->send(new RawMessage('')); // t2>fail - t1>sent
        $this->assertTransports($t, 1, [$t2]);
        sleep(4);
        $t->send(new RawMessage('')); // t1>sent
        $this->assertTransports($t, 1, [$t2]);
    }

    public function testSendAllDeadWithinRetryPeriod()
    {
        $t1 = $this->createMock(TransportInterface::class);
        $t1->method('send')->willThrowException(new TransportException());
        $t1->expects($this->once())->method('send');
        $t2 = $this->createMock(TransportInterface::class);
        $matcher = $this->exactly(3);
        $t2->expects($matcher)
            ->method('send')
            ->willReturnCallback(function () use ($matcher) {
                if (3 === $matcher->getInvocationCount()) {
                    throw new TransportException();
                }

                return null;
            });
        $t = new FailoverTransport([$t1, $t2], 40);
        $t->send(new RawMessage(''));
        sleep(4);
        $t->send(new RawMessage(''));
        sleep(4);
        $this->expectException(TransportException::class);
        $this->expectExceptionMessage('All transports failed.');
        $t->send(new RawMessage(''));
    }

    public function testSendOneDeadButRecover()
    {
        $t1Matcher = $this->any();
        $t1 = $this->createMock(TransportInterface::class);
        $t1->expects($t1Matcher)->method('send')->willReturnCallback(function () use ($t1Matcher) {
            if (1 === $t1Matcher->getInvocationCount()) {
                throw new TransportException();
            }

            return null;
        });

        $t2 = $this->createMock(TransportInterface::class);
        $matcher = $this->exactly(3);
        $t2->expects($matcher)
            ->method('send')
            ->willReturnCallback(function () use ($matcher) {
                if (3 === $matcher->getInvocationCount()) {
                    throw new TransportException();
                }

                return null;
            });
        $t = new FailoverTransport([$t1, $t2], 1);
        $t->send(new RawMessage(''));
        sleep(1);
        $t->send(new RawMessage(''));
        sleep(1);
        $t->send(new RawMessage(''));
    }

    private function assertTransports(RoundRobinTransport $transport, int $cursor, array $deadTransports)
    {
        $p = new \ReflectionProperty(RoundRobinTransport::class, 'cursor');
        $p->setAccessible(true);
        $this->assertSame($cursor, $p->getValue($transport));

        $p = new \ReflectionProperty(RoundRobinTransport::class, 'deadTransports');
        $p->setAccessible(true);
        $this->assertSame($deadTransports, iterator_to_array($p->getValue($transport)));
    }
}
