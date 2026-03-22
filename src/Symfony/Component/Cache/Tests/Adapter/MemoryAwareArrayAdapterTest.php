<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Adapter;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\Cache\Adapter\MemoryAwareArrayAdapter;
use Symfony\Component\Cache\Adapter\MemoryUsageCalculator;
use Symfony\Component\Cache\Tests\Adapter\ArrayAdapterTest;
use Symfony\Component\Clock\MockClock;

#[Group('time-sensitive')]
class MemoryAwareArrayAdapterTest extends ArrayAdapterTest
{
    protected $skippedTests = [
        'testGetMetadata' => 'ArrayAdapter does not keep metadata.',
        'testDeferredSaveWithoutCommit' => 'Assumes a shared cache which ArrayAdapter is not.',
        'testHasItemReturnsFalseWhenDeferredItemIsExpired' => 'Assumes a shared cache which ArrayAdapter is not.',
        'testSaveWithoutExpire' => 'Assumes a shared cache which ArrayAdapter is not.',
        'testNotUnserializable' => 'MemoryAwareArrayAdapter does not support serialization.',
        'testErrorsDontInvalidate' => 'MemoryAwareArrayAdapter does not support serialization, so closure could be stored',
    ];

    public function createCachePool(int $defaultLifetime = 0): CacheItemPoolInterface
    {
        return new MemoryAwareArrayAdapter($defaultLifetime);
    }

    public function testGetValuesHitAndMiss()
    {
        $this->markTestSkipped('MemoryAwareArrayAdapter does not support serialization, so closure could be stored');
    }

    #[DataProvider('memoryInputs')]
    public function testMemoryParsing($input, int $output)
    {
        $reflector = new \ReflectionMethod(MemoryAwareArrayAdapter::class, 'parseMemoryLimit');
        $memory = $reflector->invoke(null, $input);

        self::assertSame($output, $memory);
    }

    public static function memoryInputs()
    {
        yield [10, 10];
        yield [100, 100];
        yield ['100', 100];
        yield ['100x', 100];
        yield ['1k', 1024];
        yield ['1024M', 1_073_741_824];
        yield ['1G', 1_073_741_824];
    }

    public function testMemoryEviction()
    {
        $memoryUsageCalculator = $this->memoryUsageCalculator();
        $cache = new MemoryAwareArrayAdapter(
            0,
            0,
            0,
            250,
            350,
            null,
            $memoryUsageCalculator,
            new MockClock(),
        );

        $memoryUsageCalculator->advance(30);
        $item = $cache->getItem('a');
        $item->set(new \stdClass());
        $cache->save($item);
        $this->assertTrue($cache->hasItem('a'));

        $memoryUsageCalculator->advance(30);
        $item = $cache->getItem('b');
        $item->set(new \stdClass());
        $cache->save($item);

        $this->assertTrue($cache->hasItem('a'));
        $this->assertTrue($cache->hasItem('b'));

        $memoryUsageCalculator->advance(30);
        $item = $cache->getItem('c');
        $item->set(new \stdClass());
        $cache->save($item);
        $this->assertTrue($cache->hasItem('a'));
        $this->assertTrue($cache->hasItem('b'));
        $this->assertTrue($cache->hasItem('c'));

        $memoryUsageCalculator->advance(30);
        $item = $cache->getItem('d');
        $item->set(new \stdClass());
        $cache->save($item);
        $this->assertTrue($cache->hasItem('a'));
        $this->assertTrue($cache->hasItem('b'));
        $this->assertTrue($cache->hasItem('c'));
        $this->assertTrue($cache->hasItem('d'));

        $this->assertLessThanOrEqual(
            120,
            $memoryUsageCalculator->calculate(),
        );

        // simulate large object creation + 131 => 120 + 131 = 251 -> soft eviction started (251-30)
        $memoryUsageCalculator->advance(131);
        $memoryUsageCalculator->advanceOnConsecutiveCalculationCalls(0, 0, 0, -30);
        $item = $cache->getItem('e');
        $item->set(new \stdClass());
        $cache->save($item);
        $this->assertFalse($cache->hasItem('a'));
        $this->assertTrue($cache->hasItem('b'));
        $this->assertTrue($cache->hasItem('c'));
        $this->assertTrue($cache->hasItem('d'));
        $this->assertTrue($cache->hasItem('e'));

        $this->assertLessThanOrEqual(
            250,
            $memoryUsageCalculator->calculate(),
        );

        // +30 => 251 => soft eviction started again -> -30
        $memoryUsageCalculator->advance(30);
        $memoryUsageCalculator->advanceOnConsecutiveCalculationCalls(0, 0, 0, -30);
        $item = $cache->getItem('f');
        $item->set(new \stdClass());
        $cache->save($item);
        $this->assertFalse($cache->hasItem('b'));
        $this->assertTrue($cache->hasItem('c'));
        $this->assertTrue($cache->hasItem('d'));
        $this->assertTrue($cache->hasItem('e'));
        $this->assertTrue($cache->hasItem('f'));

        $this->assertLessThanOrEqual(
            250,
            $memoryUsageCalculator->calculate(),
        );

        // large object + 230 => 221 + 230 = 451 -> hard eviction started
        $memoryUsageCalculator->advance(230);
        $memoryUsageCalculator->advanceOnConsecutiveCalculationCalls(0, -30, -30, -131, -30);
        $item = $cache->getItem('g');
        $item->set(new \stdClass());
        $cache->save($item);
        $this->assertFalse($cache->hasItem('c'));
        $this->assertFalse($cache->hasItem('d'));
        $this->assertFalse($cache->hasItem('e'));
        $this->assertFalse($cache->hasItem('f'));
        $this->assertTrue($cache->hasItem('g'));

        $this->assertLessThanOrEqual(
            250,
            $memoryUsageCalculator->calculate(),
        );
    }

    private function memoryUsageCalculator(): MemoryUsageCalculator
    {
        return new class implements MemoryUsageCalculator {
            private int $estimatedMemory = 0;

            private array $consecutiveCalculations = [];

            public function advance(int $size): void
            {
                $this->estimatedMemory += $size;
            }

            public function advanceOnConsecutiveCalculationCalls(int ...$sizes): void
            {
                array_push($this->consecutiveCalculations, ...$sizes);
            }

            public function calculate(): int
            {
                $advancedSize = array_shift($this->consecutiveCalculations);
                if (null !== $advancedSize) {
                    $this->estimatedMemory += $advancedSize;
                }

                return $this->estimatedMemory;
            }
        };
    }
}
