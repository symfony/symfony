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
use MongoDB\Driver\Exception\BulkWriteException;
use MongoDB\Driver\Exception\RuntimeException;
use MongoDB\Driver\Session;
use MongoDB\Driver\WriteConcern;
use MongoDB\Driver\WriteConcernError;
use MongoDB\Driver\WriteError;
use MongoDB\Driver\WriteResult;
use MongoDB\InsertManyResult;
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
                    'queueName' => ['$in' => ['foobar']],
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

    public function testGetClaimsAcrossSeveralQueuesWithASingleRequest()
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
                    'queueName' => ['$in' => ['foo', 'bar']],
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

        $this->assertSame($document, $connection->get(['foo', 'bar']));
    }

    public function testGetSupportsASingleQueue()
    {
        $collection = $this->createMock(Collection::class);

        $clock = new MockClock();
        $connection = new Connection($collection, 'default', 100, $clock);
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
                    'queueName' => ['$in' => ['foobar']],
                ]),
                $this->anything(),
                $this->anything()
            )
            ->willReturn($document);

        $this->assertSame($document, $connection->get(['foobar']));
    }

    public function testGetThrowsWhenNoQueueIsGiven()
    {
        $connection = new Connection($this->createStub(Collection::class), 'foobar', 100);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('At least one queue name is required.');

        $connection->get([]);
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

    public function testSendWithAnExplicitQueue()
    {
        $insertOneResult = $this->createStub(InsertOneResult::class);
        $insertOneResult->method('getInsertedId')
            ->willReturn(new ObjectId());

        $clock = new MockClock();
        $collection = $this->createMock(Collection::class);
        $collection->expects($this->once())
            ->method('insertOne')
            ->with(
                $this->callback(static function (BSONDocument $document): bool {
                    self::assertSame('bar', $document->queueName);

                    return true;
                }),
                ['writeConcern' => new WriteConcern(WriteConcern::MAJORITY)]
            )
            ->willReturn($insertOneResult);

        $connection = new Connection($collection, 'foobar', 3_600, $clock);

        $connection->send('serializedEnvelope', [], 0, null, 'bar');
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

    public function testSendBatch()
    {
        $clock = new MockClock();
        $collection = $this->createMock(Collection::class);
        $collection->expects($this->once())
            ->method('insertMany')
            ->with(
                $this->callback(static function (array $documents) use (&$inserted, $clock): bool {
                    self::assertTrue(array_is_list($documents));
                    self::assertCount(2, $documents);
                    self::assertInstanceOf(ObjectId::class, $documents[0]->_id);
                    self::assertSame('first', $documents[0]->body);
                    self::assertEquals(new BSONDocument(['type' => 'foo']), $documents[0]->headers);
                    self::assertSame('foobar', $documents[0]->queueName);
                    self::assertEquals(new UTCDateTime($clock->now()), $documents[0]->createdAt);
                    self::assertEquals(new UTCDateTime($clock->now()), $documents[0]->availableAt);
                    self::assertInstanceOf(ObjectId::class, $documents[1]->_id);
                    self::assertSame('second', $documents[1]->body);
                    self::assertSame('bar', $documents[1]->queueName);
                    self::assertEquals(new UTCDateTime($clock->now()->modify('+100 seconds')), $documents[1]->availableAt);
                    $inserted = $documents;

                    return true;
                }),
                ['ordered' => false, 'writeConcern' => new WriteConcern(WriteConcern::MAJORITY)]
            )
            ->willReturn($this->createStub(InsertManyResult::class));

        $connection = new Connection($collection, 'foobar', 3_600, $clock);

        [$ids, $exceptions] = $connection->sendBatch([
            'a' => ['first', ['type' => 'foo'], 0, null, null],
            'b' => ['second', [], 100_000, null, 'bar'],
        ]);

        $this->assertSame(['a' => $inserted[0]->_id, 'b' => $inserted[1]->_id], $ids);
        $this->assertSame([], $exceptions);
    }

    public function testSendBatchInsertsTheMessagesOfEachSessionWithTheirOwnRequest()
    {
        $session = $this->createSession();
        $otherSession = $this->createSession();
        $requests = [];

        $collection = $this->createMock(Collection::class);
        $collection->expects($this->exactly(4))
            ->method('insertMany')
            ->willReturnCallback(function (array $documents, array $options) use (&$requests) {
                $requests[] = [array_map(static fn (BSONDocument $document) => $document->body, $documents), $options['session'] ?? null];

                return $this->createStub(InsertManyResult::class);
            });

        $connection = new Connection($collection, 'foobar', 3_600);

        [$ids, $exceptions] = $connection->sendBatch([
            ['a', [], 0, null, null],
            ['b', [], 0, $session, null],
            ['c', [], 0, $session, null],
            ['d', [], 0, $otherSession, null],
            ['e', [], 0, null, null],
        ]);

        $this->assertSame([[['a'], null], [['b', 'c'], $session], [['d'], $otherSession], [['e'], null]], $requests);
        $this->assertSame([0, 1, 2, 3, 4], array_keys($ids));
        $this->assertSame([], $exceptions);
    }

    public function testSendBatchReportsTheMessagesTheServerRejected()
    {
        $collection = $this->createMock(Collection::class);
        $collection->expects($this->exactly(2))
            ->method('insertMany')
            ->willReturnOnConsecutiveCalls($this->throwException($this->createBulkWriteException(2, false, [1 => 'E11000 duplicate key error'])), $this->createStub(InsertManyResult::class));

        $connection = new Connection($collection, 'foobar', 3_600);

        [$ids, $exceptions] = $connection->sendBatch([
            'a' => ['a', [], 0, null, null],
            'b' => ['b', [], 0, null, null],
            'c' => ['c', [], 0, null, null],
            'd' => ['d', [], 0, $this->createSession(), null],
        ]);

        $this->assertSame(['a', 'c', 'd'], array_keys($ids));
        $this->assertSame(['b'], array_keys($exceptions));
        $this->assertInstanceOf(TransportException::class, $exceptions['b']);
        $this->assertSame('E11000 duplicate key error', $exceptions['b']->getMessage());
    }

    public function testSendBatchReportsTheMessagesNotSentAfterAnErrorStoppedTheInsert()
    {
        $collection = $this->createMock(Collection::class);
        $collection->expects($this->once())
            ->method('insertMany')
            ->willThrowException($bulkWriteException = $this->createBulkWriteException(1, false, [1 => 'E11000 duplicate key error']));

        $connection = new Connection($collection, 'foobar', 3_600);

        [$ids, $exceptions] = $connection->sendBatch([
            'a' => ['a', [], 0, null, null],
            'b' => ['b', [], 0, null, null],
            'c' => ['c', [], 0, null, null],
            'd' => ['d', [], 0, $this->createSession(), null],
        ]);

        $this->assertSame(['a'], array_keys($ids));
        $this->assertSame(['b', 'c', 'd'], array_keys($exceptions));
        $this->assertSame('E11000 duplicate key error', $exceptions['b']->getMessage());
        $this->assertSame('Write failed.', $exceptions['c']->getMessage());
        $this->assertSame($bulkWriteException, $exceptions['c']->getPrevious());
        $this->assertSame($exceptions['c'], $exceptions['d']);
    }

    public function testSendBatchReportsAllMessagesOfARequestThatFailedItsWriteConcernAsNotInserted()
    {
        $collection = $this->createStub(Collection::class);
        $collection->method('insertMany')
            ->willThrowException($this->createBulkWriteException(2, true));

        $connection = new Connection($collection, 'foobar', 3_600);

        [$ids, $exceptions] = $connection->sendBatch([['a', [], 0, null, null], ['b', [], 0, null, null]]);

        $this->assertSame([], $ids);
        $this->assertSame([0, 1], array_keys($exceptions));
    }

    public function testSendBatchStopsAtTheFirstFailedRequest()
    {
        $session = $this->createSession();

        $collection = $this->createMock(Collection::class);
        $collection->expects($this->exactly(2))
            ->method('insertMany')
            ->willReturnOnConsecutiveCalls($this->createStub(InsertManyResult::class), $this->throwException(new RuntimeException('Foo bar baz')));

        $connection = new Connection($collection, 'foobar', 3_600);

        [$ids, $exceptions] = $connection->sendBatch([
            'a' => ['a', [], 0, null, null],
            'b' => ['b', [], 0, $session, null],
            'c' => ['c', [], 0, $session, null],
            'd' => ['d', [], 0, null, null],
        ]);

        $this->assertSame(['a'], array_keys($ids));
        $this->assertSame(['b', 'c', 'd'], array_keys($exceptions));
        $this->assertSame('Foo bar baz', $exceptions['d']->getMessage());
    }

    public function testSendBatchWrapsMongoExceptions()
    {
        $collection = $this->createStub(Collection::class);
        $collection->method('insertMany')
            ->willThrowException(new RuntimeException('Foo bar baz'));

        $connection = new Connection($collection, 'queueName', 100);

        [$ids, $exceptions] = $connection->sendBatch(['a' => ['a', [], 0, null, null], 'b' => ['b', [], 0, null, null]]);

        $this->assertSame([], $ids);
        $this->assertSame(['a', 'b'], array_keys($exceptions));
        $this->assertInstanceOf(TransportException::class, $exceptions['a']);
        $this->assertSame('Foo bar baz', $exceptions['a']->getMessage());
        $this->assertSame($exceptions['a'], $exceptions['b']);
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

        $connection = new Connection($collection, 'queueName', 100);

        $collection->expects($this->once())
            ->method('deleteOne')
            ->with($this->equalTo(['_id' => $objectId]), $this->anything())
            ->willReturn($deleteResult);

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

    private function createSession(): Session
    {
        if (\extension_loaded('mongodb')) {
            $this->markTestSkipped('The driver creates sessions only when connected to a server.');
        }

        return new Session();
    }

    private function createBulkWriteException(int $insertedCount, bool $writeConcernError = false, array $writeErrors = []): BulkWriteException
    {
        if (\extension_loaded('mongodb')) {
            $this->markTestSkipped('The driver creates write results only when connected to a server.');
        }

        return new BulkWriteException('Write failed.', new WriteResult($insertedCount, $writeConcernError ? new WriteConcernError() : null, array_map(static fn (int $index, string $message) => new WriteError($index, $message), array_keys($writeErrors), $writeErrors)));
    }
}
