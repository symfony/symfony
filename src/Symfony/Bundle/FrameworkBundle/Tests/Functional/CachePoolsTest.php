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

use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\Cache\Adapter\RedisAdapter;

class CachePoolsTest extends WebTestCase
{
    public function testCachePools()
    {
        $this->doTestCachePools(array(), FilesystemAdapter::class);
    }

    /**
     * @requires extension redis
     */
    public function testRedisCachePools()
    {
        try {
            $this->doTestCachePools(array('root_config' => 'redis_config.yml', 'environment' => 'redis_cache'), RedisAdapter::class);
        } catch (\PHPUnit_Framework_Error_Warning $e) {
            if (0 !== strpos($e->getMessage(), 'unable to connect to 127.0.0.1')) {
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
        } catch (\PHPUnit_Framework_Error_Warning $e) {
            if (0 !== strpos($e->getMessage(), 'unable to connect to 127.0.0.1')) {
                throw $e;
            }
            $this->markTestSkipped($e->getMessage());
        }
    }

    public function doTestCachePools($options, $adapterClass)
    {
        static::bootKernel($options);
        $container = static::$kernel->getContainer();

        $pool = $container->get('cache.test');
        $this->assertInstanceOf($adapterClass, $pool);

        $key = 'foobar';
        $pool->deleteItem($key);
        $item = $pool->getItem($key);
        $this->assertFalse($item->isHit());

        $item->set('baz');
        $pool->save($item);
        $item = $pool->getItem($key);
        $this->assertTrue($item->isHit());

        $container->get('cache_clearer')->clear($container->getParameter('kernel.cache_dir'));
        $item = $pool->getItem($key);
        $this->assertFalse($item->isHit());
    }

    protected static function createKernel(array $options = array())
    {
        return parent::createKernel(array('test_case' => 'CachePools') + $options);
    }
}
