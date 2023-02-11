<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Lock\Tests\Store;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Traits\RedisProxy;
use Symfony\Component\Lock\Store\RedisStore;
use Symfony\Component\Lock\Store\StoreFactory;

/**
 * @requires extension redis
 */
class RedisProxyStoreFactoryTest extends TestCase
{
    public function testCreateStore()
    {
        if (!class_exists(RedisProxy::class)) {
            $this->markTestSkipped();
        }

        $store = StoreFactory::createStore($this->createMock(RedisProxy::class));

        $this->assertInstanceOf(RedisStore::class, $store);
    }
}
