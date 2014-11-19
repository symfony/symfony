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

use Symfony\Component\HttpFoundation\Session\Storage\Handler\MongoDbSessionHandler;

/**
 * @author Markus Bachmann <markus.bachmann@bachi.biz>
 */
class MongoDbSessionHandlerTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var \PHPUnit_Framework_MockObject_MockObject
     */
    private $mongo;
    private $storage;
    public $options;

    protected function setUp()
    {
        if (!extension_loaded('mongo')) {
            $this->markTestSkipped('MongoDbSessionHandler requires the PHP "mongo" extension.');
        }

        $mongoClass = (version_compare(phpversion('mongo'), '1.3.0', '<')) ? 'Mongo' : 'MongoClient';

        $this->mongo = $this->getMockBuilder($mongoClass)
            ->getMock();

        $this->options = array(
            'id_field' => '_id',
            'data_field' => 'data',
            'time_field' => 'time',
            'expiry_field' => 'expires_at',
            'database' => 'sf2-test',
            'collection' => 'session-test',
        );

        $this->storage = new MongoDbSessionHandler($this->mongo, $this->options);
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testConstructorShouldThrowExceptionForInvalidMongo()
    {
        new MongoDbSessionHandler(new \stdClass(), $this->options);
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testConstructorShouldThrowExceptionForMissingOptions()
    {
        new MongoDbSessionHandler($this->mongo, array());
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
            ->will($this->returnValue($collection));

        $that = $this;

        // defining the timeout before the actual method call
        // allows to test for "greater than" values in the $criteria
        $testTimeout = time();

        $collection->expects($this->once())
            ->method('findOne')
            ->will($this->returnCallback(function ($criteria) use ($that, $testTimeout) {
                $that->assertArrayHasKey($that->options['id_field'], $criteria);
                $that->assertEquals($criteria[$that->options['id_field']], 'foo');

                $that->assertArrayHasKey($that->options['expiry_field'], $criteria);
                $that->assertArrayHasKey('$gte', $criteria[$that->options['expiry_field']]);
                $that->assertInstanceOf('MongoDate', $criteria[$that->options['expiry_field']]['$gte']);
                $that->assertGreaterThanOrEqual($criteria[$that->options['expiry_field']]['$gte']->sec, $testTimeout);

                return array(
                    $that->options['id_field'] => 'foo',
                    $that->options['data_field'] => new \MongoBinData('bar', \MongoBinData::BYTE_ARRAY),
                    $that->options['id_field'] => new \MongoDate(),
                );
            }));

        $this->assertEquals('bar', $this->storage->read('foo'));
    }

    public function testWrite()
    {
        $collection = $this->createMongoCollectionMock();

        $this->mongo->expects($this->once())
            ->method('selectCollection')
            ->with($this->options['database'], $this->options['collection'])
            ->will($this->returnValue($collection));

        $that = $this;
        $data = array();

        $collection->expects($this->once())
            ->method('update')
            ->will($this->returnCallback(function ($criteria, $updateData, $options) use ($that, &$data) {
                $that->assertEquals(array($that->options['id_field'] => 'foo'), $criteria);
                $that->assertEquals(array('upsert' => true, 'multiple' => false), $options);

                $data = $updateData['$set'];
            }));

        $expectedExpiry = time() + (int) ini_get('session.gc_maxlifetime');
        $this->assertTrue($this->storage->write('foo', 'bar'));

        $this->assertEquals('bar', $data[$this->options['data_field']]->bin);
        $that->assertInstanceOf('MongoDate', $data[$this->options['time_field']]);
        $this->assertInstanceOf('MongoDate', $data[$this->options['expiry_field']]);
        $this->assertGreaterThanOrEqual($expectedExpiry, $data[$this->options['expiry_field']]->sec);
    }

    public function testReplaceSessionData()
    {
        $collection = $this->createMongoCollectionMock();

        $this->mongo->expects($this->once())
            ->method('selectCollection')
            ->with($this->options['database'], $this->options['collection'])
            ->will($this->returnValue($collection));

        $data = array();

        $collection->expects($this->exactly(2))
            ->method('update')
            ->will($this->returnCallback(function ($criteria, $updateData, $options) use (&$data) {
                $data = $updateData;
            }));

        $this->storage->write('foo', 'bar');
        $this->storage->write('foo', 'foobar');

        $this->assertEquals('foobar', $data['$set'][$this->options['data_field']]->bin);
    }

    public function testDestroy()
    {
        $collection = $this->createMongoCollectionMock();

        $this->mongo->expects($this->once())
            ->method('selectCollection')
            ->with($this->options['database'], $this->options['collection'])
            ->will($this->returnValue($collection));

        $collection->expects($this->once())
            ->method('remove')
            ->with(array($this->options['id_field'] => 'foo'));

        $this->assertTrue($this->storage->destroy('foo'));
    }

    public function testGc()
    {
        $collection = $this->createMongoCollectionMock();

        $this->mongo->expects($this->once())
            ->method('selectCollection')
            ->with($this->options['database'], $this->options['collection'])
            ->will($this->returnValue($collection));

        $that = $this;

        $collection->expects($this->once())
            ->method('remove')
            ->will($this->returnCallback(function ($criteria) use ($that) {
                $that->assertInstanceOf('MongoDate', $criteria[$that->options['expiry_field']]['$lt']);
                $that->assertGreaterThanOrEqual(time() - 1, $criteria[$that->options['expiry_field']]['$lt']->sec);
            }));

        $this->assertTrue($this->storage->gc(1));
    }

    private function createMongoCollectionMock()
    {
        $mongoClient = $this->getMockBuilder('MongoClient')
            ->getMock();
        $mongoDb = $this->getMockBuilder('MongoDB')
            ->setConstructorArgs(array($mongoClient, 'database-name'))
            ->getMock();
        $collection = $this->getMockBuilder('MongoCollection')
            ->setConstructorArgs(array($mongoDb, 'collection-name'))
            ->getMock();

        return $collection;
    }
}
