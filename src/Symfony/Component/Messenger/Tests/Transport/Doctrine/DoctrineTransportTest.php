<?php

/*
* This file is part of the symfony project.
*
* (c) Vincent Touzet <vincent.touzet@dotsafe.fr>
*
* For the full copyright and license information, please view the LICENSE
* file that was distributed with this source code.
*/

namespace Symfony\Component\Messenger\Tests\Transport\Doctrine;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Tests\Fixtures\DummyMessage;
use Symfony\Component\Messenger\Transport\Doctrine\Connection;
use Symfony\Component\Messenger\Transport\Doctrine\DoctrineTransport;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;
use Symfony\Component\Messenger\Transport\TransportInterface;

class DoctrineTransportTest extends TestCase
{
    public function testItIsATransport()
    {
        $transport = $this->getTransport();

        $this->assertInstanceOf(TransportInterface::class, $transport);
    }

    public function testReceivesMessages()
    {
        $transport = $this->getTransport(
            $serializer = $this->createMock(SerializerInterface::class),
            $connection = $this->createMock(Connection::class)
        );

        $decodedMessage = new DummyMessage('Decoded.');

        $doctrineEnvelope = [
            'id' => '5',
            'body' => 'body',
            'headers' => ['my' => 'header'],
        ];

        $serializer->method('decode')->with(['body' => 'body', 'headers' => ['my' => 'header']])->willReturn(new Envelope($decodedMessage));
        $connection->method('get')->willReturn($doctrineEnvelope);

        $envelopes = $transport->get();
        $this->assertSame($decodedMessage, $envelopes[0]->getMessage());
    }

    private function getTransport(SerializerInterface $serializer = null, Connection $connection = null): DoctrineTransport
    {
        $serializer = $serializer ?: $this->createMock(SerializerInterface::class);
        $connection = $connection ?: $this->createMock(Connection::class);

        return new DoctrineTransport($connection, $serializer);
    }
}
