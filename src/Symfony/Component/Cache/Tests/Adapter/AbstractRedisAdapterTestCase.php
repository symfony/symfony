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

use PHPUnit\Framework\SkippedTestSuiteError;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\Cache\Adapter\RedisAdapter;

abstract class AbstractRedisAdapterTestCase extends AdapterTestCase
{
    protected $skippedTests = [
        'testExpiration' => 'Testing expiration slows down the test suite',
        'testHasItemReturnsFalseWhenDeferredItemIsExpired' => 'Testing expiration slows down the test suite',
        'testDefaultLifeTime' => 'Testing expiration slows down the test suite',
    ];

    protected static $redis;

    public function createCachePool(int $defaultLifetime = 0, string $testMethod = null): CacheItemPoolInterface
    {
        return new RedisAdapter(self::$redis, str_replace('\\', '.', __CLASS__), $defaultLifetime);
    }

    public static function setUpBeforeClass(): void
    {
        if (!\extension_loaded('redis')) {
            throw new SkippedTestSuiteError('Extension redis required.');
        }
        try {
            (new \Redis())->connect(...explode(':', getenv('REDIS_HOST')));
        } catch (\Exception $e) {
            throw new SkippedTestSuiteError(getenv('REDIS_HOST').': '.$e->getMessage());
        }
    }

    public static function tearDownAfterClass(): void
    {
        self::$redis = null;
    }

    /**
     * @runInSeparateProcess
     */
    public function testClearWithPrefix()
    {
        $cache = $this->createCachePool(0, __FUNCTION__);

        $cache->save($cache->getItem('foo')->set('bar'));
        $this->assertTrue($cache->hasItem('foo'));

        $cache->clear();
        $this->assertFalse($cache->hasItem('foo'));
    }
}
