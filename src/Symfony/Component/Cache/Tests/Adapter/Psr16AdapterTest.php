<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Cache\Tests\Adapter;

use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\Cache\Adapter\Psr16Adapter;
use Symfony\Component\Cache\Psr16Cache;

/**
 * @group time-sensitive
 */
class Psr16AdapterTest extends AdapterTestCase
{
    protected $skippedTests = [
        'testPrune' => 'Psr16adapter just proxies',
        'testClearPrefix' => 'SimpleCache cannot clear by prefix',
    ];

    public function createCachePool(int $defaultLifetime = 0): CacheItemPoolInterface
    {
        return new Psr16Adapter(new Psr16Cache(new FilesystemAdapter()), '', $defaultLifetime);
    }

    public function testValidCacheKeyWithNamespace()
    {
        $cache = new Psr16Adapter(new Psr16Cache(new ArrayAdapter()), 'some_namespace', 0);
        $item = $cache->getItem('my_key');
        $item->set('someValue');
        $cache->save($item);

        $this->assertTrue($cache->getItem('my_key')->isHit(), 'Stored item is successfully retrieved.');
    }
}
