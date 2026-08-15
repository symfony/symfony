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
use MongoDB\BSON\UTCDateTime;
use MongoDB\Client;
use MongoDB\Collection;
use MongoDB\DeleteResult;
use MongoDB\Driver\CursorInterface;
use MongoDB\Driver\Exception\RuntimeException;
use MongoDB\Driver\WriteConcern;
use MongoDB\InsertOneResult;
use MongoDB\Model\BSONDocument;
use MongoDB\Operation\FindOneAndUpdate;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Messenger\Bridge\MongoDb\Transport\Connection;
use Symfony\Component\Messenger\Exception\InvalidArgumentException;
use Symfony\Component\Messenger\Exception\TransportException;

class ConnectionTest extends TestCase
{
    public function testFromDsn()
    {
        $collection = $this->createStub(Collection::class);
        $client = $this->createMock(Client::class);
        $client->expects($this->once())
            ->method('getCollection')
            ->with('db', 'messenger_messages')
            ->willReturn($collection);

        $this->assertInstanceOf(Connection::class, Connection::fromDsn('mongodb://localhost:27017/db', [], $client));
    }

    public function testFromDsnWithOptions()
    {
        $collection = $this->createStub(Collection::class);
        $client = $this->createMock(Client::class);
        $client->expects($this->once())
            ->method('getCollection')
            ->with('some_db', 'some_collection')
            ->willReturn($collection);

        $this->assertInstanceOf(Connection::class, Connection::fromDsn('mongodb://localhost:27017', ['database' => 'some_db', 'collection_name' => 'some_collection'], $client));
    }

    /**
     * @param array{database: string, collection_name: string, queue_name: string, redeliver_timeout: int} $expectedConfiguration
     */
    #[DataProvider('buildConfigurationProvider')]
    public function testBuildConfiguration(string $dsn, array $options, array $expectedConfiguration, string $expectedUri)
    {
        [$configuration, $uri] = Connection::buildConfiguration($dsn, $options);

        $this->assertEquals($expectedConfiguration, $configuration);
        $this->assertSame($expectedUri, $uri);
    }

    public static function buildConfigurationProvider(): iterable
    {
        $defaultConfiguration = [
            'database' => 'db',
            'collection_name' => 'messenger_messages',
            'queue_name' => 'default',
            'redeliver_timeout' => 3600,
        ];

        yield 'database from the DSN path' => [
            'mongodb://localhost:27017/db',
            [],
            $defaultConfiguration,
            'mongodb://localhost:27017/db',
        ];

        yield 'settings from the DSN query string are extracted' => [
            'mongodb://localhost:27017/db?collection_name=my_collection&queue_name=my_queue&redeliver_timeout=100',
            [],
            ['database' => 'db', 'collection_name' => 'my_collection', 'queue_name' => 'my_queue', 'redeliver_timeout' => 100] + $defaultConfiguration,
            'mongodb://localhost:27017/db',
        ];

        yield 'driver options in the query string are kept' => [
            'mongodb+srv://localhost/db?replicaSet=repl&queue_name=my_queue&appname=my_app',
            [],
            ['queue_name' => 'my_queue'] + $defaultConfiguration,
            'mongodb+srv://localhost/db?replicaSet=repl&appname=my_app',
        ];

        yield 'options take precedence over the DSN' => [
            'mongodb://localhost:27017/db?queue_name=from_query',
            ['database' => 'other_db', 'queue_name' => 'from_options'],
            ['database' => 'other_db', 'queue_name' => 'from_options'] + $defaultConfiguration,
            'mongodb://localhost:27017/db',
        ];
    }

    #[DataProvider('invalidDsnProvider')]
    public function testInvalidDsn(string $dsn, array $options, string $expectedMessage)
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage($expectedMessage);

        Connection::buildConfiguration($dsn, $options);
    }

    public static function invalidDsnProvider(): iterable
    {
        yield 'invalid scheme' => [
            'doctrine://default',
            [],
            'The given MongoDB Messenger DSN is invalid. Expecting "mongodb://" or "mongodb+srv://".',
        ];

        yield 'missing database' => [
            'mongodb://localhost:27017',
            [],
            'The MongoDB Messenger transport requires a "database", provide it in the DSN path or as an option.',
        ];

        yield 'unknown option' => [
            'mongodb://localhost:27017/db',
            ['foo' => 'bar'],
            'Unknown option found: [foo]. Allowed options are [database, collection_name, queue_name, redeliver_timeout].',
        ];

        yield 'invalid redeliver_timeout' => [
            'mongodb://localhost:27017/db',
            ['redeliver_timeout' => 'invalid'],
            'The "redeliver_timeout" option must be an integer, "string" given.',
        ];
    }

    public function testGet()
    {
        $collection = $this->createMock(Collection::class);

        $clock = new MockClock();
        $connection = new Connection($collection, 'foobar', 100, $clock);
        $document = $this->createDocumentDeliveredTo($connection->getUniqueId());

        $collection->expects($this->once())
            ->method('findOneAndUpdate')
            ->with(
                $this->equalTo([
                    '$or' => [
                        ['deliveredAt' => null],
                        ['deliveredAt' => [
                            '$lt' => new UTCDateTime($clock->now()->modify('-100 seconds')),
                        ]],
                    ],
                    'availableAt' => ['$lte' => new UTCDateTime($clock->now())],
                    'queueName' => 'foobar',
                ]),
                $this->equalTo([
                    '$set' => [
                        'deliveredTo' => $connection->getUniqueId(),
                        'deliveredAt' => new UTCDateTime($clock->now()),
                    ],
                ]),
                $this->equalTo([
                    'writeConcern' => new WriteConcern(WriteConcern::MAJORITY),
                    'returnDocument' => FindOneAndUpdate::RETURN_DOCUMENT_AFTER,
                    'sort' => ['availableAt' => 1],
                    'typeMap' => ['root' => BSONDocument::class],
                ])
            )
            ->willReturn($document);

        $this->assertSame($document, $connection->get());
    }

    public function testGetWrapsMongoExceptions()
    {
        $collection = $this->createStub(Collection::class);
        $collection->method('findOneAndUpdate')
            ->willThrowException(new RuntimeException('Foo bar baz'));

        $connection = new Connection($collection, 'queueName', 100);

        $this->expectException(TransportException::class);
        $this->expectExceptionMessage('Foo bar baz');

        $connection->get();
    }

    public function testGetWithEmptyCollection()
    {
        $collection = $this->createMock(Collection::class);
        $collection
            ->expects($this->once())
            ->method('findOneAndUpdate')
            ->willReturn(null);
        $connection = new Connection($collection, 'default', 3_600);

        $this->assertNull($connection->get());
    }

    public function testGetWithUnmatchedDeliveredAt()
    {
        $collection = $this->createMock(Collection::class);
        $collection->expects($this->once())
            ->method('findOneAndUpdate')
            ->willReturn($this->createDocumentDeliveredTo('someoneElse'));
        $connection = new Connection($collection, 'default', 3_600);

        $this->assertNull($connection->get());
    }

    public function testSend()
    {
        $insertOneResult = $this->createStub(InsertOneResult::class);
        $objectId = new ObjectId();
        $insertOneResult->method('getInsertedId')
            ->willReturn($objectId);

        $clock = new MockClock();
        $collection = $this->createMock(Collection::class);
        $collection->expects($this->once())
            ->method('insertOne')
            ->with(
                $this->callback(static function ($document) use ($clock): bool {
                    self::assertInstanceOf(BSONDocument::class, $document);
                    self::assertSame('serializedEnvelope', $document->body);
                    self::assertEquals(new BSONDocument(['type' => 'foo']), $document->headers);
                    self::assertSame('foobar', $document->queueName);
                    self::assertEquals(new UTCDateTime($clock->now()), $document->createdAt);
                    self::assertEquals(new UTCDateTime($clock->now()), $document->availableAt);

                    return true;
                }),
                ['writeConcern' => new WriteConcern(WriteConcern::MAJORITY)]
            )
            ->willReturn($insertOneResult);

        $connection = new Connection($collection, 'foobar', 3_600, $clock);

        $this->assertSame($objectId, $connection->send('serializedEnvelope', ['type' => 'foo']));
    }

    public function testSendWithDelay()
    {
        $insertOneResult = $this->createStub(InsertOneResult::class);
        $objectId = new ObjectId();
        $insertOneResult->method('getInsertedId')
            ->willReturn($objectId);

        $clock = new MockClock();
        $collection = $this->createMock(Collection::class);
        $collection->expects($this->once())
            ->method('insertOne')
            ->with(
                $this->callback(static function ($document) use ($clock): bool {
                    self::assertInstanceOf(BSONDocument::class, $document);
                    self::assertEquals(new UTCDateTime($clock->now()), $document->createdAt);
                    self::assertEquals(new UTCDateTime($clock->now()->modify('+100 seconds')), $document->availableAt);

                    return true;
                }),
                ['writeConcern' => new WriteConcern(WriteConcern::MAJORITY)]
            )
            ->willReturn($insertOneResult);

        $connection = new Connection($collection, 'foobar', 3_600, $clock);

        $this->assertSame($objectId, $connection->send('serializedEnvelope', [], 100_000));
    }

    public function testSendWrapsMongoExceptions()
    {
        $collection = $this->createStub(Collection::class);
        $collection->method('insertOne')
            ->willThrowException(new RuntimeException('Foo bar baz'));

        $connection = new Connection($collection, 'queueName', 100);

        $this->expectException(TransportException::class);
        $this->expectExceptionMessage('Foo bar baz');

        $connection->send('body');
    }

    /**
     * @return array{int, bool}[]
     */
    public static function deleteCountProvider(): array
    {
        return [
            [2, true],
            [1, true],
            [0, false],
        ];
    }

    #[DataProvider('deleteCountProvider')]
    public function testAck(int $deletedCount, bool $expectedResult)
    {
        $collection = $this->createMock(Collection::class);
        $objectId = new ObjectId();
        $deleteResult = $this->createStub(DeleteResult::class);
        $deleteResult->method('getDeletedCount')
            ->willReturn($deletedCount);
        $collection->expects($this->once())
            ->method('deleteOne')
            ->with($this->equalTo(['_id' => $objectId]), $this->anything())
            ->willReturn($deleteResult);

        $connection = new Connection($collection, 'queueName', 100);

        $this->assertSame($expectedResult, $connection->ack((string) $objectId));
    }

    public function testAckWrapsMongoExceptions()
    {
        $collection = $this->createStub(Collection::class);
        $collection->method('deleteOne')
            ->willThrowException(new RuntimeException('Foo bar baz'));

        $connection = new Connection($collection, 'queueName', 100);

        $this->expectException(TransportException::class);
        $this->expectExceptionMessage('Foo bar baz');

        $connection->ack((string) new ObjectId());
    }

    #[DataProvider('deleteCountProvider')]
    public function testReject(int $deletedCount, bool $expectedResult)
    {
        $collection = $this->createMock(Collection::class);
        $objectId = new ObjectId();
        $deleteResult = $this->createStub(DeleteResult::class);
        $deleteResult->method('getDeletedCount')
            ->willReturn($deletedCount);
        $collection->expects($this->once())
            ->method('deleteOne')
            ->with($this->equalTo(['_id' => $objectId]), $this->anything())
            ->willReturn($deleteResult);

        $connection = new Connection($collection, 'queueName', 100);

        $this->assertSame($expectedResult, $connection->reject((string) $objectId));
    }

    public function testGetMessageCount()
    {
        $collection = $this->createMock(Collection::class);
        $collection->expects($this->once())
            ->method('countDocuments')
            ->willReturn(7);

        $connection = new Connection($collection, 'queueName', 100);

        $this->assertSame(7, $connection->getMessageCount());
    }

    public function testFind()
    {
        $document = new BSONDocument();
        $objectId = new ObjectId();
        $collection = $this->createMock(Collection::class);
        $collection->expects($this->once())
            ->method('findOne')
            ->with(
                $this->equalTo(['_id' => $objectId]),
                ['typeMap' => ['root' => BSONDocument::class]]
            )
            ->willReturn($document);

        $connection = new Connection($collection, 'queueName', 100);

        $this->assertSame($document, $connection->find((string) $objectId));
    }

    public function testFindAll()
    {
        $cursor = $this->createStub(CursorInterface::class);
        $collection = $this->createMock(Collection::class);
        $collection->expects($this->once())
            ->method('find')
            ->with($this->anything(), $this->callback(static function (array $options): bool {
                self::assertSame(50, $options['limit']);
                self::assertSame(['root' => BSONDocument::class], $options['typeMap']);

                return true;
            }))
            ->willReturn($cursor);

        $connection = new Connection($collection, 'queueName', 100);

        $this->assertSame($cursor, $connection->findAll(50));
    }

    public function testDeleteAll()
    {
        $collection = $this->createMock(Collection::class);
        $collection->expects($this->once())
            ->method('deleteMany')
            ->with(['queueName' => 'queueName']);

        $connection = new Connection($collection, 'queueName', 100);

        $connection->deleteAll();
    }

    public function testSetup()
    {
        $collection = $this->createMock(Collection::class);
        $collection->expects($this->once())
            ->method('createIndex')
            ->with([
                'availableAt' => 1,
                'queueName' => 1,
                'deliveredAt' => 1,
            ]);

        $connection = new Connection($collection, 'queueName', 100);

        $connection->setup();
    }

    private function createDocumentDeliveredTo(string $deliveredTo): BSONDocument
    {
        $document = new BSONDocument();
        $document->deliveredTo = $deliveredTo;

        return $document;
    }
}
