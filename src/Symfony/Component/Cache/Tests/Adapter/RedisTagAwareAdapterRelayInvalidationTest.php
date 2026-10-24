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
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\RedisTagAwareAdapter;
use Symfony\Component\Cache\Marshaller\DefaultMarshaller;
use Symfony\Component\Cache\Marshaller\MarshallerInterface;

#[RequiresPhpExtension('redis')]
#[RequiresPhpExtension('relay')]
#[Group('integration')]
class RedisTagAwareAdapterRelayInvalidationTest extends TestCase
{
    private string|false $pollFrequency = false;

    protected function setUp(): void
    {
        $this->pollFrequency = ini_set('relay.invalidation_poll_freq', '10000000');
        if (false === $this->pollFrequency && 10000000 > \ini_get('relay.invalidation_poll_freq')) {
            self::markTestSkipped('A relay.invalidation_poll_freq of at least 10000000 is required.');
        }
    }

    protected function tearDown(): void
    {
        if (false !== $this->pollFrequency) {
            ini_set('relay.invalidation_poll_freq', $this->pollFrequency);
        }
    }

    #[DataProvider('provideTopologies')]
    public function testRelayPruneClearsLocalCacheAfterInterruptedInvalidation(bool $cluster)
    {
        [$redis, $relay] = $this->createClients($cluster);
        $namespace = __FUNCTION__.($cluster ? '_cluster' : '_standalone');
        $relayPool = $this->createInterruptedPool($redis, $relay, $namespace);

        self::assertTrue($relayPool->prune());
        self::assertSame(0, $redis->exists($namespace.':item-0'));
        self::assertFalse($relayPool->getItem('item-0')->isHit());
        self::assertSame(\Relay\Relay::ATOMIC, $relay->getMode());
    }

    public function testRelayPrunePreservesReplacementWrittenAfterWatching()
    {
        [$redis, $relay] = $this->createClients(false);
        $namespace = __FUNCTION__;
        $relayPool = $this->createInterruptedPool($redis, $relay, $namespace);
        $replacement = new RedisTagAwareAdapter($redis, $namespace);
        $key = null;
        $relay->beforeMulti = function (string $id) use ($relayPool, $relay, $replacement, $namespace, &$key) {
            self::assertStringStartsWith($namespace.':', $id);
            $key = substr($id, \strlen($namespace) + 1);
            $this->assertLocalCache($relayPool, $relay, $key);
            self::assertTrue($replacement->save($replacement->getItem($key)->set('replacement')));
        };

        self::assertFalse($relayPool->prune());
        self::assertNotNull($key);
        self::assertSame('replacement', $replacement->getItem($key)->get());
        self::assertSame('replacement', $relayPool->getItem($key)->get());
        self::assertSame(\Relay\Relay::ATOMIC, $relay->getMode());

        self::assertTrue($relayPool->prune());
        self::assertSame('replacement', $relayPool->getItem($key)->get());
    }

    #[DataProvider('provideSkippedItems')]
    public function testRelayInvalidationRefreshesSkippedItems(bool $replacement)
    {
        [$redis, $relay] = $this->createClients(false);
        $namespace = __FUNCTION__.($replacement ? '_replacement' : '_missing');
        $relayPool = $this->createInterruptedPool($redis, $relay, $namespace);
        $id = $namespace.':item-0';
        $snapshot = null;
        foreach ($redis->keys($namespace.":\1tags\1tag:\1tags\1*") as $candidate) {
            if ($redis->sIsMember($candidate, $id)) {
                self::assertNull($snapshot);
                $snapshot = $candidate;
            }
        }
        self::assertNotNull($snapshot);
        $members = array_values(array_diff($redis->sMembers($snapshot), [$id]));
        self::assertSame(500, $redis->sRem($snapshot, ...$members));
        self::assertSame(500, $redis->del(...$members));
        self::assertSame(1, $redis->sCard($snapshot));
        $this->assertLocalCache($relayPool, $relay, 'item-0');

        if ($replacement) {
            $pool = new RedisTagAwareAdapter($redis, $namespace);
            self::assertTrue($pool->save($pool->getItem('item-0')->set('replacement')));
        } else {
            self::assertSame(1, $redis->del($id));
        }
        self::assertSame('value', $relayPool->getItem('item-0')->get());

        self::assertTrue($relayPool->invalidateTags(['tag']));
        self::assertSame($replacement ? 'replacement' : null, $relayPool->getItem('item-0')->get());
        self::assertSame($replacement ? 1 : 0, $redis->exists($id));
        self::assertSame(\Relay\Relay::ATOMIC, $relay->getMode());
    }

    public static function provideSkippedItems(): iterable
    {
        yield 'missing item' => [false];
        yield 'untagged replacement' => [true];
    }

    public static function provideTopologies(): iterable
    {
        yield [false];
        yield [true];
    }

    private function createClients(bool $cluster): array
    {
        try {
            if ($cluster) {
                if (!class_exists(\Relay\Cluster::class) || !$hosts = getenv('REDIS_CLUSTER_HOSTS')) {
                    self::markTestSkipped('Relay Cluster and REDIS_CLUSTER_HOSTS are required.');
                }
                $seeds = explode(' ', $hosts);

                return [new \RedisCluster(null, $seeds, 2, 2), new \Relay\Cluster(null, $seeds, 2, 2)];
            }

            $redis = new \Redis();
            $redis->connect(...explode(':', getenv('REDIS_HOST')));
            $relay = new class extends \Relay\Relay {
                public ?\Closure $beforeMulti = null;
                private string $watchedKey = '';

                public function watch($key, ...$other_keys): \Relay\Relay|bool|string
                {
                    $this->watchedKey = $key;

                    return parent::watch($key, ...$other_keys);
                }

                public function multi($mode = \Relay\Relay::MULTI): \Relay\Relay|bool
                {
                    if (\Relay\Relay::MULTI === $mode && $callback = $this->beforeMulti) {
                        $this->beforeMulti = null;
                        $callback($this->watchedKey);
                    }

                    return parent::multi($mode);
                }
            };
            $relay->connect(...explode(':', getenv('REDIS_HOST')));

            return [$redis, $relay];
        } catch (\RedisException|\Relay\Exception $e) {
            self::markTestSkipped($e->getMessage());
        }
    }

    private function createInterruptedPool(object $redis, object $relay, string $namespace): RedisTagAwareAdapter
    {
        $marshaller = new class implements MarshallerInterface {
            public bool $fail = false;
            private DefaultMarshaller $marshaller;

            public function __construct()
            {
                $this->marshaller = new DefaultMarshaller();
            }

            public function marshall(array $values, ?array &$failed): array
            {
                return $this->marshaller->marshall($values, $failed);
            }

            public function unmarshall(string $value): mixed
            {
                if ($this->fail) {
                    $this->fail = false;
                    throw new \RuntimeException('Interrupted invalidation.');
                }

                return $this->marshaller->unmarshall($value);
            }
        };
        $pool = new RedisTagAwareAdapter($redis, $namespace, 0, $marshaller);
        for ($i = 0; $i <= 500; ++$i) {
            $pool->saveDeferred($pool->getItem('item-'.$i)->set('value')->tag('tag'));
        }
        self::assertTrue($pool->commit());
        $relayPool = new RedisTagAwareAdapter($relay, $namespace);
        $this->assertLocalCache($relayPool, $relay, 'item-0');
        $marshaller->fail = true;
        self::assertFalse($pool->invalidateTags(['tag']));
        self::assertTrue($relayPool->getItem('item-0')->isHit());

        return $relayPool;
    }

    private function assertLocalCache(RedisTagAwareAdapter $pool, object $relay, string $key): void
    {
        self::assertTrue($pool->getItem($key)->isHit());
        $before = $relay instanceof \Relay\Cluster ? $relay->getTransferredBytes() : $relay->getBytes();
        self::assertTrue($pool->getItem($key)->isHit());
        $after = $relay instanceof \Relay\Cluster ? $relay->getTransferredBytes() : $relay->getBytes();
        if (!\is_array($before) || $before !== $after) {
            self::markTestSkipped('Relay in-memory caching is required.');
        }
    }
}
