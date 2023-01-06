<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Cache\Tests;

use Cache\IntegrationTests\SimpleCacheTest;
use Psr\SimpleCache\CacheInterface;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\Adapter\ProxyAdapter;
use Symfony\Component\Cache\Psr16Cache;

class Psr16CacheProxyTest extends SimpleCacheTest
{
    protected function setUp(): void
    {
        parent::setUp();

        try {
            \assert(false === true, new \Exception());
            $this->skippedTests['testGetInvalidKeys'] =
            $this->skippedTests['testGetMultipleInvalidKeys'] =
            $this->skippedTests['testGetMultipleNoIterable'] =
            $this->skippedTests['testSetInvalidKeys'] =
            $this->skippedTests['testSetMultipleInvalidKeys'] =
            $this->skippedTests['testSetMultipleNoIterable'] =
            $this->skippedTests['testHasInvalidKeys'] =
            $this->skippedTests['testDeleteInvalidKeys'] =
            $this->skippedTests['testDeleteMultipleInvalidKeys'] =
            $this->skippedTests['testDeleteMultipleNoIterable'] = 'Keys are checked only when assert() is enabled.';
        } catch (\Exception $e) {
        }
    }

    public function createSimpleCache(int $defaultLifetime = 0): CacheInterface
    {
        return new Psr16Cache(new ProxyAdapter(new ArrayAdapter($defaultLifetime), 'my-namespace.'));
    }

    public function testProxy()
    {
        $pool = new ArrayAdapter();
        $cache = new Psr16Cache(new ProxyAdapter($pool, 'my-namespace.'));

        $this->assertNull($cache->get('some-key'));
        $this->assertTrue($cache->set('some-other-key', 'value'));

        $item = $pool->getItem('my-namespace.some-other-key', 'value');
        $this->assertTrue($item->isHit());
        $this->assertSame('value', $item->get());
    }
}
