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

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\RedisTagAwareAdapter;

#[Group('integration')]
class RedisTagAwareAdapterInvalidationTest extends TestCase
{
    public function testLargeTagInvalidationBoundsEachScript()
    {
        $redis = $this->createRedis();

        $redis->del($redis->callsKey);
        $pool = new RedisTagAwareAdapter($redis, __FUNCTION__);
        for ($i = 0; $i < 5001; ++$i) {
            $pool->saveDeferred($pool->getItem('item-'.$i)->set('value')->tag('large'));
        }
        self::assertTrue($pool->commit());
        self::assertTrue($pool->invalidateTags(['large']));
        foreach ($pool->getItems(array_map(static fn ($i) => 'item-'.$i, range(0, 5000))) as $item) {
            self::assertFalse($item->isHit());
        }
        self::assertLessThanOrEqual(1024, $redis->get($redis->callsKey));
    }

    public function testInvalidationPreservesRetaggedReplacement()
    {
        $redis = $this->createRedis();
        $other = $this->createRedis();
        $pool = new RedisTagAwareAdapter($redis, __FUNCTION__);
        $replacement = new RedisTagAwareAdapter($other, __FUNCTION__);
        $pool->save($pool->getItem('item')->set('old')->tag('tag'));
        $this->padTag($pool);
        $called = false;
        $redis->beforeScript = static function (string $lua) use ($redis, $replacement, &$called) {
            if (str_contains($lua, 'SRANDMEMBER')) {
                $redis->beforeScript = null;
                $called = true;
                $replacement->save($replacement->getItem('item')->set('replacement')->tag('other'));
            }
        };

        self::assertTrue($pool->invalidateTags(['tag']));
        self::assertTrue($called);
        self::assertSame('replacement', $pool->getItem('item')->get());
        self::assertTrue($pool->getItem('item')->isHit());
    }

    public function testInvalidationPreservesReplacementBetweenTagReadAndDeletion()
    {
        $redis = $this->createRedis();
        $other = $this->createRedis();
        $pool = new RedisTagAwareAdapter($redis, __FUNCTION__);
        $replacement = new RedisTagAwareAdapter($other, __FUNCTION__);
        $pool->save($pool->getItem('item')->set('old')->tag('tag'));
        $this->padTag($pool);
        $called = false;
        $redis->beforeScript = static function (string $lua, array $args) use ($redis, $replacement, &$called) {
            if (str_contains($lua, 'UNLINK') && str_contains($lua, 'GETRANGE') && array_any($args, static fn ($arg) => \is_string($arg) && str_ends_with($arg, ':item'))) {
                $redis->beforeScript = null;
                $called = true;
                $replacement->save($replacement->getItem('item')->set('replacement'));
            }
        };

        self::assertTrue($pool->invalidateTags(['tag']));
        self::assertTrue($called);
        self::assertSame('replacement', $pool->getItem('item')->get());
        self::assertTrue($pool->getItem('item')->isHit());
    }

    public function testReplacementRetainsUnchangedTagRelation()
    {
        $redis = $this->createRedis();
        $other = $this->createRedis();
        $pool = new RedisTagAwareAdapter($redis, __FUNCTION__);
        $replacement = new RedisTagAwareAdapter($other, __FUNCTION__);
        $pool->save($pool->getItem('item')->set('old')->tag('tag'));
        $this->padTag($pool);
        $redis->beforeScript = static function (string $lua, array $args) use ($redis, $replacement) {
            if (str_contains($lua, 'UNLINK') && str_contains($lua, 'GETRANGE') && array_any($args, static fn ($arg) => \is_string($arg) && str_ends_with($arg, ':item'))) {
                $redis->beforeScript = null;
                $replacement->save($replacement->getItem('item')->set('replacement')->tag(['tag', 'other']));
            }
        };

        self::assertTrue($pool->invalidateTags(['tag']));
        self::assertTrue($pool->getItem('item')->isHit());
        self::assertSame('replacement', $pool->getItem('item')->get());
        self::assertTrue($pool->invalidateTags(['tag']));
        self::assertFalse($pool->getItem('item')->isHit());
    }

    public function testRetryCompletesInterruptedInvalidation()
    {
        $redis = $this->createRedis();
        $pool = new RedisTagAwareAdapter($redis, __FUNCTION__);
        $pool->save($pool->getItem('item')->set('value')->tag('tag'));
        $this->padTag($pool);
        $redis->beforeScript = static function (string $lua) use ($redis) {
            if (str_contains($lua, 'SRANDMEMBER')) {
                $redis->beforeScript = null;
                $redis->failAfterExec = true;
            }
        };
        self::assertFalse($pool->invalidateTags(['tag']));
        self::assertTrue($pool->getItem('item')->isHit());

        $retry = new RedisTagAwareAdapter($this->createRedis(), __FUNCTION__);
        self::assertTrue($retry->invalidateTags(['tag']));
        self::assertFalse($retry->getItem('item')->isHit());
    }

    public function testPruneCompletesInterruptedInvalidation()
    {
        $redis = $this->createRedis();
        $pool = new RedisTagAwareAdapter($redis, __FUNCTION__);
        $pool->save($pool->getItem('item')->set('value')->tag('tag'));
        $this->padTag($pool);
        $redis->beforeScript = static function (string $lua) use ($redis) {
            if (str_contains($lua, 'SRANDMEMBER')) {
                $redis->beforeScript = null;
                $redis->failAfterExec = true;
            }
        };
        self::assertFalse($pool->invalidateTags(['tag']));
        self::assertTrue($pool->getItem('item')->isHit());

        $retry = new RedisTagAwareAdapter($this->createRedis(), __FUNCTION__);
        self::assertTrue($retry->prune());
        self::assertFalse($retry->getItem('item')->isHit());
    }

    public function testFailedReadBatchDoesNotDiscardPendingItems()
    {
        $redis = $this->createRedis();
        $pool = new RedisTagAwareAdapter($redis, __FUNCTION__);
        $pool->save($pool->getItem('item')->set('value')->tag('tag'));
        $this->padTag($pool);
        $redis->beforeScript = static function (string $lua) use ($redis) {
            if (str_contains($lua, 'GETRANGE') && !str_contains($lua, 'UNLINK')) {
                $redis->beforeScript = null;
                $redis->failNextExec = true;
            }
        };

        self::assertFalse($pool->invalidateTags(['tag']));
        $retry = new RedisTagAwareAdapter($this->createRedis(), __FUNCTION__);
        self::assertTrue($retry->getItem('item')->isHit());
        self::assertTrue($retry->invalidateTags(['tag']));
        self::assertFalse($retry->getItem('item')->isHit());
    }

    public function testFailedDeletionBatchDoesNotDiscardPendingItems()
    {
        $redis = $this->createRedis();
        $pool = new RedisTagAwareAdapter($redis, __FUNCTION__);
        $pool->save($pool->getItem('item')->set('value')->tag('tag'));
        $this->padTag($pool);
        $redis->beforeScript = static function (string $lua) use ($redis) {
            if (str_contains($lua, 'GETRANGE') && str_contains($lua, 'UNLINK')) {
                $redis->beforeScript = null;
                $redis->failNextExec = true;
            }
        };

        self::assertFalse($pool->invalidateTags(['tag']));
        $retry = new RedisTagAwareAdapter($this->createRedis(), __FUNCTION__);
        self::assertTrue($retry->getItem('item')->isHit());
        self::assertTrue($retry->invalidateTags(['tag']));
        self::assertFalse($retry->getItem('item')->isHit());
    }

    #[DataProvider('provideNativeSerializers')]
    public function testInvalidationWithNativeSerialization(string $serializer)
    {
        $redis = $this->createRedis();
        if ('SERIALIZER_IGBINARY' === $serializer && !\extension_loaded('igbinary')) {
            self::markTestSkipped('Extension igbinary required.');
        }
        $redis->setOption(\Redis::OPT_SERIALIZER, \constant('Redis::'.$serializer));
        $pool = new RedisTagAwareAdapter($redis, __FUNCTION__.'_'.$serializer);
        self::assertTrue($pool->save($pool->getItem('item')->set('value')->tag('tag')));
        self::assertTrue($pool->getItem('item')->isHit());
        self::assertTrue($pool->invalidateTags(['tag']));
        self::assertFalse($pool->getItem('item')->isHit());
    }

    public static function provideNativeSerializers(): iterable
    {
        yield ['SERIALIZER_PHP'];
        yield ['SERIALIZER_IGBINARY'];
    }

    public function testInvalidationDoesNotLoadItemPayloads()
    {
        $redis = $this->createRedis();
        $pool = new RedisTagAwareAdapter($redis, __FUNCTION__);
        self::assertTrue($pool->save($pool->getItem('item')->set(str_repeat('v', 1000000))->tag('tag')));
        $this->padTag($pool);
        $redis->maximumReturnedStringLength = 0;
        self::assertTrue($pool->invalidateTags(['tag']));
        self::assertFalse($pool->getItem('item')->isHit());
        self::assertLessThan(1000, $redis->maximumReturnedStringLength);
    }

    #[DataProvider('provideInvalidMetadataReplies')]
    public function testInvalidMetadataReplyDoesNotCompleteInvalidation(string $stage, mixed $reply)
    {
        $redis = $this->createRedis();
        $namespace = __FUNCTION__.'_'.substr(hash('sha256', $stage.get_debug_type($reply)), 0, 16);
        $pool = new RedisTagAwareAdapter($redis, $namespace);
        $pool->save($pool->getItem('item')->set('value')->tag('tag'));
        $this->padTag($pool);
        $redis->beforeScript = static function (string $lua, array $args) use ($redis, $stage, $reply) {
            $pending = str_contains($args[0], 'pending');
            $matches = match ($stage) {
                'snapshot' => str_contains($lua, 'RENAME'),
                'pending-read' => str_contains($lua, 'SRANDMEMBER') && $pending,
                'snapshot-read' => str_contains($lua, 'SRANDMEMBER') && !$pending,
                'snapshot-remove' => str_contains($lua, 'SREM') && !$pending,
                'pending-remove' => str_contains($lua, 'SREM') && $pending,
            };
            if ($matches) {
                $redis->beforeScript = null;
                $redis->replaceNextExecResult = true;
                $redis->nextExecResult = $reply;
            }
        };

        self::assertFalse($pool->invalidateTags(['tag']));
        $retry = new RedisTagAwareAdapter($this->createRedis(), $namespace);
        self::assertTrue($retry->invalidateTags(['tag']));
        self::assertFalse($retry->getItem('item')->isHit());
    }

    public static function provideInvalidMetadataReplies(): iterable
    {
        foreach (['snapshot', 'pending-read', 'snapshot-read', 'snapshot-remove', 'pending-remove'] as $stage) {
            yield $stage.' false' => [$stage, false];
            yield $stage.' null' => [$stage, null];
        }
    }

    public function testPrunePreservesVersionedTagRelations()
    {
        $pool = new RedisTagAwareAdapter($this->createRedis(), __FUNCTION__);
        $pool->enableVersioning();
        self::assertTrue($pool->save($pool->getItem('item')->set('value')->tag('tag')));
        self::assertTrue($pool->prune());
        self::assertTrue($pool->getItem('item')->isHit());
        self::assertTrue($pool->invalidateTags(['tag']));
        self::assertFalse($pool->getItem('item')->isHit());
    }

    #[DataProvider('providePrefixes')]
    public function testInvalidationWithClientPrefix(string $prefix)
    {
        $redis = $this->createRedis();
        $redis->setOption(\Redis::OPT_PREFIX, $prefix);
        $pool = new RedisTagAwareAdapter($redis, __FUNCTION__);
        self::assertTrue($pool->save($pool->getItem('item')->set('value')->tag('tag')));
        self::assertTrue($pool->invalidateTags(['tag']));
        self::assertFalse($pool->getItem('item')->isHit());
    }

    #[DataProvider('providePrefixes')]
    public function testClusterInvalidationWithClientPrefix(string $prefix)
    {
        if (!class_exists(\RedisCluster::class) || !$hosts = getenv('REDIS_CLUSTER_HOSTS')) {
            self::markTestSkipped('RedisCluster and REDIS_CLUSTER_HOSTS are required.');
        }
        try {
            $redis = new \RedisCluster(null, explode(' ', $hosts), 2, 2);
        } catch (\RedisException $e) {
            self::markTestSkipped($e->getMessage());
        }
        $redis->setOption(\Redis::OPT_PREFIX, $prefix);
        $pool = new RedisTagAwareAdapter($redis, __FUNCTION__);
        self::assertTrue($pool->save($pool->getItem('item')->set('value')->tag('tag')));
        self::assertTrue($pool->invalidateTags(['tag']));
        self::assertFalse($pool->getItem('item')->isHit());
    }

    public function testArrayInvalidationWithDifferentSerializers()
    {
        if (!class_exists(\RedisArray::class) || !$address = getenv('REDIS_HOST')) {
            self::markTestSkipped('RedisArray and REDIS_HOST are required.');
        }
        if (str_starts_with($address, '/')) {
            self::markTestSkipped('A TCP Redis connection is required.');
        }
        [$hostname, $port] = array_pad(explode(':', $address, 2), 2, '6379');
        $alias = match ($hostname) {
            'localhost' => '127.0.0.1',
            '127.0.0.1' => 'localhost',
            default => gethostbyname($hostname),
        };
        if ($alias === $hostname) {
            self::markTestSkipped('A hostname and IP address for the same Redis server are required.');
        }
        try {
            $redis = new \RedisArray([$address, $alias.':'.$port], ['lazy_connect' => true]);
            foreach ($redis->_hosts() as $i => $host) {
                $connection = $redis->_instance($host);
                $connection->select($i);
                $connection->setOption(\Redis::OPT_SERIALIZER, $i ? \Redis::SERIALIZER_PHP : \Redis::SERIALIZER_NONE);
            }
        } catch (\RedisException $e) {
            self::markTestSkipped($e->getMessage());
        }
        $pool = new RedisTagAwareAdapter($redis, __FUNCTION__);
        $keys = $owners = [];
        for ($i = 0; $i < 23; ++$i) {
            $keys[] = $key = 'item-'.$i;
            $owners[$redis->_target(__FUNCTION__.':'.$key)] = true;
            $pool->saveDeferred($pool->getItem($key)->set('value')->tag('tag'));
        }
        self::assertCount(2, $owners);
        self::assertTrue($pool->commit());
        foreach ($pool->getItems($keys) as $item) {
            self::assertTrue($item->isHit());
        }
        self::assertTrue($pool->invalidateTags(['tag']));
        foreach ($pool->getItems($keys) as $item) {
            self::assertFalse($item->isHit());
        }
        self::assertTrue($pool->prune());
        self::assertTrue($pool->clear());
    }

    public static function providePrefixes(): iterable
    {
        foreach (['', 'prefix_', '{tenant}:', 'client{}', 'client}', 'client{', '{}{tenant}', 'client{{}'] as $prefix) {
            yield [$prefix];
        }
    }

    public function testSubNamespacesShareTagInvalidation()
    {
        $pool = new RedisTagAwareAdapter($this->createRedis(), __FUNCTION__);
        $sub = $pool->withSubNamespace('sub');
        self::assertTrue($pool->save($pool->getItem('root')->set('root')->tag('tag')));
        self::assertTrue($sub->save($sub->getItem('child')->set('child')->tag('tag')));
        self::assertTrue($sub->invalidateTags(['tag']));
        self::assertFalse($pool->getItem('root')->isHit());
        self::assertFalse($sub->getItem('child')->isHit());
    }

    public function testPrunePreservesSubNamespaceItemsMatchingTagMetadata()
    {
        $pool = new RedisTagAwareAdapter($this->createRedis(), __FUNCTION__);
        $sub = $pool->withSubNamespace("\1tags\1")->withSubNamespace("\1tags\1");
        self::assertTrue($sub->save($sub->getItem('item')->set('value')));
        self::assertTrue($pool->prune());
        self::assertSame('value', $sub->getItem('item')->get());
    }

    #[DataProvider('provideSmallTags')]
    public function testSmallTagInvalidationUsesOneScript(int $count)
    {
        $redis = $this->createRedis();
        $pool = new RedisTagAwareAdapter($redis, __FUNCTION__.'_'.$count);
        for ($i = 0; $i < $count; ++$i) {
            $pool->saveDeferred($pool->getItem('item-'.$i)->set('value')->tag('tag'));
        }
        self::assertTrue($pool->commit());
        $redis->evalCalls = 0;
        self::assertTrue($pool->invalidateTags(['tag']));
        self::assertSame(1, $redis->evalCalls);
        foreach ($pool->getItems(array_map(static fn ($i) => 'item-'.$i, range(0, $count - 1))) as $item) {
            self::assertFalse($item->isHit());
        }
    }

    public static function provideSmallTags(): iterable
    {
        foreach ([1, 10, 100, 500] as $count) {
            yield [$count];
        }
    }

    public function testSmallTagInvalidationUsesOnePipeline()
    {
        $redis = $this->createRedis();
        $pool = new RedisTagAwareAdapter($redis, __FUNCTION__);
        $tags = [];
        for ($i = 0; $i < 10; ++$i) {
            $tags[] = $tag = 'tag-'.$i;
            self::assertTrue($pool->save($pool->getItem('item-'.$i)->set('value')->tag($tag)));
        }
        $redis->execCalls = 0;

        self::assertTrue($pool->invalidateTags($tags));
        self::assertSame(1, $redis->execCalls);
        for ($i = 0; $i < 10; ++$i) {
            self::assertFalse($pool->getItem('item-'.$i)->isHit());
        }
    }

    public function testFailedDetachPipelineCanBeRetried()
    {
        $redis = $this->createRedis();
        $pool = new RedisTagAwareAdapter($redis, __FUNCTION__);
        self::assertTrue($pool->save($pool->getItem('first')->set('value')->tag('first')));
        self::assertTrue($pool->save($pool->getItem('second')->set('value')->tag('second')));
        $redis->failNextExec = true;

        self::assertFalse($pool->invalidateTags(['first', 'second']));
        self::assertTrue($pool->getItem('first')->isHit());
        self::assertTrue($pool->getItem('second')->isHit());
        self::assertTrue($pool->invalidateTags(['first', 'second']));
        self::assertFalse($pool->getItem('first')->isHit());
        self::assertFalse($pool->getItem('second')->isHit());
    }

    public function testLargeTagInvalidationBatchesScripts()
    {
        $redis = $this->createRedis();
        $pool = new RedisTagAwareAdapter($redis, __FUNCTION__);
        $keys = [];
        for ($i = 0; $i < 1000; ++$i) {
            $keys[] = $key = 'item-'.$i;
            $pool->saveDeferred($pool->getItem($key)->set('value')->tag('tag'));
        }
        self::assertTrue($pool->commit());
        $redis->evalCalls = 0;
        self::assertTrue($pool->invalidateTags(['tag']));
        self::assertLessThanOrEqual(20, $redis->evalCalls);
        foreach ($pool->getItems($keys) as $item) {
            self::assertFalse($item->isHit());
        }
    }

    public function testTaggedReadsUseOneCommand()
    {
        $redis = $this->createRedis();
        $pool = new RedisTagAwareAdapter($redis, __FUNCTION__);
        $pool->save($pool->getItem('item')->set('value')->tag(['first', 'second']));
        $before = $redis->info('commandstats');
        self::assertSame('value', $pool->getItem('item')->get());
        self::assertTrue($pool->hasItem('item'));
        $after = $redis->info('commandstats');
        foreach (['mget' => 1, 'exists' => 1, 'get' => 0, 'eval' => 0] as $command => $expected) {
            preg_match('/calls=(\d+)/', $before['cmdstat_'.$command] ?? 'calls=0', $beforeCalls);
            preg_match('/calls=(\d+)/', $after['cmdstat_'.$command] ?? 'calls=0', $afterCalls);
            self::assertSame($expected, $afterCalls[1] - $beforeCalls[1], $command);
        }
    }

    private function padTag(RedisTagAwareAdapter $pool): void
    {
        for ($i = 0; $i < 500; ++$i) {
            $pool->saveDeferred($pool->getItem('padding-'.$i)->set('padding')->tag('tag'));
        }
        self::assertTrue($pool->commit());
    }

    private function createRedis(): \Redis
    {
        if (!\extension_loaded('redis')) {
            self::markTestSkipped('Extension redis required.');
        }

        $redis = new class extends \Redis {
            public string $callsKey = 'testLargeTagInvalidationBoundsEachScript:max';
            public ?\Closure $beforeScript = null;
            public bool $failNextExec = false;
            public bool $failAfterExec = false;
            public int $maximumReturnedStringLength = 0;
            public int $evalCalls = 0;
            public int $execCalls = 0;
            public bool $replaceNextExecResult = false;
            public mixed $nextExecResult = null;

            public function exec(): \Redis|array|false
            {
                ++$this->execCalls;
                if ($this->failNextExec) {
                    $this->failNextExec = false;
                    parent::discard();

                    return false;
                }

                $result = parent::exec();
                if ($this->replaceNextExecResult) {
                    $this->replaceNextExecResult = false;
                    $result[0] = $this->nextExecResult;
                }
                if (\is_array($result)) {
                    array_walk_recursive($result, function ($value) {
                        if (\is_string($value)) {
                            $this->maximumReturnedStringLength = max($this->maximumReturnedStringLength, \strlen($value));
                        }
                    });
                }
                if ($this->failAfterExec) {
                    $this->failAfterExec = false;
                    throw new \RuntimeException('Interrupted invalidation.');
                }

                return $result;
            }

            public function eval(string $script, array $args = [], int $num_keys = 0): mixed
            {
                ++$this->evalCalls;
                if ($this->beforeScript) {
                    ($this->beforeScript)($script, $args);
                }

                $wrapper = <<<'LUA'
                    local originalRedis = redis
                    local calls = 0
                    local redis = setmetatable({call = function (...)
                        calls = calls + 1
                        return originalRedis.call(...)
                    end, pcall = function (...)
                        calls = calls + 1
                        return originalRedis.pcall(...)
                    end}, {__index = originalRedis})
                    local function run()
                    LUA;
                $wrapper .= "\n".$script."\nend\nlocal result = run()\nlocal previous = tonumber(originalRedis.call('GET', '".$this->callsKey."')) or 0\noriginalRedis.call('SET', '".$this->callsKey."', math.max(previous, calls))\nreturn result";

                return parent::eval($wrapper, $args, $num_keys);
            }
        };

        try {
            $redis->connect(...explode(':', getenv('REDIS_HOST')));
        } catch (\RedisException $e) {
            self::markTestSkipped($e->getMessage());
        }

        return $redis;
    }
}
