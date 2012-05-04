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
