<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Cache\Tests\Traits;

use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\Cache\CacheItem;
use Symfony\Component\Filesystem\Filesystem;

class ContractsTraitTest extends TestCase
{
    public static function tearDownAfterClass(): void
    {
        (new Filesystem())->remove(sys_get_temp_dir().'/symfony-cache-contracts-trait');
    }

    public function testGetReturnsHitWithItsMetadata()
    {
        $pool = $this->createPool();
        $this->saveWrapped($pool, 'cached', time() + 1000, ['bar' => 'bar']);
        $expectedMetadata = $pool->getItem('foo')->getMetadata();

        $this->assertSame('cached', $pool->get('foo', function () {
            $this->fail('Callback should not be called on a hit');
        }, null, $metadata));
        $this->assertSame($expectedMetadata, $metadata);
        $this->assertSame(['bar' => 'bar'], $metadata[CacheItem::METADATA_TAGS]);
    }

    public function testGetElectsHitForEarlyExpiration()
    {
        $logger = new class extends AbstractLogger {
            public array $messages = [];

            public function log($level, $message, array $context = []): void
            {
                $this->messages[] = [$level, $message, $context['key'] ?? null];
            }
        };

        $pool = $this->createPool();
        $pool->setLogger($logger);
        $this->saveWrapped($pool, 'stale', time() + 1000);

        $this->assertSame('stale', $pool->get('foo', function () {
            $this->fail('Callback should not be called when the item is far from its expiration');
        }));
        $this->assertSame([], $logger->messages);

        $this->assertSame('fresh', $pool->get('foo', static fn () => 'fresh', \PHP_FLOAT_MAX));
        $this->assertSame([['info', 'Item "{key}" elected for early recomputation {delta}s before its expiration', 'foo']], $logger->messages);
        $this->assertSame('fresh', $pool->getItem('foo')->get());
    }

    public function testGetComputesAgainAfterCallbackThrew()
    {
        $pool = $this->createPool();

        try {
            $pool->get('foo', static fn () => throw new \RuntimeException('boom'));
            $this->fail('RuntimeException expected');
        } catch (\RuntimeException $e) {
            $this->assertSame('boom', $e->getMessage());
        }

        $this->assertSame('bar', $pool->get('foo', static fn () => 'bar'));
        $this->assertSame('bar', $pool->getItem('foo')->get());
    }

    private function createPool(): FilesystemAdapter
    {
        $pool = new FilesystemAdapter('', 0, sys_get_temp_dir().'/symfony-cache-contracts-trait');
        $pool->clear();

        return $pool;
    }

    private function saveWrapped(FilesystemAdapter $pool, string $value, int $expiry, array $tags = []): void
    {
        $wrapper = "\xA9";
        $metadata = [CacheItem::METADATA_EXPIRY => $expiry, CacheItem::METADATA_CTIME => 50];

        if ($tags) {
            $metadata[CacheItem::METADATA_TAGS] = $tags;
        }

        $pool->save($pool->getItem('foo')->set(new $wrapper($value, $metadata)));
    }
}
