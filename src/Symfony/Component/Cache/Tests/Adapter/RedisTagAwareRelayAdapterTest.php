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
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\Cache\Adapter\RedisTagAwareAdapter;
use Symfony\Component\Cache\Traits\RelayProxy;

#[RequiresPhpExtension('relay')]
#[Group('integration')]
class RedisTagAwareRelayAdapterTest extends RelayAdapterTest
{
    use RedisTagAwarePruneTestTrait;
    use TagAwareTestTrait;

    protected function setUp(): void
    {
        parent::setUp();
        $this->skippedTests['testTagItemExpiry'] = 'Testing expiration slows down the test suite';
        $this->skippedTests['testPrune'] = 'Redis expires items by itself, prune() only garbage-collects tag Sets';
    }

    public function createCachePool(int $defaultLifetime = 0, ?string $testMethod = null): CacheItemPoolInterface
    {
        $this->assertInstanceOf(RelayProxy::class, self::$redis);

        return new RedisTagAwareAdapter(self::$redis, str_replace('\\', '.', __CLASS__), $defaultLifetime);
    }

    public function testGetItemMissesRightAfterInvalidatingItsTag()
    {
        $pool = $this->createCachePool();

        // Reading a stale copy from Relay's memory depends on timing, hence the loop
        for ($i = 0; $i < 100; ++$i) {
            $pool->save($pool->getItem('foo')->set('bar')->tag('baz'));
            $this->assertTrue($pool->getItem('foo')->isHit());

            $pool->invalidateTags(['baz']);
            $this->assertFalse($pool->getItem('foo')->isHit());
        }
    }
}
