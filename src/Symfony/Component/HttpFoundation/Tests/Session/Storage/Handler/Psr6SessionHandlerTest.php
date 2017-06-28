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
use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\HttpFoundation\Session\Storage\Handler\Psr6SessionHandler;

/**
 * @author Aaron Scherer <aequasi@gmail.com>
 */
class Psr6SessionHandlerTest extends TestCase
{
    const TTL = 100;
    const PREFIX = 'pre';

    /**
     * @var Psr6SessionHandler
     */
    private $handler;

    /**
     * @var \PHPUnit_Framework_MockObject_MockObject|CacheItemPoolInterface
     */
    private $psr6;

    protected function setUp()
    {
        parent::setUp();

        $this->psr6 = $this->getMockBuilder(CacheItemPoolInterface::class)
            ->setMethods(['getItem', 'deleteItem'])
            ->getMock();
        $this->handler = new Psr6SessionHandler($this->psr6, ['prefix' => self::PREFIX, 'ttl' => self::TTL]);
    }

    public function testOpen()
    {
        $this->assertTrue($this->handler->open('foo', 'bar'));
    }

    public function testClose()
    {
        $this->assertTrue($this->handler->close());
    }

    public function testGc()
    {
        $this->assertTrue($this->handler->gc(4711));
    }

    public function testReadMiss()
    {
        $item = $this->getItemMock();
        $item->expects($this->once())
            ->method('isHit')
            ->willReturn(false);
        $this->psr6->expects($this->once())
            ->method('getItem')
            ->willReturn($item);

        $this->assertEquals('', $this->handler->read('foo'));
    }

    public function testReadHit()
    {
        $item = $this->getItemMock();
        $item->expects($this->once())
            ->method('isHit')
            ->willReturn(true);
        $item->expects($this->once())
            ->method('get')
            ->willReturn('bar');
        $this->psr6->expects($this->once())
            ->method('getItem')
            ->willReturn($item);

        $this->assertEquals('bar', $this->handler->read('foo'));
    }

    public function testWrite()
    {
        $item = $this->getItemMock();

        $item->expects($this->once())
            ->method('set')
            ->with('session value')
            ->willReturnSelf();
        $item->expects($this->once())
            ->method('expiresAfter')
            ->with(self::TTL)
            ->willReturnSelf();

        $this->psr6->expects($this->once())
            ->method('getItem')
            ->with(self::PREFIX.'foo')
            ->willReturn($item);

        $this->psr6->expects($this->once())
            ->method('save')
            ->with($item)
            ->willReturn(true);

        $this->assertTrue($this->handler->write('foo', 'session value'));
    }

    public function testDestroy()
    {
        $this->psr6->expects($this->once())
            ->method('deleteItem')
            ->with(self::PREFIX.'foo')
            ->willReturn(true);

        $this->assertTrue($this->handler->destroy('foo'));
    }

    /**
     * @return \PHPUnit_Framework_MockObject_MockObject
     */
    private function getItemMock(): \PHPUnit_Framework_MockObject_MockObject
    {
        return $this->getMockBuilder(CacheItemInterface::class)
            ->setMethods(['isHit', 'getKey', 'get', 'set', 'expiresAt', 'expiresAfter'])
            ->getMock();
    }
}
