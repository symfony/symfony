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

use Cache\IntegrationTests\CachePoolTest;

abstract class AdapterTestCase extends CachePoolTest
{
    protected function setUp()
    {
        parent::setUp();

        if (!array_key_exists('testDeferredSaveWithoutCommit', $this->skippedTests) && defined('HHVM_VERSION')) {
            $this->skippedTests['testDeferredSaveWithoutCommit'] = 'Destructors are called late on HHVM.';
        }
    }

    public function testDefaultLifeTime()
    {
        if (isset($this->skippedTests[__FUNCTION__])) {
            $this->markTestSkipped($this->skippedTests[__FUNCTION__]);
        }

        $cache = $this->createCachePool(2);

        $item = $cache->getItem('key.dlt');
        $item->set('value');
        $cache->save($item);
        sleep(1);

        $item = $cache->getItem('key.dlt');
        $this->assertTrue($item->isHit());

        sleep(2);
        $item = $cache->getItem('key.dlt');
        $this->assertFalse($item->isHit());
    }

    public function testExpiration()
    {
        if (isset($this->skippedTests[__FUNCTION__])) {
            $this->markTestSkipped($this->skippedTests[__FUNCTION__]);
        }

        $cache = $this->createCachePool();
        $cache->save($cache->getItem('k1')->set('v1')->expiresAfter(2));
        $cache->save($cache->getItem('k2')->set('v2')->expiresAfter(366 * 86400));

        sleep(3);
        $item = $cache->getItem('k1');
        $this->assertFalse($item->isHit());
        $this->assertNull($item->get(), "Item's value must be null when isHit() is false.");

        $item = $cache->getItem('k2');
        $this->assertTrue($item->isHit());
        $this->assertSame('v2', $item->get());
    }

    public function testNotUnserializable()
    {
        if (isset($this->skippedTests[__FUNCTION__])) {
            $this->markTestSkipped($this->skippedTests[__FUNCTION__]);
        }

        $cache = $this->createCachePool();

        $item = $cache->getItem('foo');
        $cache->save($item->set(new NotUnserializable()));

        $item = $cache->getItem('foo');
        $this->assertFalse($item->isHit());

        foreach ($cache->getItems(array('foo')) as $item) {
        }
        $cache->save($item->set(new NotUnserializable()));

        foreach ($cache->getItems(array('foo')) as $item) {
        }
        $this->assertFalse($item->isHit());
    }
}

class NotUnserializable implements \Serializable
{
    public function serialize()
    {
        return serialize(123);
    }

    public function unserialize($ser)
    {
        throw new \Exception(__CLASS__);
    }
}
