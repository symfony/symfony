<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

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
