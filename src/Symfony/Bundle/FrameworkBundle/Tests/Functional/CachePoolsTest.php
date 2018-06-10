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

use Symfony\Component\Cache\Adapter\AdapterInterface;
use Symfony\Component\Cache\Adapter\RedisAdapter;
use Symfony\Component\Cache\Adapter\TagAwareAdapter;
use Symfony\Component\Cache\Exception\InvalidArgumentException;

class CachePoolsTest extends WebTestCase
{
    public function testCachePools()
    {
        $this->doTestCachePools(array(), AdapterInterface::class);
    }

    /**
     * @requires extension redis
     */
    public function testRedisCachePools()
    {
        try {
            $this->doTestCachePools(array('root_config' => 'redis_config.yml', 'environment' => 'redis_cache'), RedisAdapter::class);
        } catch (\PHPUnit\Framework\Error\Warning $e) {
            if (0 !== strpos($e->getMessage(), 'unable to connect to')) {
                throw $e;
            }
            $this->markTestSkipped($e->getMessage());
        } catch (\PHPUnit_Framework_Error_Warning $e) {
            if (0 !== strpos($e->getMessage(), 'unable to connect to')) {
                throw $e;
            }
            $this->markTestSkipped($e->getMessage());
        } catch (InvalidArgumentException $e) {
            if (0 !== strpos($e->getMessage(), 'Redis connection failed')) {
                throw $e;
            }
            $this->markTestSkipped($e->getMessage());
        }
    }

    /**
     * @requires extension redis
     */
    public function testRedisCustomCachePools()
    {
        try {
            $this->doTestCachePools(array('root_config' => 'redis_custom_config.yml', 'environment' => 'custom_redis_cache'), RedisAdapter::class);
        } catch (\PHPUnit\Framework\Error\Warning $e) {
            if (0 !== strpos($e->getMessage(), 'unable to connect to')) {
                throw $e;
            }
            $this->markTestSkipped($e->getMessage());
        } catch (\PHPUnit_Framework_Error_Warning $e) {
            if (0 !== strpos($e->getMessage(), 'unable to connect to')) {
                throw $e;
            }
            $this->markTestSkipped($e->getMessage());
        }
    }

    private function doTestCachePools($options, $adapterClass)
    {
        static::bootKernel($options);
        $container = static::$container;

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

        $container->get('cache_clearer')->clear($container->getParameter('kernel.cache_dir'));
        $item = $pool1->getItem($key);
        $this->assertFalse($item->isHit());

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

    protected static function createKernel(array $options = array())
    {
        return parent::createKernel(array('test_case' => 'CachePools') + $options);
    }
}
