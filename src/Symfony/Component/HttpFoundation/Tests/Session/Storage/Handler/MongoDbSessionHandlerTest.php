<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpFoundation\Tests\Session\Storage\Handler;

use MongoDB\Client;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Session\Storage\Handler\MongoDbSessionHandler;

/**
 * @author Markus Bachmann <markus.bachmann@bachi.biz>
 * @group time-sensitive
 * @requires extension mongodb
 */
class MongoDbSessionHandlerTest extends TestCase
{
    /**
     * @var MockObject&Client
     */
    private $mongo;
    private $storage;
    public $options;

    protected function setUp(): void
    {
        self::setUp();

        if (!class_exists(Client::class)) {
            self::markTestSkipped('The mongodb/mongodb package is required.');
        }

        $this->mongo = self::getMockBuilder(Client::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->options = [
            'id_field' => '_id',
            'data_field' => 'data',
            'time_field' => 'time',
            'expiry_field' => 'expires_at',
            'database' => 'sf-test',
            'collection' => 'session-test',
        ];

        $this->storage = new MongoDbSessionHandler($this->mongo, $this->options);
    }

    public function testConstructorShouldThrowExceptionForMissingOptions()
    {
        self::expectException(\InvalidArgumentException::class);
        new MongoDbSessionHandler($this->mongo, []);
    }

    public function testOpenMethodAlwaysReturnTrue()
    {
        self::assertTrue($this->storage->open('test', 'test'), 'The "open" method should always return true');
    }

    public function testCloseMethodAlwaysReturnTrue()
    {
        self::assertTrue($this->storage->close(), 'The "close" method should always return true');
    }

    public function testRead()
    {
        $collection = $this->createMongoCollectionMock();

        $this->mongo->expects(self::once())
            ->method('selectCollection')
            ->with($this->options['database'], $this->options['collection'])
            ->willReturn($collection);

        // defining the timeout before the actual method call
        // allows to test for "greater than" values in the $criteria
        $testTimeout = time() + 1;

        $collection->expects(self::once())
            ->method('findOne')
            ->willReturnCallback(function ($criteria) use ($testTimeout) {
                self::assertArrayHasKey($this->options['id_field'], $criteria);
                self::assertEquals('foo', $criteria[$this->options['id_field']]);

                self::assertArrayHasKey($this->options['expiry_field'], $criteria);
                self::assertArrayHasKey('$gte', $criteria[$this->options['expiry_field']]);

                self::assertInstanceOf(\MongoDB\BSON\UTCDateTime::class, $criteria[$this->options['expiry_field']]['$gte']);
                self::assertGreaterThanOrEqual(round((string) $criteria[$this->options['expiry_field']]['$gte'] / 1000), $testTimeout);

                return [
                    $this->options['id_field'] => 'foo',
                    $this->options['expiry_field'] => new \MongoDB\BSON\UTCDateTime(),
                    $this->options['data_field'] => new \MongoDB\BSON\Binary('bar', \MongoDB\BSON\Binary::TYPE_OLD_BINARY),
                ];
            });

        self::assertEquals('bar', $this->storage->read('foo'));
    }

    public function testWrite()
    {
        $collection = $this->createMongoCollectionMock();

        $this->mongo->expects(self::once())
            ->method('selectCollection')
            ->with($this->options['database'], $this->options['collection'])
            ->willReturn($collection);

        $collection->expects(self::once())
            ->method('updateOne')
            ->willReturnCallback(function ($criteria, $updateData, $options) {
                self::assertEquals([$this->options['id_field'] => 'foo'], $criteria);
                self::assertEquals(['upsert' => true], $options);

                $data = $updateData['$set'];
                $expectedExpiry = time() + (int) \ini_get('session.gc_maxlifetime');
                self::assertInstanceOf(\MongoDB\BSON\Binary::class, $data[$this->options['data_field']]);
                self::assertEquals('bar', $data[$this->options['data_field']]->getData());
                self::assertInstanceOf(\MongoDB\BSON\UTCDateTime::class, $data[$this->options['time_field']]);
                self::assertInstanceOf(\MongoDB\BSON\UTCDateTime::class, $data[$this->options['expiry_field']]);
                self::assertGreaterThanOrEqual($expectedExpiry, round((string) $data[$this->options['expiry_field']] / 1000));
            });

        self::assertTrue($this->storage->write('foo', 'bar'));
    }

    public function testReplaceSessionData()
    {
        $collection = $this->createMongoCollectionMock();

        $this->mongo->expects(self::once())
            ->method('selectCollection')
            ->with($this->options['database'], $this->options['collection'])
            ->willReturn($collection);

        $data = [];

        $collection->expects(self::exactly(2))
            ->method('updateOne')
            ->willReturnCallback(function ($criteria, $updateData, $options) use (&$data) {
                $data = $updateData;
            });

        $this->storage->write('foo', 'bar');
        $this->storage->write('foo', 'foobar');

        self::assertEquals('foobar', $data['$set'][$this->options['data_field']]->getData());
    }

    public function testDestroy()
    {
        $collection = $this->createMongoCollectionMock();

        $this->mongo->expects(self::once())
            ->method('selectCollection')
            ->with($this->options['database'], $this->options['collection'])
            ->willReturn($collection);

        $collection->expects(self::once())
            ->method('deleteOne')
            ->with([$this->options['id_field'] => 'foo']);

        self::assertTrue($this->storage->destroy('foo'));
    }

    public function testGc()
    {
        $collection = $this->createMongoCollectionMock();

        $this->mongo->expects(self::once())
            ->method('selectCollection')
            ->with($this->options['database'], $this->options['collection'])
            ->willReturn($collection);

        $collection->expects(self::once())
            ->method('deleteMany')
            ->willReturnCallback(function ($criteria) {
                self::assertInstanceOf(\MongoDB\BSON\UTCDateTime::class, $criteria[$this->options['expiry_field']]['$lt']);
                self::assertGreaterThanOrEqual(time() - 1, round((string) $criteria[$this->options['expiry_field']]['$lt'] / 1000));

                $result = self::createMock(\MongoDB\DeleteResult::class);
                $result->method('getDeletedCount')->willReturn(42);

                return $result;
            });

        self::assertSame(42, $this->storage->gc(1));
    }

    public function testGetConnection()
    {
        $method = new \ReflectionMethod($this->storage, 'getMongo');
        $method->setAccessible(true);

        self::assertInstanceOf(Client::class, $method->invoke($this->storage));
    }

    private function createMongoCollectionMock(): \MongoDB\Collection
    {
        $collection = self::getMockBuilder(\MongoDB\Collection::class)
            ->disableOriginalConstructor()
            ->getMock();

        return $collection;
    }
}
