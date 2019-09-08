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
        $t1->expects($this->exactly(2))->method('send');
        $t2 = $this->createMock(TransportInterface::class);
        $t2->expects($this->once())->method('send');
        $t = new RoundRobinTransport([$t1, $t2]);
        $t->send(new RawMessage(''));
        $this->assertTransports($t, 1, []);
        $t->send(new RawMessage(''));
        $this->assertTransports($t, 0, []);
        $t->send(new RawMessage(''));
        $this->assertTransports($t, 1, []);
    }

    public function testSendAllDead()
    {
        $t1 = $this->createMock(TransportInterface::class);
        $t1->expects($this->once())->method('send')->will($this->throwException(new TransportException()));
        $t2 = $this->createMock(TransportInterface::class);
        $t2->expects($this->once())->method('send')->will($this->throwException(new TransportException()));
        $t = new RoundRobinTransport([$t1, $t2]);
        $this->expectException(TransportException::class);
        $this->expectExceptionMessage('All transports failed.');
        $t->send(new RawMessage(''));
        $this->assertTransports($t, 1, [$t1, $t2]);
    }

    public function testSendOneDead()
    {
        $t1 = $this->createMock(TransportInterface::class);
        $t1->expects($this->once())->method('send')->will($this->throwException(new TransportException()));
        $t2 = $this->createMock(TransportInterface::class);
        $t2->expects($this->exactly(3))->method('send');
        $t = new RoundRobinTransport([$t1, $t2]);
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
        $t2->expects($this->once())->method('send')->will($this->throwException(new TransportException()));
        $t = new RoundRobinTransport([$t1, $t2], 60);
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
        $t2->expects($this->at(0))->method('send')->will($this->throwException(new TransportException()));
        $t2->expects($this->at(1))->method('send');
        $t = new RoundRobinTransport([$t1, $t2], 3);
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

    private function assertTransports(RoundRobinTransport $transport, int $cursor, array $deadTransports)
    {
        $p = new \ReflectionProperty($transport, 'cursor');
        $p->setAccessible(true);
        $this->assertSame($cursor, $p->getValue($transport));

        $p = new \ReflectionProperty($transport, 'deadTransports');
        $p->setAccessible(true);
        $this->assertSame($deadTransports, iterator_to_array($p->getValue($transport)));
    }
}
