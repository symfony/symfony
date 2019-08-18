<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Tests\Transport;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Transport\InMemoryTransport;

/**
 * @author Gary PEGEOT <garypegeot@gmail.com>
 */
class InMemoryTransportTest extends TestCase
{
    /**
     * @var InMemoryTransport
     */
    private $transport;

    protected function setUp(): void
    {
        $this->transport = new InMemoryTransport();
    }

    public function testSend()
    {
        $envelope = new Envelope(new \stdClass());
        $this->transport->send($envelope);
        $this->assertSame([$envelope], $this->transport->getSent());
    }

    public function testQueue()
    {
        $envelope1 = new Envelope(new \stdClass());
        $this->transport->send($envelope1);
        $envelope2 = new Envelope(new \stdClass());
        $this->transport->send($envelope2);
        $this->assertSame([$envelope1, $envelope2], $this->transport->get());
        $this->transport->ack($envelope1);
        $this->assertSame([$envelope2], $this->transport->get());
        $this->transport->reject($envelope2);
        $this->assertSame([], $this->transport->get());
    }

    public function testAck()
    {
        $envelope = new Envelope(new \stdClass());
        $this->transport->ack($envelope);
        $this->assertSame([$envelope], $this->transport->getAcknowledged());
    }

    public function testReject()
    {
        $envelope = new Envelope(new \stdClass());
        $this->transport->reject($envelope);
        $this->assertSame([$envelope], $this->transport->getRejected());
    }

    public function testReset()
    {
        $envelope = new Envelope(new \stdClass());
        $this->transport->send($envelope);
        $this->transport->ack($envelope);
        $this->transport->reject($envelope);

        $this->transport->reset();

        $this->assertEmpty($this->transport->get(), 'Should be empty after reset');
        $this->assertEmpty($this->transport->getAcknowledged(), 'Should be empty after reset');
        $this->assertEmpty($this->transport->getRejected(), 'Should be empty after reset');
        $this->assertEmpty($this->transport->getSent(), 'Should be empty after reset');
    }
}
