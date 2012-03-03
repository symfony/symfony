<?php

namespace Symfony\Tests\Component\HttpFoundation\Session\Storage\Handler;

use Symfony\Component\HttpFoundation\Session\Storage\Handler\MemcachedSessionHandler;

class MemcacheddSessionHandlerTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var MemcachedSessionHandler
     */
    protected $storage;

    protected $memcached;

    protected function setUp()
    {
        if (!class_exists('Memcached')) {
            $this->markTestSkipped('Skipped tests Memcache class is not present');
        }

        $this->memcached = $this->getMock('Memcached');
        $this->storage = new MemcachedSessionHandler($this->memcached);
    }

    protected function tearDown()
    {
        $this->memcached = null;
        $this->storage = null;
    }

    public function testOpenSession()
    {
        $this->memcached->expects($this->atLeastOnce())
            ->method('addServers')
            ->will($this->returnValue(true));

        $this->assertTrue($this->storage->open('', ''));
    }

    public function testCloseSession()
    {
        $this->assertTrue($this->storage->close());
    }

    public function testReadSession()
    {
        $this->memcached->expects($this->once())
            ->method('get');

        $this->assertEquals('', $this->storage->read(''));
    }

    public function testWriteSession()
    {
        $this->memcached->expects($this->once())
            ->method('set')
            ->will($this->returnValue(true));

        $this->assertTrue($this->storage->write('', ''));
    }

    public function testDestroySession()
    {
        $this->memcached->expects($this->once())
            ->method('delete')
            ->will($this->returnValue(true));

        $this->assertTrue($this->storage->destroy(''));
    }

    public function testGcSession()
    {
        $this->assertTrue($this->storage->gc(123));
    }


}
