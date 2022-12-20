<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Cache\Tests;

use Cache\IntegrationTests\SimpleCacheTest;
use Psr\SimpleCache\CacheInterface;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\Cache\PruneableInterface;
use Symfony\Component\Cache\Psr16Cache;

/**
 * @group time-sensitive
 */
class Psr16CacheTest extends SimpleCacheTest
{
    protected function setUp(): void
    {
        parent::setUp();

        if (\array_key_exists('testPrune', $this->skippedTests)) {
            return;
        }

        $pool = $this->createSimpleCache();
        if ($pool instanceof Psr16Cache) {
            $pool = ((array) $pool)[sprintf("\0%s\0pool", Psr16Cache::class)];
        }

        if (!$pool instanceof PruneableInterface) {
            $this->skippedTests['testPrune'] = 'Not a pruneable cache pool.';
        }

        try {
            \assert(false === true, new \Exception());
            $this->skippedTests['testGetInvalidKeys'] =
            $this->skippedTests['testGetMultipleInvalidKeys'] =
            $this->skippedTests['testGetMultipleNoIterable'] =
            $this->skippedTests['testSetInvalidKeys'] =
            $this->skippedTests['testSetMultipleInvalidKeys'] =
            $this->skippedTests['testSetMultipleNoIterable'] =
            $this->skippedTests['testHasInvalidKeys'] =
            $this->skippedTests['testDeleteInvalidKeys'] =
            $this->skippedTests['testDeleteMultipleInvalidKeys'] =
            $this->skippedTests['testDeleteMultipleNoIterable'] = 'Keys are checked only when assert() is enabled.';
        } catch (\Exception $e) {
        }
    }

    public function createSimpleCache(int $defaultLifetime = 0): CacheInterface
    {
        return new Psr16Cache(new FilesystemAdapter('', $defaultLifetime));
    }

    public static function validKeys(): array
    {
        return array_merge(parent::validKeys(), [["a\0b"]]);
    }

    public function testDefaultLifeTime()
    {
        if (isset($this->skippedTests[__FUNCTION__])) {
            self::markTestSkipped($this->skippedTests[__FUNCTION__]);
        }

        $cache = $this->createSimpleCache(2);
        $cache->clear();

        $cache->set('key.dlt', 'value');
        sleep(1);

        self::assertSame('value', $cache->get('key.dlt'));

        sleep(2);
        self::assertNull($cache->get('key.dlt'));

        $cache->clear();
    }

    public function testNotUnserializable()
    {
        if (isset($this->skippedTests[__FUNCTION__])) {
            self::markTestSkipped($this->skippedTests[__FUNCTION__]);
        }

        $cache = $this->createSimpleCache();
        $cache->clear();

        $cache->set('foo', new NotUnserializable());

        self::assertNull($cache->get('foo'));

        $cache->setMultiple(['foo' => new NotUnserializable()]);

        foreach ($cache->getMultiple(['foo']) as $value) {
        }
        self::assertNull($value);

        $cache->clear();
    }

    public function testPrune()
    {
        if (isset($this->skippedTests[__FUNCTION__])) {
            self::markTestSkipped($this->skippedTests[__FUNCTION__]);
        }

        /** @var PruneableInterface|CacheInterface $cache */
        $cache = $this->createSimpleCache();
        $cache->clear();

        $cache->set('foo', 'foo-val', new \DateInterval('PT05S'));
        $cache->set('bar', 'bar-val', new \DateInterval('PT10S'));
        $cache->set('baz', 'baz-val', new \DateInterval('PT15S'));
        $cache->set('qux', 'qux-val', new \DateInterval('PT20S'));

        sleep(30);
        $cache->prune();
        self::assertTrue($this->isPruned($cache, 'foo'));
        self::assertTrue($this->isPruned($cache, 'bar'));
        self::assertTrue($this->isPruned($cache, 'baz'));
        self::assertTrue($this->isPruned($cache, 'qux'));

        $cache->set('foo', 'foo-val');
        $cache->set('bar', 'bar-val', new \DateInterval('PT20S'));
        $cache->set('baz', 'baz-val', new \DateInterval('PT40S'));
        $cache->set('qux', 'qux-val', new \DateInterval('PT80S'));

        $cache->prune();
        self::assertFalse($this->isPruned($cache, 'foo'));
        self::assertFalse($this->isPruned($cache, 'bar'));
        self::assertFalse($this->isPruned($cache, 'baz'));
        self::assertFalse($this->isPruned($cache, 'qux'));

        sleep(30);
        $cache->prune();
        self::assertFalse($this->isPruned($cache, 'foo'));
        self::assertTrue($this->isPruned($cache, 'bar'));
        self::assertFalse($this->isPruned($cache, 'baz'));
        self::assertFalse($this->isPruned($cache, 'qux'));

        sleep(30);
        $cache->prune();
        self::assertFalse($this->isPruned($cache, 'foo'));
        self::assertTrue($this->isPruned($cache, 'baz'));
        self::assertFalse($this->isPruned($cache, 'qux'));

        sleep(30);
        $cache->prune();
        self::assertFalse($this->isPruned($cache, 'foo'));
        self::assertTrue($this->isPruned($cache, 'qux'));

        $cache->clear();
    }

    protected function isPruned(CacheInterface $cache, string $name): bool
    {
        if (Psr16Cache::class !== \get_class($cache)) {
            self::fail('Test classes for pruneable caches must implement `isPruned($cache, $name)` method.');
        }

        $pool = ((array) $cache)[sprintf("\0%s\0pool", Psr16Cache::class)];
        $getFileMethod = (new \ReflectionObject($pool))->getMethod('getFile');
        $getFileMethod->setAccessible(true);

        return !file_exists($getFileMethod->invoke($pool, $name));
    }
}

class NotUnserializable
{
    public function __wakeup()
    {
        throw new \Exception(__CLASS__);
    }
}
