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
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\Transport\RoundRobinTransport;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Symfony\Component\Mime\RawMessage;

/**
 * @group time-sensitive
 */
class RoundRobinTransportTest extends TestCase
{
    public function testSendNoTransports()
    {
        $this->expectException(TransportException::class);
        new RoundRobinTransport([]);
    }

    public function testToString()
    {
        $t1 = $this->createMock(TransportInterface::class);
        $t1->expects($this->once())->method('__toString')->willReturn('t1://local');
        $t2 = $this->createMock(TransportInterface::class);
        $t2->expects($this->once())->method('__toString')->willReturn('t2://local');
        $t = new RoundRobinTransport([$t1, $t2]);
        $this->assertEquals('roundrobin(t1://local t2://local)', (string) $t);
    }

    public function testSendAlternate()
    {
        $t1 = $this->createMock(TransportInterface::class);
        $t1->expects($this->atLeast(1))->method('send');
        $t2 = $this->createMock(TransportInterface::class);
        $t2->expects($this->atLeast(1))->method('send');
        $t = new RoundRobinTransport([$t1, $t2]);
        $t->send(new RawMessage(''));
        $cursor = $this->assertTransports($t, -1, []);
        $t->send(new RawMessage(''));
        $cursor = $this->assertTransports($t, 0 === $cursor ? 1 : 0, []);
        $t->send(new RawMessage(''));
        $this->assertTransports($t, 0 === $cursor ? 1 : 0, []);
    }

    public function testSendAllDead()
    {
        $t1 = $this->createMock(TransportInterface::class);
        $t1->expects($this->once())->method('send')->willThrowException(new TransportException());
        $t2 = $this->createMock(TransportInterface::class);
        $t2->expects($this->once())->method('send')->willThrowException(new TransportException());
        $t = new RoundRobinTransport([$t1, $t2]);
        $p = new \ReflectionProperty($t, 'cursor');
        $p->setValue($t, 0);

        try {
            $t->send(new RawMessage(''));
        } catch (\Exception $e) {
            $this->assertInstanceOf(TransportException::class, $e);
            $this->assertStringContainsString('All transports failed.', $e->getMessage());
            $this->assertTransports($t, 0, [$t1, $t2]);

            return;
        }

        $this->fail('The expected exception was not thrown.');
    }

    public function testSendOneDead()
    {
        $t1 = $this->createMock(TransportInterface::class);
        $t1->expects($this->once())->method('send')->willThrowException(new TransportException());
        $t2 = $this->createMock(TransportInterface::class);
        $t2->expects($this->exactly(3))->method('send');
        $t = new RoundRobinTransport([$t1, $t2]);
        $p = new \ReflectionProperty($t, 'cursor');
        $p->setValue($t, 0);
        $t->send(new RawMessage(''));
        $this->assertTransports($t, 0, [$t1]);
        $t->send(new RawMessage(''));
        $this->assertTransports($t, 0, [$t1]);
        $t->send(new RawMessage(''));
        $this->assertTransports($t, 0, [$t1]);
    }

    public function testSendOneDeadAndRecoveryNotWithinRetryPeriod()
    {
        $t1 = $this->createMock(TransportInterface::class);
        $t1->expects($this->exactly(4))->method('send');
        $t2 = $this->createMock(TransportInterface::class);
        $t2->expects($this->once())->method('send')->willThrowException(new TransportException());
        $t = new RoundRobinTransport([$t1, $t2], 60);
        $p = new \ReflectionProperty($t, 'cursor');
        $p->setValue($t, 0);
        $t->send(new RawMessage(''));
        $this->assertTransports($t, 1, []);
        $t->send(new RawMessage(''));
        $this->assertTransports($t, 1, [$t2]);
        $t->send(new RawMessage(''));
        $this->assertTransports($t, 1, [$t2]);
        $t->send(new RawMessage(''));
        $this->assertTransports($t, 1, [$t2]);
    }

    public function testSendOneDeadAndRecoveryWithinRetryPeriod()
    {
        $t1 = $this->createMock(TransportInterface::class);
        $t1->expects($this->exactly(3))->method('send');

        $t2 = $this->createMock(TransportInterface::class);
        $t2->expects($this->exactly(2))
            ->method('send')
            ->willReturnCallback(function () {
                static $call = 0;

                if (1 === ++$call) {
                    throw new TransportException();
                }

                return null;
            });
        $t = new RoundRobinTransport([$t1, $t2], 3);
        $p = new \ReflectionProperty($t, 'cursor');
        $p->setValue($t, 0);
        $t->send(new RawMessage(''));
        $this->assertTransports($t, 1, []);
        $t->send(new RawMessage(''));
        $this->assertTransports($t, 1, [$t2]);
        sleep(5);
        $t->send(new RawMessage(''));
        $this->assertTransports($t, 0, []);
        $t->send(new RawMessage(''));
        $this->assertTransports($t, 1, []);
    }

    public function testFailureDebugInformation()
    {
        $t1 = $this->createMock(TransportInterface::class);
        $e1 = new TransportException();
        $e1->appendDebug('Debug message 1');
        $t1->expects($this->once())->method('send')->willThrowException($e1);
        $t1->expects($this->once())->method('__toString')->willReturn('t1');

        $t2 = $this->createMock(TransportInterface::class);
        $e2 = new TransportException();
        $e2->appendDebug('Debug message 2');
        $t2->expects($this->once())->method('send')->willThrowException($e2);
        $t2->expects($this->once())->method('__toString')->willReturn('t2');

        $t = new RoundRobinTransport([$t1, $t2]);

        try {
            $t->send(new RawMessage(''));
        } catch (TransportExceptionInterface $e) {
            $this->assertStringContainsString('Transport "t1": Debug message 1', $e->getDebug());
            $this->assertStringContainsString('Transport "t2": Debug message 2', $e->getDebug());

            return;
        }

        $this->fail('Expected exception was not thrown!');
    }

    private function assertTransports(RoundRobinTransport $transport, int $cursor, array $deadTransports)
    {
        $p = new \ReflectionProperty($transport, 'cursor');
        if (-1 !== $cursor) {
            $this->assertSame($cursor, $p->getValue($transport));
        }
        $cursor = $p->getValue($transport);

        $p = new \ReflectionProperty($transport, 'deadTransports');
        $this->assertSame($deadTransports, iterator_to_array($p->getValue($transport)));

        return $cursor;
    }
}
