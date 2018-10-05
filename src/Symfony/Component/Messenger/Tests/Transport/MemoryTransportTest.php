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
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\ReceivedStamp;
use Symfony\Component\Messenger\Transport\MemoryTransport;

class MemoryTransportTest extends TestCase
{
    public function testItHandlesEnvelopes()
    {
        $transport = new MemoryTransport(new aBus());
        $transport->send(new Envelope($aMessage = new aMessage()));
        $transport->receive(function (Envelope $envelope) {
            $envelope->getMessage()->setMessage('Lorem ipsum');
        });

        $this->assertEquals('Lorem ipsum', $aMessage->getMessage());
    }

    public function testItStopsReceivingMessages()
    {
        $transport = new MemoryTransport(new aBus());
        $transport->send(new Envelope($aMessage = new aMessage()));
        $transport->receive(function (Envelope $envelope) {
            $envelope->getMessage()->setMessage('Lorem ipsum');
        });

        $transport->stop();
        $transport->send(new Envelope($anotherMessage = new aMessage()));
        $transport->receive(function (Envelope $envelope) {
            $envelope->getMessage()->setMessage('Sic Amet');
        });

        $this->assertEquals('Lorem ipsum', $aMessage->getMessage());
        $this->assertEquals('', $anotherMessage->getMessage());
    }

    public function testItFlushesMessages()
    {
        $transport = new MemoryTransport($aBus = new aBus());
        $transport->send(new Envelope($aMessage = new aMessage('myId')));
        $transport->flush();

        $envelope = $aBus->getEnvelopes()[0];

        $this->assertEquals($envelope->getMessage()->getId(), $aMessage->getId());
        $stamps = $envelope->all();
        $this->assertCount(1, $stamps);
        $this->assertInstanceOf(ReceivedStamp::class, $stamps['Symfony\Component\Messenger\Stamp\ReceivedStamp'][0]);
    }
}

class aMessage
{
    private $message = '';
    private $id = '';

    public function __construct(?string $id = '')
    {
        $this->id = $id;
    }

    public function setMessage(string $message): void
    {
        $this->message = $message;
    }

    public function getMessage(): string
    {
        return $this->message;
    }

    public function getId(): string
    {
        return $this->id;
    }
}

class aBus implements MessageBusInterface
{
    private $envelopes;

    public function dispatch($envelope): Envelope
    {
        $this->envelopes[] = $envelope;

        return $envelope;
    }

    public function getEnvelopes()
    {
        return $this->envelopes;
    }
}
