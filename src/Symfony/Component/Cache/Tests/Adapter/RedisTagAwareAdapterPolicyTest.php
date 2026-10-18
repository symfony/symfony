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
    public function testElastiCacheServerlessValkey()
    {
        $info = ['redis_version' => 7.2, 'server_name' => 'valkey', 'valkey_version' => 8, 'redis_mode' => 'cluster', 'os' => 'Amazon ElastiCache', 'arch_bits' => 64, 'run_id' => 0, 'role' => 'master', 'connected_slaves' => 1, 'slave0' => 'ip=xxx.cache.amazonaws.com,port=6380,state=online,offset=0,lag=0', 'cluster_enabled' => 1];

        $this->assertSame('volatile-lru', $this->getEvictionPolicy(false, $info));
    }

    public function testElastiCacheServerlessRedis()
    {
        $info = ['redis_version' => 7.1, 'redis_mode' => 'cluster', 'arch_bits' => 64, 'run_id' => 0, 'role' => 'master', 'connected_slaves' => 1, 'slave0' => 'ip=xxx.serverless.euw1.cache.amazonaws.com,port=6380,state=online,offset=0,lag=0', 'cluster_enabled' => 1];

        $this->assertSame('volatile-lru', $this->getEvictionPolicy([], $info));
    }

    public function testElastiCacheServerlessWithPredisSections()
    {
        $info = [
            'Server' => ['redis_version' => '7.1', 'redis_mode' => 'cluster', 'arch_bits' => '64', 'run_id' => '0'],
            'Replication' => ['role' => 'master', 'connected_slaves' => '1', 'slave0' => 'ip=xxx.serverless.euw1.cache.amazonaws.com,port=6380,state=online,offset=0,lag=0'],
            'Cluster' => ['cluster_enabled' => '1'],
        ];

        $this->assertSame('volatile-lru', $this->getEvictionPolicy([], $info));
    }

    public function testReportedPolicyIsUsed()
    {
        $this->assertSame('allkeys-lru', $this->getEvictionPolicy(['maxmemory_policy' => 'allkeys-lru'], false, 1));
        $this->assertSame('noeviction', $this->getEvictionPolicy(['Memory' => ['maxmemory_policy' => 'noeviction']], false, 1));
    }

    public function testUnknownPolicyIsEmpty()
    {
        $this->assertSame('', $this->getEvictionPolicy(false, false));
        $this->assertSame('', $this->getEvictionPolicy([], ['redis_mode' => 'standalone', 'os' => 'Linux 6.6.0 x86_64', 'run_id' => '5c1f2d3e4a5b6c7d8e9f00112233445566778899']));
        $this->assertSame('', $this->getEvictionPolicy([], ['redis_mode' => 'cluster', 'os' => 'Amazon ElastiCache', 'run_id' => '5c1f2d3e4a5b6c7d8e9f00112233445566778899']));
        $this->assertSame('', $this->getEvictionPolicy([], ['redis_mode' => 'cluster', 'run_id' => 0, 'slave0' => 'ip=xxx.serverless.euw1.cache.amazonaws.com.example.org,port=6380']));
    }

    private function getEvictionPolicy(array|false $memoryInfo, array|false $info, int $infoCalls = 2): string
    {
        $redis = $this->createMock(\Redis::class);
        $redis->expects($this->exactly($infoCalls))->method('info')->willReturnCallback(static fn ($section = null) => $section ? $memoryInfo : $info);

        $adapter = new RedisTagAwareAdapter($redis);

        return (new \ReflectionMethod($adapter, 'getRedisEvictionPolicy'))->invoke($adapter);
    }
}
