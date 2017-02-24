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

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Session\Storage\Handler\MongoDbSessionHandler;

/**
 * @author Markus Bachmann <markus.bachmann@bachi.biz>
 * @group time-sensitive
 */
class MongoDbSessionHandlerTest extends TestCase
{
    /**
     * @var \PHPUnit_Framework_MockObject_MockObject
     */
    private $mongo;
    private $storage;
    public $options;

    protected function setUp()
    {
        parent::setUp();

        if (!extension_loaded('mongo') && !extension_loaded('mongodb')) {
            $this->markTestSkipped('The Mongo or MongoDB extension is required.');
        }

        if (phpversion('mongodb')) {
            $mongoClass = 'MongoDB\Client';
        } else {
            $mongoClass = version_compare(phpversion('mongo'), '1.3.0', '<') ? 'Mongo' : 'MongoClient';
        }

        $this->mongo = $this->getMockBuilder($mongoClass)
            ->disableOriginalConstructor()
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

        // defining the timeout before the actual method call
        // allows to test for "greater than" values in the $criteria
        $testTimeout = time() + 1;

        $collection->expects($this->once())
            ->method('findOne')
            ->will($this->returnCallback(function ($criteria) use ($testTimeout) {
                $this->assertArrayHasKey($this->options['id_field'], $criteria);
                $this->assertEquals($criteria[$this->options['id_field']], 'foo');

                $this->assertArrayHasKey($this->options['expiry_field'], $criteria);
                $this->assertArrayHasKey('$gte', $criteria[$this->options['expiry_field']]);

                if (phpversion('mongodb')) {
                    $this->assertInstanceOf('MongoDB\BSON\UTCDateTime', $criteria[$this->options['expiry_field']]['$gte']);
                    $this->assertGreaterThanOrEqual(round(((int) $criteria[$this->options['expiry_field']]['$gte']) / 1000), $testTimeout);
                } else {
                    $this->assertInstanceOf('MongoDate', $criteria[$this->options['expiry_field']]['$gte']);
                    $this->assertGreaterThanOrEqual($criteria[$this->options['expiry_field']]['$gte']->sec, $testTimeout);
                }

                $fields = array(
                    $this->options['id_field'] => 'foo',
                );

                if (phpversion('mongodb')) {
                    $fields[$this->options['data_field']] = new \MongoDB\BSON\Binary('bar', \MongoDB\BSON\Binary::TYPE_OLD_BINARY);
                    $fields[$this->options['id_field']] = new \MongoDB\BSON\UTCDateTime(time() * 1000);
                } else {
                    $fields[$this->options['data_field']] = new \MongoBinData('bar', \MongoBinData::BYTE_ARRAY);
                    $fields[$this->options['id_field']] = new \MongoDate();
                }

                return $fields;
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

        $data = array();

        $methodName = phpversion('mongodb') ? 'updateOne' : 'update';

        $collection->expects($this->once())
            ->method($methodName)
            ->will($this->returnCallback(function ($criteria, $updateData, $options) use (&$data) {
                $this->assertEquals(array($this->options['id_field'] => 'foo'), $criteria);

                if (phpversion('mongodb')) {
                    $this->assertEquals(array('upsert' => true), $options);
                } else {
                    $this->assertEquals(array('upsert' => true, 'multiple' => false), $options);
                }

                $data = $updateData['$set'];
            }));

        $expectedExpiry = time() + (int) ini_get('session.gc_maxlifetime');
        $this->assertTrue($this->storage->write('foo', 'bar'));

        if (phpversion('mongodb')) {
            $this->assertEquals('bar', $data[$this->options['data_field']]->getData());
            $this->assertInstanceOf('MongoDB\BSON\UTCDateTime', $data[$this->options['time_field']]);
            $this->assertInstanceOf('MongoDB\BSON\UTCDateTime', $data[$this->options['expiry_field']]);
            $this->assertGreaterThanOrEqual($expectedExpiry, round(((int) $data[$this->options['expiry_field']]) / 1000));
        } else {
            $this->assertEquals('bar', $data[$this->options['data_field']]->bin);
            $this->assertInstanceOf('MongoDate', $data[$this->options['time_field']]);
            $this->assertInstanceOf('MongoDate', $data[$this->options['expiry_field']]);
            $this->assertGreaterThanOrEqual($expectedExpiry, $data[$this->options['expiry_field']]->sec);
        }
    }

    public function testWriteWhenUsingExpiresField()
    {
        $this->options = array(
            'id_field' => '_id',
            'data_field' => 'data',
            'time_field' => 'time',
            'database' => 'sf2-test',
            'collection' => 'session-test',
            'expiry_field' => 'expiresAt',
        );

        $this->storage = new MongoDbSessionHandler($this->mongo, $this->options);

        $collection = $this->createMongoCollectionMock();

        $this->mongo->expects($this->once())
            ->method('selectCollection')
            ->with($this->options['database'], $this->options['collection'])
            ->will($this->returnValue($collection));

        $data = array();

        $methodName = phpversion('mongodb') ? 'updateOne' : 'update';

        $collection->expects($this->once())
            ->method($methodName)
            ->will($this->returnCallback(function ($criteria, $updateData, $options) use (&$data) {
                $this->assertEquals(array($this->options['id_field'] => 'foo'), $criteria);

                if (phpversion('mongodb')) {
                    $this->assertEquals(array('upsert' => true), $options);
                } else {
                    $this->assertEquals(array('upsert' => true, 'multiple' => false), $options);
                }

                $data = $updateData['$set'];
            }));

        $this->assertTrue($this->storage->write('foo', 'bar'));

        if (phpversion('mongodb')) {
            $this->assertEquals('bar', $data[$this->options['data_field']]->getData());
            $this->assertInstanceOf('MongoDB\BSON\UTCDateTime', $data[$this->options['time_field']]);
            $this->assertInstanceOf('MongoDB\BSON\UTCDateTime', $data[$this->options['expiry_field']]);
        } else {
            $this->assertEquals('bar', $data[$this->options['data_field']]->bin);
            $this->assertInstanceOf('MongoDate', $data[$this->options['time_field']]);
            $this->assertInstanceOf('MongoDate', $data[$this->options['expiry_field']]);
        }
    }

    public function testReplaceSessionData()
    {
        $collection = $this->createMongoCollectionMock();

        $this->mongo->expects($this->once())
            ->method('selectCollection')
            ->with($this->options['database'], $this->options['collection'])
            ->will($this->returnValue($collection));

        $data = array();

        $methodName = phpversion('mongodb') ? 'updateOne' : 'update';

        $collection->expects($this->exactly(2))
            ->method($methodName)
            ->will($this->returnCallback(function ($criteria, $updateData, $options) use (&$data) {
                $data = $updateData;
            }));

        $this->storage->write('foo', 'bar');
        $this->storage->write('foo', 'foobar');

        if (phpversion('mongodb')) {
            $this->assertEquals('foobar', $data['$set'][$this->options['data_field']]->getData());
        } else {
            $this->assertEquals('foobar', $data['$set'][$this->options['data_field']]->bin);
        }
    }

    public function testDestroy()
    {
        $collection = $this->createMongoCollectionMock();

        $this->mongo->expects($this->once())
            ->method('selectCollection')
            ->with($this->options['database'], $this->options['collection'])
            ->will($this->returnValue($collection));

        $methodName = phpversion('mongodb') ? 'deleteOne' : 'remove';

        $collection->expects($this->once())
            ->method($methodName)
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

        $methodName = phpversion('mongodb') ? 'deleteOne' : 'remove';

        $collection->expects($this->once())
            ->method($methodName)
            ->will($this->returnCallback(function ($criteria) {
                if (phpversion('mongodb')) {
                    $this->assertInstanceOf('MongoDB\BSON\UTCDateTime', $criteria[$this->options['expiry_field']]['$lt']);
                    $this->assertGreaterThanOrEqual(time() - 1, round(((int) $criteria[$this->options['expiry_field']]['$lt']) / 1000));
                } else {
                    $this->assertInstanceOf('MongoDate', $criteria[$this->options['expiry_field']]['$lt']);
                    $this->assertGreaterThanOrEqual(time() - 1, $criteria[$this->options['expiry_field']]['$lt']->sec);
                }
            }));

        $this->assertTrue($this->storage->gc(1));
    }

    public function testGetConnection()
    {
        $method = new \ReflectionMethod($this->storage, 'getMongo');
        $method->setAccessible(true);

        if (phpversion('mongodb')) {
            $mongoClass = 'MongoDB\Client';
        } else {
            $mongoClass = version_compare(phpversion('mongo'), '1.3.0', '<') ? 'Mongo' : 'MongoClient';
        }

        $this->assertInstanceOf($mongoClass, $method->invoke($this->storage));
    }

    private function createMongoCollectionMock()
    {
        $collectionClass = 'MongoCollection';
        if (phpversion('mongodb')) {
            $collectionClass = 'MongoDB\Collection';
        }

        $collection = $this->getMockBuilder($collectionClass)
            ->disableOriginalConstructor()
            ->getMock();

        return $collection;
    }
}
