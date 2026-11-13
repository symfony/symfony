<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Tests\Functional;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\Error\Warning;
use Symfony\Bundle\FrameworkBundle\Tests\Fixtures\Cache\EarlyExpirationConsumer;
use Symfony\Component\Cache\Adapter\AdapterInterface;
use Symfony\Component\Cache\Adapter\RedisAdapter;
use Symfony\Component\Cache\Adapter\TagAwareAdapter;
use Symfony\Component\Cache\Exception\InvalidArgumentException;
use Symfony\Component\Cache\Messenger\EarlyExpirationMessage;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Messenger\Stamp\ReceivedStamp;

class CachePoolsTest extends AbstractWebTestCase
{
    public function testCachePools()
    {
        $this->doTestCachePools([], AdapterInterface::class);
    }

    #[RequiresPhpExtension('redis')]
    #[Group('integration')]
    public function testRedisCachePools()
    {
        $this->skipIfRedisUnavailable();

        try {
            $this->doTestCachePools(['root_config' => 'redis_config.yml', 'environment' => 'redis_cache'], RedisAdapter::class);
        } catch (Warning $e) {
            if (!str_starts_with($e->getMessage(), 'unable to connect to')) {
                throw $e;
            }
            $this->markTestSkipped($e->getMessage());
        } catch (InvalidArgumentException $e) {
            if (!str_starts_with($e->getMessage(), 'Redis connection ')) {
                throw $e;
            }
            $this->markTestSkipped($e->getMessage());
        }
    }

    #[RequiresPhpExtension('redis')]
    #[Group('integration')]
    public function testRedisCustomCachePools()
    {
        $this->skipIfRedisUnavailable();

        try {
            $this->doTestCachePools(['root_config' => 'redis_custom_config.yml', 'environment' => 'custom_redis_cache'], RedisAdapter::class);
        } catch (Warning $e) {
            if (!str_starts_with($e->getMessage(), 'unable to connect to')) {
                throw $e;
            }
            $this->markTestSkipped($e->getMessage());
        }
    }

    #[TestWith(['early_expiration_tagged.yml', 'cache.early_expiration'])]
    #[TestWith(['early_expiration_tagged_chain.yml', 'cache.early_expiration'])]
    #[TestWith(['early_expiration_taggable.yml', '.cache.early_expiration.taggable'])]
    #[TestWith(['early_expiration_taggable_chain.yml', '.cache.early_expiration.taggable'])]
    public function testTagAwareEarlyExpiration(string $config, string $poolId)
    {
        static::bootKernel(['root_config' => $config]);
        $container = static::getContainer();
        $consumer = $container->get(EarlyExpirationConsumer::class);
        $pool = $consumer->cacheEarlyExpiration;
        $computation = $consumer->computation;
        $transport = $container->get('messenger.transport.async');
        $this->assertFalse(static::$kernel->getContainer()->has('cache.early_expiration'));
        $pool->clear();

        $this->assertSame(1, $pool->get('key', $computation));
        $this->assertSame(1, $pool->get('key', $computation, \INF));
        $this->assertSame(1, $computation->calls);
        $this->assertCount(1, $transport->getSent());
        $envelope = $transport->get()[0];
        $this->assertInstanceOf(EarlyExpirationMessage::class, $message = $envelope->getMessage());
        $this->assertSame($poolId, $message->getPool());

        $container->get('cache.bus')->dispatch($envelope->with(new ReceivedStamp('async')));

        $this->assertSame(2, $pool->get('key', $computation, 0));
        $this->assertSame(2, $computation->calls);

        $pool->invalidateTags(['group']);

        $this->assertFalse($pool->hasItem('key'));
        $this->assertSame(3, $pool->get('key', $computation, 0));
        $this->assertSame(3, $computation->calls);
    }

    private function doTestCachePools($options, $adapterClass)
    {
        static::bootKernel($options);
        $container = static::getContainer();

        $pool1 = $container->get('cache.pool1');
        $this->assertInstanceOf($adapterClass, $pool1);

        $key = 'foobar';
        $pool1->deleteItem($key);
        $item = $pool1->getItem($key);
        $this->assertFalse($item->isHit());

        $item->set('baz');
        $pool1->save($item);
        $item = $pool1->getItem($key);
        $this->assertTrue($item->isHit());

        $pool2 = $container->get('cache.pool2');
        $pool2->save($item);

        $item = $pool2->getItem($key);
        $this->assertTrue($item->isHit());

        $prefix = "\0".TagAwareAdapter::class."\0";
        $pool4 = $container->get('cache.pool4');
        $this->assertInstanceof(TagAwareAdapter::class, $pool4);
        $pool4 = (array) $pool4;
        $this->assertSame($pool4[$prefix.'pool'], $pool4[$prefix.'tags'] ?? $pool4['tags']);

        $pool5 = $container->get('cache.pool5');
        $this->assertInstanceof(TagAwareAdapter::class, $pool5);
        $pool5 = (array) $pool5;
        $this->assertSame($pool2, $pool5[$prefix.'tags'] ?? $pool5['tags']);

        $pool6 = $container->get('cache.pool6');
        $this->assertInstanceof(TagAwareAdapter::class, $pool6);
        $pool6 = (array) $pool6;
        $this->assertSame($pool4[$prefix.'pool'], $pool6[$prefix.'tags'] ?? $pool6['tags']);

        $pool7 = $container->get('cache.pool7');
        $this->assertNotInstanceof(TagAwareAdapter::class, $pool7);
    }

    protected static function createKernel(array $options = []): KernelInterface
    {
        return parent::createKernel(['test_case' => 'CachePools'] + $options);
    }

    private function skipIfRedisUnavailable()
    {
        try {
            (new \Redis())->connect(...explode(':', getenv('REDIS_HOST')));
        } catch (\Exception $e) {
            self::markTestSkipped($e->getMessage());
        }
    }
}
