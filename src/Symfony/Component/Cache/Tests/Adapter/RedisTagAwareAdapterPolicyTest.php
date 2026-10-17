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

use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\RedisTagAwareAdapter;

/**
 * @requires extension redis
 */
class RedisTagAwareAdapterPolicyTest extends TestCase
{
    /**
     * @dataProvider provideEvictionPolicies
     */
    public function testEvictionPolicy($memory, $info, string $expected)
    {
        $redis = $this->createMock(\Redis::class);
        $redis->expects($this->exactly(isset($memory['maxmemory_policy']) || isset($memory['Memory']['maxmemory_policy']) ? 1 : 2))
            ->method('info')->willReturnCallback(static fn ($section = null) => 'Memory' === $section ? $memory : $info);
        $adapter = new RedisTagAwareAdapter($redis);
        $method = new \ReflectionMethod($adapter, 'getRedisEvictionPolicy');

        $this->assertSame($expected, $method->invoke($adapter));
        $this->assertSame($expected, $method->invoke($adapter));
    }

    public static function provideEvictionPolicies(): iterable
    {
        $valkey = ['redis_mode' => 'cluster', 'run_id' => 0, 'os' => 'Amazon ElastiCache'];
        $redis = ['redis_mode' => 'cluster', 'run_id' => 0, 'slave0' => 'ip=cache-123.serverless.use1.cache.amazonaws.com,port=6380,state=online,offset=0,lag=0'];
        yield 'Valkey false section' => [false, $valkey, 'volatile-lru'];
        yield 'Valkey empty section' => [[], $valkey, 'volatile-lru'];
        yield 'Redis serverless replica' => [[], $redis, 'volatile-lru'];
        yield 'Predis nested sections' => [['Memory' => []], ['Server' => $valkey], 'volatile-lru'];
        yield 'explicit unsafe policy' => [['maxmemory_policy' => 'allkeys-lru'], $valkey, 'allkeys-lru'];
        yield 'unsafe full policy' => [[], ['Memory' => ['maxmemory_policy' => 'allkeys-lfu'], 'Server' => $valkey], 'allkeys-lfu'];
        yield 'explicit supported policy' => [['Memory' => ['maxmemory_policy' => 'noeviction']], [], 'noeviction'];
        yield 'unavailable INFO' => [false, false, ''];
        yield 'nested Redis replication' => [['Memory' => []], ['Server' => ['redis_mode' => 'cluster', 'run_id' => '0'], 'Replication' => ['slave0' => $redis['slave0']]], 'volatile-lru'];
        yield 'generic Redis' => [[], ['redis_mode' => 'cluster', 'run_id' => 0], ''];
        yield 'provisioned ElastiCache' => [[], array_replace($valkey, ['run_id' => 'some-real-run-id']), ''];
        yield 'nonzero run id with serverless replica' => [[], array_replace($redis, ['run_id' => 'some-real-run-id']), ''];
        yield 'standalone mode' => [[], array_replace($valkey, ['redis_mode' => 'standalone']), ''];
        yield 'hostname suffix spoof' => [[], array_replace($redis, ['slave0' => 'ip=cache.serverless.use1.cache.amazonaws.com.example.org,port=6380']), ''];
        yield 'provisioned replica' => [[], array_replace($redis, ['slave0' => 'ip=cache.use1.cache.amazonaws.com,port=6380']), ''];
    }
}
