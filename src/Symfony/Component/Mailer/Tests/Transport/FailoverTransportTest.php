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

    public function testSendFirstWork()
    {
        $t1 = $this->createMock(TransportInterface::class);
        $t1->expects($this->exactly(3))->method('send');
        $t2 = $this->createMock(TransportInterface::class);
        $t2->expects($this->never())->method('send');
        $t = new FailoverTransport([$t1, $t2]);
        $t->send(new RawMessage(''));
        $t->send(new RawMessage(''));
        $t->send(new RawMessage(''));
    }

    public function testSendAllDead()
    {
        $t1 = $this->createMock(TransportInterface::class);
        $t1->expects($this->once())->method('send')->will($this->throwException(new TransportException()));
        $t2 = $this->createMock(TransportInterface::class);
        $t2->expects($this->once())->method('send')->will($this->throwException(new TransportException()));
        $t = new FailoverTransport([$t1, $t2]);
        $this->expectException(TransportException::class);
        $this->expectExceptionMessage('All transports failed.');
        $t->send(new RawMessage(''));
    }

    public function testSendOneDead()
    {
        $t1 = $this->createMock(TransportInterface::class);
        $t1->expects($this->once())->method('send')->will($this->throwException(new TransportException()));
        $t2 = $this->createMock(TransportInterface::class);
        $t2->expects($this->exactly(3))->method('send');
        $t = new FailoverTransport([$t1, $t2]);
        $t->send(new RawMessage(''));
        $t->send(new RawMessage(''));
        $t->send(new RawMessage(''));
    }

    public function testSendOneDeadAndRecoveryNotWithinRetryPeriod()
    {
        $t1 = $this->createMock(TransportInterface::class);
        $t1->expects($this->at(0))->method('send')->will($this->throwException(new TransportException()));
        $t1->expects($this->once())->method('send');
        $t2 = $this->createMock(TransportInterface::class);
        $t2->expects($this->exactly(5))->method('send');
        $t = new FailoverTransport([$t1, $t2], 40);
        $t->send(new RawMessage(''));
        sleep(4);
        $t->send(new RawMessage(''));
        sleep(4);
        $t->send(new RawMessage(''));
        sleep(4);
        $t->send(new RawMessage(''));
        sleep(4);
        $t->send(new RawMessage(''));
    }

    public function testSendOneDeadAndRecoveryWithinRetryPeriod()
    {
        $t1 = $this->createMock(TransportInterface::class);
        $t1->expects($this->at(0))->method('send')->will($this->throwException(new TransportException()));
        $t1->expects($this->at(1))->method('send');
        $t1->expects($this->exactly(3))->method('send');
        $t2 = $this->createMock(TransportInterface::class);
        $t2->expects($this->at(0))->method('send');
        $t2->expects($this->at(1))->method('send');
        $t2->expects($this->at(2))->method('send');
        $t2->expects($this->at(3))->method('send')->will($this->throwException(new TransportException()));
        $t2->expects($this->exactly(4))->method('send');
        $t = new FailoverTransport([$t1, $t2], 6);
        $t->send(new RawMessage('')); // t1>fail - t2>sent
        sleep(4);
        $t->send(new RawMessage('')); // t2>sent
        sleep(4);
        $t->send(new RawMessage('')); // t2>sent
        sleep(4);
        $t->send(new RawMessage('')); // t2>fail - t1>sent
        sleep(4);
        $t->send(new RawMessage('')); // t1>sent
    }

    public function testSendAllDeadWithinRetryPeriod()
    {
        $t1 = $this->createMock(TransportInterface::class);
        $t1->expects($this->at(0))->method('send')->will($this->throwException(new TransportException()));
        $t1->expects($this->once())->method('send');
        $t2 = $this->createMock(TransportInterface::class);
        $t2->expects($this->at(0))->method('send');
        $t2->expects($this->at(1))->method('send');
        $t2->expects($this->at(2))->method('send')->will($this->throwException(new TransportException()));
        $t2->expects($this->exactly(3))->method('send');
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
        $t1 = $this->createMock(TransportInterface::class);
        $t1->expects($this->at(0))->method('send')->will($this->throwException(new TransportException()));
        $t1->expects($this->at(1))->method('send');
        $t2 = $this->createMock(TransportInterface::class);
        $t2->expects($this->at(0))->method('send');
        $t2->expects($this->at(1))->method('send');
        $t2->expects($this->at(2))->method('send')->will($this->throwException(new TransportException()));
        $t = new FailoverTransport([$t1, $t2], 1);
        $t->send(new RawMessage(''));
        sleep(1);
        $t->send(new RawMessage(''));
        sleep(1);
        $t->send(new RawMessage(''));
    }
}
