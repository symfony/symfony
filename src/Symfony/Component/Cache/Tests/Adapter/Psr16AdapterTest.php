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

use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\AbstractLogger;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\Cache\Adapter\Psr16Adapter;
use Symfony\Component\Cache\Exception\InvalidArgumentException;
use Symfony\Component\Cache\Psr16Cache;

/**
 * @group time-sensitive
 */
class Psr16AdapterTest extends AdapterTestCase
{
    protected static ?string $allowPsr6Keys = null;

    protected $skippedTests = [
        'testPrune' => 'Psr16adapter just proxies',
        'testClearPrefix' => 'SimpleCache cannot clear by prefix',
    ];

    private TestLogger $testLogger;

    public function createCachePool(int $defaultLifetime = 0): CacheItemPoolInterface
    {
        $this->testLogger = new TestLogger();
        $psr16Adapter = new Psr16Adapter(new Psr16Cache(new FilesystemAdapter()), '', $defaultLifetime);
        $psr16Adapter->setLogger($this->testLogger);

        return $psr16Adapter;
    }

    /**
     * @dataProvider invalidKeys
     */
    public function testGetItemInvalidKeys($key)
    {
        if (isset($this->skippedTests[__FUNCTION__])) {
            $this->markTestSkipped($this->skippedTests[__FUNCTION__]);
        }

        try {
            $this->cache->getItem($key);
        } catch (\Exception $exception) {
            $this->assertInstanceOf(InvalidArgumentException::class, $exception);

            return;
        }

        $this->assertNotEmpty($this->testLogger->records);
        $record = $this->testLogger->records[0];

        $this->assertSame('warning', $record['level']);
        $this->assertSame(sprintf('Failed to fetch key "{key}": Cache key "%s" contains reserved characters "{}()/\\@:".', $key), $record['message']);
        $this->assertSame('Symfony\\Component\\Cache\\Adapter\\Psr16Adapter', $record['context']['cache-adapter']);
        $this->assertSame($key, $record['context']['key']);

        $exception = $record['context']['exception'];
        $this->assertInstanceOf(InvalidArgumentException::class, $exception);
        $this->assertSame(sprintf('Cache key "%s" contains reserved characters "{}()/\\@:".', $key), $exception->getMessage());
    }

    /**
     * @dataProvider invalidKeys
     */
    public function testHasItemInvalidKeys($key)
    {
        if (isset($this->skippedTests[__FUNCTION__])) {
            $this->markTestSkipped($this->skippedTests[__FUNCTION__]);
        }

        try {
            $this->cache->hasItem($key);
        } catch (\Exception $exception) {
            $this->assertInstanceOf(InvalidArgumentException::class, $exception);

            return;
        }

        $this->assertNotEmpty($this->testLogger->records);
        $record = $this->testLogger->records[0];

        $this->assertSame('warning', $record['level']);
        $this->assertSame(sprintf('Failed to check if key "{key}" is cached: Cache key "%s" contains reserved characters "{}()/\@:".', $key), $record['message']);
        $this->assertSame('Symfony\\Component\\Cache\\Adapter\\Psr16Adapter', $record['context']['cache-adapter']);
        $this->assertSame($key, $record['context']['key']);

        $exception = $record['context']['exception'];
        $this->assertInstanceOf(InvalidArgumentException::class, $exception);
        $this->assertSame(sprintf('Cache key "%s" contains reserved characters "{}()/\\@:".', $key), $exception->getMessage());
    }

    /**
     * @dataProvider invalidKeys
     */
    public function testDeleteItemInvalidKeys($key)
    {
        if (isset($this->skippedTests[__FUNCTION__])) {
            $this->markTestSkipped($this->skippedTests[__FUNCTION__]);
        }

        try {
            $this->cache->deleteItem($key);
        } catch (\Exception $exception) {
            $this->assertInstanceOf(InvalidArgumentException::class, $exception);

            return;
        }

        $this->assertNotEmpty($this->testLogger->records);
        $record = $this->testLogger->records[0];

        $this->assertSame('warning', $record['level']);
        $this->assertSame(sprintf('Failed to delete key "{key}": Cache key "%s" contains reserved characters "{}()/\@:".', $key), $record['message']);
        $this->assertSame('Symfony\\Component\\Cache\\Adapter\\Psr16Adapter', $record['context']['cache-adapter']);
        $this->assertSame($key, $record['context']['key']);

        $exception = $record['context']['exception'];
        $this->assertInstanceOf(InvalidArgumentException::class, $exception);
        $this->assertSame(sprintf('Cache key "%s" contains reserved characters "{}()/\\@:".', $key), $exception->getMessage());
    }

    public function testValidCacheKeyWithNamespace()
    {
        $cache = new Psr16Adapter(new Psr16Cache(new ArrayAdapter()), 'some_namespace', 0);
        $item = $cache->getItem('my_key');
        $item->set('someValue');
        $cache->save($item);

        $this->assertTrue($cache->getItem('my_key')->isHit(), 'Stored item is successfully retrieved.');
    }
}

final class TestLogger extends AbstractLogger
{
    public array $records = [];

    public function log($level, $message, array $context = []): void
    {
        $this->records[] = [
            'level' => $level,
            'message' => $message,
            'context' => $context,
        ];
    }
}
