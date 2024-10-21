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
use PHPUnit\Framework\SkippedTestSuiteError;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Session\Storage\Handler\MongoDbSessionHandler;

/**
 * @author Markus Bachmann <markus.bachmann@bachi.biz>
 *
 * @group time-sensitive
 *
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

    public static function setUpBeforeClass(): void
    {
        if (!class_exists(Client::class)) {
            throw new SkippedTestSuiteError('The mongodb/mongodb package is required.');
        }
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->mongo = $this->getMockBuilder(Client::class)
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
        $this->expectException(\InvalidArgumentException::class);
        new MongoDbSessionHandler($this->mongo, []);
    }

    public function testOpenMethodAlwaysReturnTrue()
    {
        $this->assertTrue($this->storage->open('test', 'test'), 'The "open" method should always return true');
    }

    public function testCloseMethodAlwaysReturnTrue()
    {
        $this->assertTrue($this->storage->close(), 'The "close" method should always return true');
    }

    public function testRead()
    {
        $collection = $this->createMongoCollectionMock();

        $this->mongo->expects($this->once())
            ->method('selectCollection')
            ->with($this->options['database'], $this->options['collection'])
            ->willReturn($collection);

        // defining the timeout before the actual method call
        // allows to test for "greater than" values in the $criteria
        $testTimeout = time() + 1;

        $collection->expects($this->once())
            ->method('findOne')
            ->willReturnCallback(function ($criteria) use ($testTimeout) {
                $this->assertArrayHasKey($this->options['id_field'], $criteria);
                $this->assertEquals('foo', $criteria[$this->options['id_field']]);

                $this->assertArrayHasKey($this->options['expiry_field'], $criteria);
                $this->assertArrayHasKey('$gte', $criteria[$this->options['expiry_field']]);

                $this->assertInstanceOf(\MongoDB\BSON\UTCDateTime::class, $criteria[$this->options['expiry_field']]['$gte']);
                $this->assertGreaterThanOrEqual(round((string) $criteria[$this->options['expiry_field']]['$gte'] / 1000), $testTimeout);

                return [
                    $this->options['id_field'] => 'foo',
                    $this->options['expiry_field'] => new \MongoDB\BSON\UTCDateTime(),
                    $this->options['data_field'] => new \MongoDB\BSON\Binary('bar', \MongoDB\BSON\Binary::TYPE_OLD_BINARY),
                ];
            });

        $this->assertEquals('bar', $this->storage->read('foo'));
    }

    public function testWrite()
    {
        $collection = $this->createMongoCollectionMock();

        $this->mongo->expects($this->once())
            ->method('selectCollection')
            ->with($this->options['database'], $this->options['collection'])
            ->willReturn($collection);

        $collection->expects($this->once())
            ->method('updateOne')
            ->willReturnCallback(function ($criteria, $updateData, $options) {
                $this->assertEquals([$this->options['id_field'] => 'foo'], $criteria);
                $this->assertEquals(['upsert' => true], $options);

                $data = $updateData['$set'];
                $expectedExpiry = time() + (int) \ini_get('session.gc_maxlifetime');
                $this->assertInstanceOf(\MongoDB\BSON\Binary::class, $data[$this->options['data_field']]);
                $this->assertEquals('bar', $data[$this->options['data_field']]->getData());
                $this->assertInstanceOf(\MongoDB\BSON\UTCDateTime::class, $data[$this->options['time_field']]);
                $this->assertInstanceOf(\MongoDB\BSON\UTCDateTime::class, $data[$this->options['expiry_field']]);
                $this->assertGreaterThanOrEqual($expectedExpiry, round((string) $data[$this->options['expiry_field']] / 1000));
            });

        $this->assertTrue($this->storage->write('foo', 'bar'));
    }

    public function testReplaceSessionData()
    {
        $collection = $this->createMongoCollectionMock();

        $this->mongo->expects($this->once())
            ->method('selectCollection')
            ->with($this->options['database'], $this->options['collection'])
            ->willReturn($collection);

        $data = [];

        $collection->expects($this->exactly(2))
            ->method('updateOne')
            ->willReturnCallback(function ($criteria, $updateData, $options) use (&$data) {
                $data = $updateData;
            });

        $this->storage->write('foo', 'bar');
        $this->storage->write('foo', 'foobar');

        $this->assertEquals('foobar', $data['$set'][$this->options['data_field']]->getData());
    }

    public function testDestroy()
    {
        $collection = $this->createMongoCollectionMock();

        $this->mongo->expects($this->once())
            ->method('selectCollection')
            ->with($this->options['database'], $this->options['collection'])
            ->willReturn($collection);

        $collection->expects($this->once())
            ->method('deleteOne')
            ->with([$this->options['id_field'] => 'foo']);

        $this->storage->open('test', 'test');

        $this->assertTrue($this->storage->destroy('foo'));
    }

    public function testGc()
    {
        $collection = $this->createMongoCollectionMock();

        $this->mongo->expects($this->once())
            ->method('selectCollection')
            ->with($this->options['database'], $this->options['collection'])
            ->willReturn($collection);

        $collection->expects($this->once())
            ->method('deleteMany')
            ->willReturnCallback(function ($criteria) {
                $this->assertInstanceOf(\MongoDB\BSON\UTCDateTime::class, $criteria[$this->options['expiry_field']]['$lt']);
                $this->assertGreaterThanOrEqual(time() - 1, round((string) $criteria[$this->options['expiry_field']]['$lt'] / 1000));

                $result = $this->createMock(\MongoDB\DeleteResult::class);
                $result->method('getDeletedCount')->willReturn(42);

                return $result;
            });

        $this->assertSame(42, $this->storage->gc(1));
    }

    public function testGetConnection()
    {
        $method = new \ReflectionMethod($this->storage, 'getMongo');
        $method->setAccessible(true);

        $this->assertInstanceOf(Client::class, $method->invoke($this->storage));
    }

    private function createMongoCollectionMock(): \MongoDB\Collection
    {
        $collection = $this->getMockBuilder(\MongoDB\Collection::class)
            ->disableOriginalConstructor()
            ->getMock();

        return $collection;
    }
}
