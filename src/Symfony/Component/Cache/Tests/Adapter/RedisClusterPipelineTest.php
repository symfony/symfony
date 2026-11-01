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

use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\RedisAdapter;
use Symfony\Component\Cache\Adapter\RedisTagAwareAdapter;

#[RequiresPhpExtension('redis')]
class RedisClusterPipelineTest extends TestCase
{
    protected function setUp(): void
    {
        if (!method_exists(\RedisCluster::class, 'pipeline')) {
            $this->markTestSkipped('The Redis extension does not support cluster pipelines.');
        }
    }

    public function testDeferredWritesUseOnePipeline()
    {
        $redis = $this->createMock(\RedisCluster::class);
        $redis->expects($this->once())->method('mget')->with(['batch:first', 'batch:second'])->willReturn([false, false]);
        $redis->expects($this->once())->method('multi')->with(\Redis::PIPELINE)->willReturnSelf();
        $redis->expects($this->exactly(2))->method('setEx')->willReturnCallback(function ($id, $ttl, $value) use ($redis) {
            $this->assertSame(3600, $ttl);
            $this->assertSame(serialize(substr($id, 6)), $value);

            return $redis;
        });
        $redis->expects($this->once())->method('exec')->willReturn([true, true]);

        $pool = new RedisAdapter($redis, 'batch', 3600);
        foreach ($pool->getItems(['first', 'second']) as $key => $item) {
            $this->assertTrue($pool->saveDeferred($item->set($key)));
        }

        $this->assertTrue($pool->commit());
    }

    #[TestWith(['false'])]
    #[TestWith(['short'])]
    #[TestWith(['exception'])]
    public function testFailedPipelineDoesNotRetryWrites(string $failure)
    {
        $redis = $this->createMock(\RedisCluster::class);
        $redis->method('mget')->willReturn([false, false]);
        $redis->expects($this->once())->method('multi')->with(\Redis::PIPELINE)->willReturnSelf();
        $redis->expects($this->exactly(2))->method('setEx')->willReturnSelf();
        $exec = $redis->expects($this->once())->method('exec');
        if ('exception' === $failure) {
            $exec->willThrowException(new \RedisClusterException('The pipeline response is incomplete.'));
        } else {
            $exec->willReturn('false' === $failure ? false : [true]);
        }

        $pool = new RedisAdapter($redis, 'batch', 3600);
        foreach ($pool->getItems(['first', 'second']) as $key => $item) {
            $pool->saveDeferred($item->set($key));
        }

        $this->assertFalse($pool->commit());
    }

    public function testFailedPipelineStartDoesNotSendWrites()
    {
        $redis = $this->createMock(\RedisCluster::class);
        $redis->method('mget')->willReturn([false, false]);
        $redis->expects($this->once())->method('multi')->with(\Redis::PIPELINE)->willReturn(false);
        $redis->expects($this->never())->method('setEx');
        $redis->expects($this->never())->method('exec');

        $pool = new RedisAdapter($redis, 'batch', 3600);
        foreach ($pool->getItems(['first', 'second']) as $key => $item) {
            $pool->saveDeferred($item->set($key));
        }

        $this->assertFalse($pool->commit());
    }

    #[TestWith([false])]
    #[TestWith([true])]
    public function testQueueFailureDiscardsOnlyAnActivePipeline(bool $pipeline)
    {
        $mode = $pipeline ? \Redis::PIPELINE : \Redis::ATOMIC;
        $redis = $this->createMock(\RedisCluster::class);
        $redis->method('mget')->willReturn([false, false]);
        $redis->method('getmode')->willReturn($mode);
        $redis->expects($this->once())->method('multi')->with(\Redis::PIPELINE)->willReturnSelf();
        $redis->expects($this->once())->method('setEx')->willThrowException(new \RedisClusterException('The command cannot be queued.'));
        $redis->expects(\Redis::PIPELINE === $mode ? $this->once() : $this->never())->method('discard')->willReturn(true);
        $redis->expects($this->never())->method('exec');

        $pool = new RedisAdapter($redis, 'batch', 3600);
        foreach ($pool->getItems(['first', 'second']) as $key => $item) {
            $pool->saveDeferred($item->set($key));
        }

        $this->assertFalse($pool->commit());
    }

    public function testTagRegistrationWaitsForItemWrites()
    {
        $events = [];
        $redis = $this->createMock(\RedisCluster::class);
        $redis->method('mget')->willReturn([false, false]);
        $redis->method('_masters')->willReturn([['127.0.0.1', 6379]]);
        $redis->method('info')->willReturn(['maxmemory_policy' => 'noeviction']);
        $redis->expects($this->exactly(2))->method('multi')->with(\Redis::PIPELINE)->willReturnCallback(static function () use ($redis, &$events) {
            $events[] = 'begin';

            return $redis;
        });
        $redis->expects($this->exactly(2))->method('setEx')->willReturnCallback(static function ($id) use ($redis, &$events) {
            $events[] = 'write:'.$id;

            return $redis;
        });
        $redis->expects($this->once())->method('sAdd')->with("batch:\1tags\1shared", 'batch:first', 'batch:second')->willReturnCallback(static function () use ($redis, &$events) {
            $events[] = 'tag';

            return $redis;
        });
        $redis->expects($this->exactly(2))->method('exec')->willReturnCallback(static function () use (&$events) {
            $events[] = 'execute';

            return \count($events) < 5 ? [true, true] : [2];
        });

        $pool = new RedisTagAwareAdapter($redis, 'batch', 3600);
        foreach ($pool->getItems(['first', 'second']) as $key => $item) {
            $pool->saveDeferred($item->set($key)->tag('shared'));
        }

        $this->assertTrue($pool->commit());
        $this->assertSame(['begin', 'write:batch:first', 'write:batch:second', 'execute', 'begin', 'tag', 'execute'], $events);
    }

    public function testFailedItemsAreNotRegisteredWithTags()
    {
        $redis = $this->createMock(\RedisCluster::class);
        $redis->method('mget')->willReturn([false, false]);
        $redis->method('_masters')->willReturn([['127.0.0.1', 6379]]);
        $redis->method('info')->willReturn(['maxmemory_policy' => 'noeviction']);
        $redis->expects($this->exactly(2))->method('multi')->with(\Redis::PIPELINE)->willReturnSelf();
        $redis->expects($this->exactly(2))->method('setEx')->willReturnSelf();
        $redis->expects($this->once())->method('sAdd')->with("batch:\1tags\1shared", 'batch:first')->willReturnSelf();
        $redis->expects($this->exactly(2))->method('exec')->willReturnOnConsecutiveCalls([true, false], [1]);

        $pool = new RedisTagAwareAdapter($redis, 'batch', 3600);
        foreach ($pool->getItems(['first', 'second']) as $key => $item) {
            $pool->saveDeferred($item->set($key)->tag('shared'));
        }

        $this->assertFalse($pool->commit());
    }

    #[TestWith(['false'])]
    #[TestWith(['exception'])]
    public function testFailedTaggedWritesDoNotRegisterTagsOrRetry(string $failure)
    {
        $redis = $this->createMock(\RedisCluster::class);
        $redis->method('mget')->willReturn([false, false]);
        $redis->method('_masters')->willReturn([['127.0.0.1', 6379]]);
        $redis->method('info')->willReturn(['maxmemory_policy' => 'noeviction']);
        $redis->expects($this->once())->method('multi')->with(\Redis::PIPELINE)->willReturnSelf();
        $redis->expects($this->exactly(2))->method('setEx')->willReturnSelf();
        $redis->expects($this->never())->method('sAdd');
        $exec = $redis->expects($this->once())->method('exec');
        if ('exception' === $failure) {
            $exec->willThrowException(new \RedisClusterException('The pipeline response is incomplete.'));
        } else {
            $exec->willReturn(false);
        }

        $pool = new RedisTagAwareAdapter($redis, 'batch', 3600);
        foreach ($pool->getItems(['first', 'second']) as $key => $item) {
            $pool->saveDeferred($item->set($key)->tag('shared'));
        }

        $this->assertFalse($pool->commit());
    }
}
