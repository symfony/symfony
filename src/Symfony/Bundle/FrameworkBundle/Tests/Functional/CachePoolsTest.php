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
        $container = static::$kernel->getContainer();

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
    }

    protected static function createKernel(array $options = array())
    {
        return parent::createKernel(array('test_case' => 'CachePools') + $options);
    }
}
