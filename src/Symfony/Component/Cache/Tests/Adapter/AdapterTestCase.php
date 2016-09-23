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
use Symfony\Component\Cache\Adapter\ContextAwareAdapterInterface;

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

            return;
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

    public function testNotUnserializable()
    {
        if (isset($this->skippedTests[__FUNCTION__])) {
            $this->markTestSkipped($this->skippedTests[__FUNCTION__]);

            return;
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

    public function testContext()
    {
        if (isset($this->skippedTests[__FUNCTION__])) {
            $this->markTestSkipped($this->skippedTests[__FUNCTION__]);

            return;
        }
        $cache = $this->createCachePool();

        if (!$cache instanceof ContextAwareAdapterInterface) {
            $this->markTestSkipped('ContextAwareAdapterInterface not implemented.');
        }

        $item = $cache->getItem('foo');
        $cache->save($item->set('foo'));

        $fork = $cache->withContext('ns');
        $item = $fork->getItem('foo');
        $this->assertFalse($item->isHit());

        $fork->save($item->set('bar'));
        $item = $cache->getItem('bar');
        $this->assertFalse($item->isHit());

        $fork = $cache->withContext('ns');
        $item = $fork->getItem('foo');
        $this->assertTrue($item->isHit());

        $cache->clear();
        $item = $fork->getItem('foo');
        $this->assertFalse($item->isHit());
    }

    /**
     * @expectedException \Psr\Cache\InvalidArgumentException
     * @dataProvider invalidKeys
     */
    public function testBadContext($context)
    {
        if (isset($this->skippedTests[__FUNCTION__])) {
            $this->markTestSkipped($this->skippedTests[__FUNCTION__]);

            return;
        }
        $cache = $this->createCachePool();

        if (!$cache instanceof ContextAwareAdapterInterface) {
            $this->markTestSkipped('ContextAwareAdapterInterface not implemented.');
        }

        $cache->withContext($context);
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
