<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\ExpressionLanguage\Tests;

use Symfony\Component\ExpressionLanguage\ParsedExpression;
use Symfony\Component\ExpressionLanguage\ParserCache\ParserCacheAdapter;
use Symfony\Component\ExpressionLanguage\Node\Node;

/**
 * @group legacy
 */
class ParserCacheAdapterTest extends \PHPUnit_Framework_TestCase
{
    public function testGetItem()
    {
        $poolMock = $this->getMock('Symfony\Component\ExpressionLanguage\ParserCache\ParserCacheInterface');

        $key = 'key';
        $value = 'value';
        $parserCacheAdapter = new ParserCacheAdapter($poolMock);

        $poolMock
            ->expects($this->once())
            ->method('fetch')
            ->with($key)
            ->willReturn($value)
        ;

        $cacheItem = $parserCacheAdapter->getItem($key);

        $this->assertEquals($cacheItem->get(), $value);
        $this->assertEquals($cacheItem->isHit(), true);
    }

    public function testSave()
    {
        $poolMock = $this->getMock('Symfony\Component\ExpressionLanguage\ParserCache\ParserCacheInterface');
        $cacheItemMock = $this->getMock('Psr\Cache\CacheItemInterface');
        $key = 'key';
        $value = new ParsedExpression('1 + 1', new Node(array(), array()));
        $parserCacheAdapter = new ParserCacheAdapter($poolMock);

        $poolMock
            ->expects($this->once())
            ->method('save')
            ->with($key, $value)
        ;

        $cacheItemMock
            ->expects($this->once())
            ->method('getKey')
            ->willReturn($key)
        ;

        $cacheItemMock
            ->expects($this->once())
            ->method('get')
            ->willReturn($value)
        ;

        $cacheItem = $parserCacheAdapter->save($cacheItemMock);
    }

    public function testGetItems()
    {
        $poolMock = $this->getMock('Symfony\Component\ExpressionLanguage\ParserCache\ParserCacheInterface');
        $parserCacheAdapter = new ParserCacheAdapter($poolMock);
        $this->setExpectedException(\BadMethodCallException::class);

        $parserCacheAdapter->getItems();
    }

    public function testHasItem()
    {
        $poolMock = $this->getMock('Symfony\Component\ExpressionLanguage\ParserCache\ParserCacheInterface');
        $key = 'key';
        $parserCacheAdapter = new ParserCacheAdapter($poolMock);
        $this->setExpectedException(\BadMethodCallException::class);

        $parserCacheAdapter->hasItem($key);
    }

    public function testClear()
    {
        $poolMock = $this->getMock('Symfony\Component\ExpressionLanguage\ParserCache\ParserCacheInterface');
        $parserCacheAdapter = new ParserCacheAdapter($poolMock);
        $this->setExpectedException(\BadMethodCallException::class);

        $parserCacheAdapter->clear();
    }

    public function testDeleteItem()
    {
        $poolMock = $this->getMock('Symfony\Component\ExpressionLanguage\ParserCache\ParserCacheInterface');
        $key = 'key';
        $parserCacheAdapter = new ParserCacheAdapter($poolMock);
        $this->setExpectedException(\BadMethodCallException::class);

        $parserCacheAdapter->deleteItem($key);
    }

    public function testDeleteItems()
    {
        $poolMock = $this->getMock('Symfony\Component\ExpressionLanguage\ParserCache\ParserCacheInterface');
        $keys = array('key');
        $parserCacheAdapter = new ParserCacheAdapter($poolMock);
        $this->setExpectedException(\BadMethodCallException::class);

        $parserCacheAdapter->deleteItems($keys);
    }

    public function testSaveDeferred()
    {
        $poolMock = $this->getMock('Symfony\Component\ExpressionLanguage\ParserCache\ParserCacheInterface');
        $parserCacheAdapter = new ParserCacheAdapter($poolMock);
        $cacheItemMock = $this->getMock('Psr\Cache\CacheItemInterface');
        $this->setExpectedException(\BadMethodCallException::class);

        $parserCacheAdapter->saveDeferred($cacheItemMock);
    }

    public function testCommit()
    {
        $poolMock = $this->getMock('Symfony\Component\ExpressionLanguage\ParserCache\ParserCacheInterface');
        $parserCacheAdapter = new ParserCacheAdapter($poolMock);
        $this->setExpectedException(\BadMethodCallException::class);

        $parserCacheAdapter->commit();
    }
}
