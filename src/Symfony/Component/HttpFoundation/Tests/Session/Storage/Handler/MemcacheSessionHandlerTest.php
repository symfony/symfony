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

use Symfony\Component\HttpFoundation\Session\Storage\Handler\MemcacheSessionHandler;

class MemcacheSessionHandlerTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var MemcacheSessionHandler
     */
    protected $storage;

    protected $memcache;

    protected function setUp()
    {
        if (!class_exists('Memcache')) {
            $this->markTestSkipped('Skipped tests Memcache class is not present');
        }

        $this->memcache = $this->getMock('Memcache');
        $this->storage = new MemcacheSessionHandler($this->memcache);
    }

    protected function tearDown()
    {
        $this->memcache = null;
        $this->storage = null;
    }

    public function testOpenSession()
    {
        $this->memcache->expects($this->atLeastOnce())
            ->method('addServer')
            ->with('127.0.0.1', 11211, false, 1, 1, 15);

        $this->assertTrue($this->storage->open('', ''));
    }

    public function testConstructingWithServerPool()
    {
        $mock    = $this->getMock('Memcache');

        $storage = new MemcacheSessionHandler($mock, array(
            'serverpool' => array(
                array('host' => '127.0.0.2'),
                array('host'           => '127.0.0.3',
                      'port'           => 11212,
                      'timeout'        => 10,
                      'persistent'     => true,
                      'weight'         => 5,
                      'retry_interval' => 39,
                ),
                array('host'   => '127.0.0.4',
                      'port'   => 11211,
                      'weight' => 2
                ),
            ),
        ));

        $matcher = $mock
            ->expects($this->at(0))
            ->method('addServer')
            ->with('127.0.0.2', 11211, false, 1, 1, 15);
        $matcher = $mock
            ->expects($this->at(1))
            ->method('addServer')
            ->with('127.0.0.3', 11212, true, 5, 10, 39);
        $matcher = $mock
            ->expects($this->at(2))
            ->method('addServer')
            ->with('127.0.0.4', 11211, false, 2, 1, 15);
        $this->assertTrue($storage->open('', ''));
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
