<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Bridge\MongoDb\Tests\Transport;

require_once __DIR__.'/../Stubs/mongodb.php';

use MongoDB\BSON\ObjectId;
use MongoDB\Model\BSONDocument;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Bridge\MongoDb\Tests\Fixtures\DummyMessage;
use Symfony\Component\Messenger\Bridge\MongoDb\Transport\Connection;
use Symfony\Component\Messenger\Bridge\MongoDb\Transport\MongoDbTransport;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Transport\Receiver\QueueReceiverInterface;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;

class MongoDbTransportTest extends TestCase
{
    public function testGetFromQueuesConsumesFromTheGivenQueues()
    {
        $serializer = $this->createStub(SerializerInterface::class);
        $serializer->method('decode')->willReturn(new Envelope(new DummyMessage('Hi')));

        $connection = $this->createMock(Connection::class);
        $connection->expects($this->exactly(2))
            ->method('get')
            ->with(['foo', 'bar'])
            ->willReturnOnConsecutiveCalls($this->createDocument(), $this->createDocument());

        $transport = new MongoDbTransport($connection, $serializer);

        $this->assertInstanceOf(QueueReceiverInterface::class, $transport);
        $this->assertSame(2, iterator_count($transport->getFromQueues(['foo', 'bar'], 2)));
    }

    private function createDocument(): BSONDocument
    {
        return new BSONDocument(['_id' => new ObjectId()]);
    }
}
