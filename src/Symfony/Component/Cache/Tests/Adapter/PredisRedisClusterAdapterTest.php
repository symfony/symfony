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

use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\Cache\Adapter\RedisAdapter;

#[Group('integration')]
class PredisRedisClusterAdapterTest extends AbstractRedisAdapterTestCase
{
    public static function setUpBeforeClass(): void
    {
        if (!$hosts = getenv('REDIS_CLUSTER_HOSTS')) {
            self::markTestSkipped('REDIS_CLUSTER_HOSTS env var is not defined.');
        }

        self::$redis = RedisAdapter::createConnection('redis:?host['.str_replace(' ', ']&host[', $hosts).']', ['class' => \Predis\Client::class, 'redis_cluster' => true, 'prefix' => 'prefix_']);
    }

    public function testBulkReadsWithStaleSlotMap()
    {
        $host = explode(' ', getenv('REDIS_CLUSTER_HOSTS'))[0];
        $parameters = array_combine(['host', 'port'], explode(':', $host) + [1 => 6379]);
        $namespace = __FUNCTION__;
        $createCache = static function () use ($parameters, $namespace) {
            $redis = new \Predis\Client([$parameters + ['slots' => '0-16383']], ['cluster' => 'redis', 'prefix' => 'prefix_']);

            return new RedisAdapter($redis, $namespace);
        };
        $cache = $createCache();
        $values = [];

        for ($i = 0; $i < 32; ++$i) {
            $values['key_'.$i] = "value_\0\xFF\r\n".$i;
        }

        try {
            $items = iterator_to_array($cache->getItems(array_keys($values)));
            $cache = $createCache();
            foreach ($items as $key => $item) {
                $cache->saveDeferred($item->set($values[$key])->expiresAfter(60));
            }
            $this->assertTrue($cache->commit());

            $cache = $createCache();
            foreach ($cache->getItems(array_keys($values)) as $key => $item) {
                $this->assertTrue($item->isHit());
                $this->assertSame($values[$key], $item->get());
            }

            $cache = $createCache();
            $this->assertTrue($cache->deleteItems(array_keys($values)));
            foreach ($cache->getItems(array_keys($values)) as $item) {
                $this->assertFalse($item->isHit());
            }
        } finally {
            $cache->deleteItems(array_keys($values));
        }
    }
}
