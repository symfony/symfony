<?php

namespace Symfony\Tests\Component\HttpFoundation\Session\Storage;

use Symfony\Component\HttpFoundation\Session\Storage\MemcacheSessionStorage;

class MemcacheSessionStorageTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var MemcacheSessionStorage
     */
    protected $storage;

    protected $memcache;

    protected function setUp()
    {
        if (!class_exists('Memcache')) {
            $this->markTestSkipped('Skipped tests Memcache class is not present');
        }

        $this->memcache = $this->getMock('Memcache');
        $this->storage = new MemcacheSessionStorage($this->memcache);
    }

    protected function tearDown()
    {
        $this->memcache = null;
        $this->storage = null;
    }

    public function testOpenSession()
    {
        $this->memcache->expects($this->atLeastOnce())
            ->method('addServer');

        $this->assertTrue($this->storage->open('', ''));
    }

    public function testCloseSession()
    {
        $this->memcache->expects($this->once())
            ->method('close')
            ->will($this->returnValue(true));

        $this->assertTrue($this->storage->close());
    }

    public function testReadSession()
    {
        $this->memcache->expects($this->once())
            ->method('get');

        $this->assertEquals('', $this->storage->read(''));
    }

    public function testWriteSession()
    {
        $this->memcache->expects($this->once())
            ->method('set')
            ->will($this->returnValue(true));

        $this->assertTrue($this->storage->write('', ''));
    }

    public function testDestroySession()
    {
        $this->memcache->expects($this->once())
            ->method('delete')
            ->will($this->returnValue(true));

        $this->assertTrue($this->storage->destroy(''));
    }

    public function testGcSession()
    {
        $this->assertTrue($this->storage->gc(123));
    }

}
