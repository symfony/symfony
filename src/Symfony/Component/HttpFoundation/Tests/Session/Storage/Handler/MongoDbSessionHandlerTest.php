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
    private static $mongo;

    public static function setUpBeforeClass()
    {
        if (class_exists('\Mongo')) {
            try {
                self::$mongo = new \Mongo();
            } catch (\Exception $e) {
            }
        }
    }

    protected function setUp()
    {
        if (null === self::$mongo) {
            $this->markTestSkipped('MongoDbSessionHandler requires the php "mongo" extension and a mongodb server on localhost');
        }

        $this->options = array('database' => 'sf2-test', 'collection' => 'session-test');
        $this->options = array('database' => 'sf2-test', 'collection' => 'session-test');

        $this->storage = new MongoDbSessionHandler(self::$mongo, $this->options);
    }

    protected function tearDown()
    {
        if (null !== self::$mongo) {
            self::$mongo->dropDB($this->options['database']);
        }
    }

    public function testOpenMethodAlwaysReturnTrue()
    {
        $this->assertTrue($this->storage->open('test', 'test'), 'The "open" method should always return true');
    }

    public function testCloseMethodAlwaysReturnTrue()
    {
        $this->assertTrue($this->storage->close(), 'The "close" method should always return true');
    }

    public function testWrite()
    {
        $this->assertTrue($this->storage->write('foo', 'bar'));
        $this->assertEquals('bar', $this->storage->read('foo'));
    }

    public function testReplaceSessionData()
    {
        $this->storage->write('foo', 'bar');
        $this->storage->write('foo', 'foobar');

        $coll = self::$mongo->selectDB($this->options['database'])->selectCollection($this->options['collection']);

        $this->assertEquals('foobar', $this->storage->read('foo'));
        $this->assertEquals(1, $coll->find(array('sess_id' => 'foo'))->count());
    }

    public function testDestroy()
    {
        $this->storage->write('foo', 'bar');
        $this->storage->destroy('foo');

        $this->assertEquals('', $this->storage->read('foo'));
    }

    public function testGc()
    {
        $this->storage->write('foo', 'bar');
        $this->storage->write('bar', 'foo');

        $coll = self::$mongo->selectDB($this->options['database'])->selectCollection($this->options['collection']);

        $this->assertEquals(2, $coll->count());
        $this->storage->gc(-1);
        $this->assertEquals(0, $coll->count());

    }
}
