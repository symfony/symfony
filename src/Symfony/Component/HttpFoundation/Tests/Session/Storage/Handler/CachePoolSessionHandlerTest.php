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

use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Cache\InvalidArgumentException as BaseInvalidArgumentException;
use Symfony\Component\HttpFoundation\Session\Storage\Handler\CachePoolSessionHandler;

class CachePoolSessionHandlerTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var \PHPUnit_Framework_MockObject_MockObject
     */
    private $cachePool;

    /**
     * @var CachePoolSessionHandler
     */
    private $sessionHandler;

    protected function setUp()
    {
        $this->cachePool = $this->getMock(CacheItemPoolInterface::class);
        $this->sessionHandler = new CachePoolSessionHandler($this->cachePool, array());
    }

    public function testOpenSucceeds()
    {
        $this->assertTrue($this->sessionHandler->open('save-path', 'id'));
    }

    public function testOpenReturnsFalseIfTheCacheItemCannotBeRead()
    {
        $this->cachePoolThrowsException('getItem');

        $this->assertFalse($this->sessionHandler->open('save-path', 'id'));
    }

    public function testClose()
    {
        $this->assertTrue($this->sessionHandler->close());
    }

    public function testReadReturnsCachedDataOnSuccess()
    {
        $this->cachePoolReturnsItemWithHit();

        $this->assertSame('data', $this->sessionHandler->read('id'));
    }

    public function testReadReturnsEmptyStringIfNoCacheItemWasFound()
    {
        $this->cachePoolReturnsItemWithoutHit();

        $this->assertSame('', $this->sessionHandler->read('id'));
    }

    public function testReadReturnsEmptyStringIfTheCacheItemCannotBeRead()
    {
        $this->cachePoolThrowsException('getItem');

        $this->assertSame('', $this->sessionHandler->read('id'));
    }

    public function testWriteReturnsTrueWhenTheCacheItemCouldBeSaved()
    {
        $this->cachePoolReturnsItemWithHit();
        $this
            ->cachePool
            ->expects($this->once())
            ->method('save')
            ->willReturn(true)
        ;

        $this->assertTrue($this->sessionHandler->write('id', 'data'));
    }

    public function testWriteReturnsFalseWhenTheCacheItemCouldNotBeSaved()
    {
        $this->cachePoolReturnsItemWithHit();
        $this
            ->cachePool
            ->expects($this->once())
            ->method('save')
            ->willReturn(false)
        ;

        $this->assertFalse($this->sessionHandler->write('id', 'data'));
    }

    public function testWriteReturnsFalseWhenTheCachePoolThrowsAnException()
    {
        $this->cachePoolThrowsException('getItem');

        $this->assertFalse($this->sessionHandler->write('id', 'data'));
    }

    public function testWriteRespectsCustomMaxLifetime()
    {
        $this->sessionHandler = new CachePoolSessionHandler($this->cachePool, array('expires_after' => 86400));
        $item = $this->cachePoolReturnsItemWithHit();
        $item
            ->expects($this->atLeastOnce())
            ->method('expiresAfter')
            ->with(86400)
        ;
        $this
            ->cachePool
            ->expects($this->once())
            ->method('save')
            ->willReturn(true)
        ;

        $this->assertTrue($this->sessionHandler->write('id', 'data'));
    }

    public function testDestroyReturnsTrueWhenTheCacheItemWasRemoved()
    {
        $this
            ->cachePool
            ->expects($this->once())
            ->method('deleteItem')
            ->willReturn(true)
        ;

        $this->assertTrue($this->sessionHandler->destroy('id'));
    }

    public function testDestroyReturnsFalseWhenTheCacheItemWasNotRemoved()
    {
        $this
            ->cachePool
            ->expects($this->once())
            ->method('deleteItem')
            ->willReturn(false)
        ;

        $this->assertFalse($this->sessionHandler->destroy('id'));
    }

    public function testDestroyReturnsFalseWhenTheCachePoolThrowsAnException()
    {
        $this->cachePoolThrowsException('deleteItem');

        $this->assertFalse($this->sessionHandler->destroy('id'));
    }

    public function testGcSucceeds()
    {
        $this->assertTrue($this->sessionHandler->gc(1000));
    }

    /**
     * @dataProvider getOptionFixtures
     */
    public function testSupportedOptions($options, $supported)
    {
        try {
            new CachePoolSessionHandler($this->cachePool, $options);

            $this->assertTrue($supported);
        } catch (\InvalidArgumentException $e) {
            $this->assertFalse($supported);
        }
    }

    public function getOptionFixtures()
    {
        return array(
            array(array('prefix' => 'session'), false),
            array(array('expires_after' => 100), true),
            array(array('expires_after' => 100, 'foo' => 'bar'), false),
        );
    }

    private function cachePoolReturnsItemWithHit()
    {
        $item = $this->getMock(CacheItemInterface::class);
        $item
            ->expects($this->any())
            ->method('isHit')
            ->willReturn(true)
        ;
        $item
            ->expects($this->any())
            ->method('get')
            ->willReturn('data')
        ;
        $this
            ->cachePool
            ->expects($this->any())
            ->method('getItem')
            ->with('id')
            ->willReturn($item)
        ;

        return $item;
    }

    private function cachePoolReturnsItemWithoutHit()
    {
        $item = $this->getMock(CacheItemInterface::class);
        $item
            ->expects($this->any())
            ->method('isHit')
            ->willReturn(false)
        ;
        $this
            ->cachePool
            ->expects($this->any())
            ->method('getItem')
            ->with('id')
            ->willReturn($item)
        ;
    }

    private function cachePoolThrowsException($method)
    {
        $this
            ->cachePool
            ->expects($this->any())
            ->method($method)
            ->willThrowException($this->getMock(InvalidArgumentException::class))
        ;
    }
}

class InvalidArgumentException extends \Exception implements BaseInvalidArgumentException
{
}
