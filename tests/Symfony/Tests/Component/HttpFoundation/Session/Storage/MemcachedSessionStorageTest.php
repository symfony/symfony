<?php

namespace Symfony\Tests\Component\HttpFoundation\Session\Storage;

use Symfony\Component\HttpFoundation\Session\Storage\MemcachedSessionStorage;

class MemcacheddSessionStorageTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var MemcachedSessionStorage
     */
    protected $storage;

    protected $memcached;

    protected function setUp()
    {
        if (!class_exists('Memcached')) {
            $this->markTestSkipped('Skipped tests Memcache class is not present');
        }

        $this->memcached = $this->getMock('Memcached');
        $this->storage = new MemcachedSessionStorage($this->memcached);
    }

    protected function tearDown()
    {
        $this->memcached = null;
        $this->storage = null;
    }

    public function testConstructor()
    {
        // Remove the following lines when you implement this test.
        $this->markTestIncomplete(
            'This test has not been implemented yet.'
        );
    }

    public function testOpenSession()
    {
        $this->memcached->expects($this->atLeastOnce())
            ->method('addServer');

        $this->assertTrue($this->storage->openSession('', ''));
    }

    public function testCloseSession()
    {
        $this->assertTrue($this->storage->closeSession());
    }

    public function testReadSession()
    {
        $this->memcached->expects($this->once())
            ->method('get');

        $this->assertEquals('', $this->storage->readSession(''));
    }

    public function testWriteSession()
    {
        $this->memcached->expects($this->once())
            ->method('set')
            ->will($this->returnValue(true));

        $this->assertTrue($this->storage->writeSession('', ''));
    }

    public function testDestroySession()
    {
        $this->memcached->expects($this->once())
            ->method('delete')
            ->will($this->returnValue(true));

        $this->assertTrue($this->storage->destroySession(''));
    }

    public function testGcSession()
    {
        $this->assertTrue($this->storage->gcSession(123));
    }


}
