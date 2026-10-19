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

use PHPUnit\Framework\Attributes\Group;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\Cache\Adapter\Psr16Adapter;
use Symfony\Component\Cache\Psr16Cache;
use Symfony\Component\Cache\Tests\Fixtures\ResettableAdapter;
use Symfony\Contracts\Service\ShutdownInterface;

#[Group('time-sensitive')]
class Psr16AdapterTest extends AdapterTestCase
{
    protected array $skippedTests = [
        'testPrune' => 'Psr16adapter just proxies',
        'testClearPrefix' => 'SimpleCache cannot clear by prefix',
        'testClearPrefixWithUnderscore' => 'SimpleCache cannot clear by prefix',
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

    public function testResetForwardsTheShutdownFlagToShutdownAwarePoolsOnly()
    {
        $inner = new class(new ArrayAdapter()) extends Psr16Cache implements ShutdownInterface {
            public array $resets = [];

            public function reset(bool $shutdown = false): void
            {
                $this->resets[] = $shutdown;
            }
        };
        $pool = new Psr16Adapter($inner);
        $pool->reset();
        $pool->reset(true);

        $this->assertSame([false, true], $inner->resets);

        $pool = new Psr16Adapter(new Psr16Cache($inner = new ResettableAdapter()));
        $pool->reset(true);

        $this->assertSame([[]], $inner->resets);
    }
}
