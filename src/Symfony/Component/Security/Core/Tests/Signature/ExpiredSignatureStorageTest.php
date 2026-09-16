<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Core\Tests\Signature;

use PHPUnit\Framework\TestCase;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Security\Core\Signature\ExpiredSignatureStorage;

class ExpiredSignatureStorageTest extends TestCase
{
    public function testUsage()
    {
        $cache = new ArrayAdapter();
        $storage = new ExpiredSignatureStorage($cache, 600);

        $this->assertSame(0, $storage->countUsages('hash+more'));
        $storage->incrementUsages('hash+more');
        $this->assertSame(1, $storage->countUsages('hash+more'));
    }

    public function testIncrementUsagesReturnsTheResultingCount()
    {
        $storage = new ExpiredSignatureStorage(new ArrayAdapter(), 600);

        $this->assertSame(1, $storage->incrementUsages('hash+more'));
        $this->assertSame(2, $storage->incrementUsages('hash+more'));
        $this->assertSame(1, $storage->incrementUsages('another+hash'));
    }

    public function testIncrementUsagesAlwaysSetsAnExpiry()
    {
        // the counter already exists: an item fetched from the pool does not carry its expiration
        $item = $this->createMock(CacheItemInterface::class);
        $item->method('isHit')->willReturn(true);
        $item->method('get')->willReturn(1);
        $item->expects($this->once())->method('expiresAfter')->with(600);

        $cache = $this->createMock(CacheItemPoolInterface::class);
        $cache->expects($this->once())->method('getItem')->with(rawurlencode('hash+more'))->willReturn($item);
        $cache->expects($this->once())->method('save')->with($item);

        $storage = new ExpiredSignatureStorage($cache, 600);

        $this->assertSame(2, $storage->incrementUsages('hash+more'));
    }
}
