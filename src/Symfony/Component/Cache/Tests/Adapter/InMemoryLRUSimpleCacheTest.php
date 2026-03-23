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
use Psr\Clock\ClockInterface;
use Symfony\Component\Cache\InMemoryLRUSimpleCache;
use Symfony\Component\Cache\MemoryUsageCalculator;

final class InMemoryLRUSimpleCacheTest extends TestCase
{
    private const string CURRENT_DATE_TIME = '2026-01-01 00:00:00';

    public function testSetAndGet()
    {
        $cache = $this->createCache();

        $a = new \stdClass();
        $a->foo = 'bar';
        $cache->set('a', $a);

        $b = 1;
        $cache->set('b', $b);

        $c = \M_PI;
        $cache->set('c', $c);

        $d = 'random-string';
        $cache->set('d', $d);

        $e = [1, 2, 3, 'random', 5, 'stop'];
        $cache->set('e', $e);

        $f = true;
        $cache->set('f', $f);

        $g = null;
        $cache->set('g', $g);

        $this->assertSame($a, $cache->get('a'));
        $this->assertSame($b, $cache->get('b'));
        $this->assertSame($c, $cache->get('c'));
        $this->assertSame($d, $cache->get('d'));
        $this->assertSame($e, $cache->get('e'));
        $this->assertSame($f, $cache->get('f'));
        $this->assertSame($g, $cache->get('g'));
    }

    public function testOverwriteSameKey()
    {
        $cache = $this->createCache();

        $obj1 = new \stdClass();
        $obj2 = new \stdClass();

        $cache->set('key', $obj1);
        $cache->set('key', $obj2);

        $this->assertSame($obj2, $cache->get('key'));

        $replaced = 'change-to-string';
        $cache->set('key', $replaced);
        $this->assertSame($replaced, $cache->get('key'));
    }

    public function testExpiration()
    {
        $clock = $this->clock();
        $cache = $this->createCache(clock: $clock);

        $obj = new \stdClass();
        $obj->foo = 'bar';

        $cache->set('key', $obj, 10); // Expires in 10 sec

        // Simulate time passing
        $clock->advance(11);

        $result = $cache->get('key');

        $this->assertNull($result);
    }

    public function testZeroTtlDoesNotExpire()
    {
        $clock = $this->clock();
        $cache = $this->createCache(clock: $clock);

        $obj = new \stdClass();

        $cache->set('key', $obj);

        $clock->advance(1000);

        $this->assertSame($obj, $cache->get('key'));
    }

    public function testLruEviction()
    {
        $cache = $this->createCache(maxItems: 2);

        $a = new \stdClass();
        $b = new \stdClass();
        $c = new \stdClass();

        $cache->set('a', $a);
        $cache->set('b', $b);

        // access a → b is LRU
        $cache->get('a');

        $cache->set('c', $c);

        $this->assertNull($cache->get('b'));
        $this->assertSame($a, $cache->get('a'));
        $this->assertSame($c, $cache->get('c'));
    }

    public function testEvictionWithoutAccess()
    {
        $cache = $this->createCache(maxItems: 2);

        $cache->set('a', new \stdClass());
        $cache->set('b', new \stdClass());
        $cache->set('c', new \stdClass());

        $this->assertNull($cache->get('a'));
        $this->assertNotNull($cache->get('b'));
        $this->assertNotNull($cache->get('c'));
    }

    public function testDelete()
    {
        $cache = $this->createCache();

        $obj = new \stdClass();

        $cache->set('key', $obj);
        $cache->delete('key');

        $this->assertNull($cache->get('key'));
    }

    public function testDeleteMultiple()
    {
        $cache = $this->createCache();
        $a = new \stdClass();
        $b = new \stdClass();
        $c = new \stdClass();

        $cache->set('a', $a);
        $cache->set('b', $b);
        $cache->set('c', $c);

        $this->assertNotNull($cache->get('a'));
        $this->assertNotNull($cache->get('b'));
        $this->assertNotNull($cache->get('c'));

        $cache->deleteMultiple(['a', 'b']);

        $this->assertNull($cache->get('a'));
        $this->assertNull($cache->get('b'));
        $this->assertNotNull($cache->get('c'));
    }

    public function testClear()
    {
        $cache = $this->createCache();

        $cache->set('a', new \stdClass());
        $cache->set('b', new \stdClass());

        $cache->clear();

        $this->assertNull($cache->get('a'));
        $this->assertNull($cache->get('b'));
    }

    public function testAccessingItemPreventsEvictionInLruCache()
    {
        $cache = $this->createCache(maxItems: 2);

        $a = new \stdClass();
        $b = new \stdClass();

        $cache->set('a', $a);
        $cache->set('b', $b);

        $cache->get('a');

        $cache->set('c', new \stdClass());

        $this->assertNull($cache->get('b'));
        $this->assertSame($a, $cache->get('a'));
    }

    public function testHas()
    {
        $cache = $this->createCache();

        $cache->set('key', new \stdClass());

        $this->assertTrue($cache->has('key'));
        $this->assertFalse($cache->has('missing'));
    }

    public function testMemoryEviction()
    {
        $memoryUsageCalculator = $this->memoryUsageCalculator();

        $cache = $this->createCache(
            memoryUsageCalculator: $memoryUsageCalculator,
            softMemoryLimit: 250,
            hardMemoryLimit: 350,
        );

        // set the cache itself to read estimated memory as if it were measuring actual memory usage
        $memoryUsageCalculator->setCache($cache);

        $cache->set('a', new \stdClass()); // 34
        $this->assertNotNull($cache->get('a'));

        $cache->set('b', new \stdClass()); // 68
        $this->assertNotNull($cache->get('a'));
        $this->assertNotNull($cache->get('b'));

        $cache->set('c', new \stdClass()); // 102
        $this->assertNotNull($cache->get('a'));
        $this->assertNotNull($cache->get('b'));
        $this->assertNotNull($cache->get('c'));

        $cache->set('d', new \stdClass()); // 136
        $this->assertNotNull($cache->get('a'));
        $this->assertNotNull($cache->get('b'));
        $this->assertNotNull($cache->get('c'));
        $this->assertNotNull($cache->get('d'));

        $this->assertLessThanOrEqual(
            150,
            self::readProperty($cache, 'estimatedMemory'),
        );

        $cache->set(
            'e',
            (object) [
                'Name' => 'Test',
                'Last' => 'Tests',
            ],
        ); // +115 => 251 -> soft eviction started => 217 (251 - 34)
        $this->assertNull($cache->get('a'));
        $this->assertNotNull($cache->get('b'));
        $this->assertNotNull($cache->get('c'));
        $this->assertNotNull($cache->get('d'));
        $this->assertNotNull($cache->get('e'));

        $this->assertLessThanOrEqual(
            250,
            self::readProperty($cache, 'estimatedMemory'),
        );

        $cache->set('f', new \stdClass()); // +34 => 251 => soft eviction => 217
        $this->assertNull($cache->get('b'));
        $this->assertNotNull($cache->get('c'));
        $this->assertNotNull($cache->get('d'));
        $this->assertNotNull($cache->get('e'));
        $this->assertNotNull($cache->get('f'));

        $this->assertLessThanOrEqual(
            250,
            self::readProperty($cache, 'estimatedMemory'),
        );

        // large object + 232 => 217 + 232 = 449 -> hard eviction started
        $cache->set(
            'g',
            (object) [
                'Name' => 'Test',
                'Surname' => 'Testing',
                'Reason' => 'Mandatory',
                'Dimension' => 'Relative',
            ],
        );
        $this->assertNull($cache->get('c'));
        $this->assertNull($cache->get('d'));
        $this->assertNull($cache->get('e'));
        $this->assertNull($cache->get('f'));
        $this->assertNotNull($cache->get('g'));

        $this->assertLessThanOrEqual(
            250,
            self::readProperty($cache, 'estimatedMemory'),
        );
    }

    public static function readProperty(object $instance, string $property): mixed
    {
        $newScope = $instance::class;
        $invoker = static fn ($instance) => $instance->{$property};

        return \Closure::bind($invoker, null, $newScope)($instance);
    }

    private function createCache(
        ?ClockInterface $clock = null,
        ?MemoryUsageCalculator $memoryUsageCalculator = null,
        ?int $maxItems = null,
        ?int $softMemoryLimit = null,
        ?int $hardMemoryLimit = null,
    ): InMemoryLRUSimpleCache {
        return new InMemoryLRUSimpleCache(
            clock: $clock ?? $this->clock(),
            memoryUsageCalculator: $memoryUsageCalculator ?? $this->memoryUsageCalculator(),
            maxItems: $maxItems,
            softMemoryLimit: $softMemoryLimit,
            hardMemoryLimit: $hardMemoryLimit,
        );
    }

    private function clock(): ClockInterface
    {
        return new class(self::CURRENT_DATE_TIME) implements ClockInterface {
            public function __construct(
                private string $current,
            ) {
            }

            public function advance(int $seconds): void
            {
                $dateTime = new \DateTimeImmutable($this->current);
                $dateTime = $dateTime->modify("+$seconds seconds");
                $this->current = $dateTime->format('Y-m-d H:i:s');
            }

            public function now(): \DateTimeImmutable
            {
                return new \DateTimeImmutable($this->current);
            }
        };
    }

    private function memoryUsageCalculator(): MemoryUsageCalculator
    {
        return new class implements MemoryUsageCalculator {
            private ?InMemoryLRUSimpleCache $cache = null;

            public function setCache(InMemoryLRUSimpleCache $cache): void
            {
                $this->cache = $cache;
            }

            public function calculate(): int
            {
                if (null === $this->cache) {
                    return 0;
                }

                return InMemoryLRUSimpleCacheTest::readProperty($this->cache, 'estimatedMemory');
            }
        };
    }
}
