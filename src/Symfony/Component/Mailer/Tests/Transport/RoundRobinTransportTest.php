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

    public function testSendAlternate()
    {
        $t1 = $this->createMock(TransportInterface::class);
        $t1->expects($this->exactly(2))->method('send');
        $t2 = $this->createMock(TransportInterface::class);
        $t2->expects($this->once())->method('send');
        $t = new RoundRobinTransport([$t1, $t2]);
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
        $t = new RoundRobinTransport([$t1, $t2]);
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
        $t = new RoundRobinTransport([$t1, $t2]);
        $t->send(new RawMessage(''));
        $t->send(new RawMessage(''));
        $t->send(new RawMessage(''));
    }

    public function testSendOneDeadButRecover()
    {
        $t1 = $this->createMock(TransportInterface::class);
        $t1->expects($this->at(0))->method('send')->will($this->throwException(new TransportException()));
        $t1->expects($this->at(1))->method('send');
        $t2 = $this->createMock(TransportInterface::class);
        $t2->expects($this->once())->method('send');
        $t = new RoundRobinTransport([$t1, $t2], 1);
        $t->send(new RawMessage(''));
        sleep(2);
        $t->send(new RawMessage(''));
    }
}
