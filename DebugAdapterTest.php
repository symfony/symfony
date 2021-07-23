<?php

namespace Symfony\Component\Cache\Adapter;

use PHPUnit\Framework\TestCase;
use Psr\Cache\CacheItemInterface;
use Symfony\Component\Cache\Exception\CacheException;

class DebugAdapterTest extends TestCase
{
    private $adapter;

    protected function setUp(): void
    {
        $arrayAdapter = $this->createMock(ArrayAdapter::class);
        $arrayAdapter->method('save')->willReturn(false);

        $this->adapter = new DebugAdapter($arrayAdapter);
    }

    public function testExceptionOnSave()
    {
        /** @var CacheItemInterface $cacheItem */
        $cacheItem = $this->adapter->getItem('sample-key');

        $this->expectException(CacheException::class);
        $this->adapter->save($cacheItem);
    }

    public function testExceptionOnSaveDeferred()
    {
        /** @var CacheItemInterface $cacheItem */
        $cacheItem = $this->adapter->getItem('sample-key');

        $this->expectException(CacheException::class);
        $this->adapter->saveDeferred($cacheItem);
    }
}
