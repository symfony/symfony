<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Cache\Tests\Simple;

use Cache\IntegrationTests\SimpleCacheTest;
use Psr\SimpleCache\CacheInterface;
use Symfony\Component\Cache\PruneableInterface;

abstract class CacheTestCase extends SimpleCacheTest
{
    protected function setUp()
    {
        parent::setUp();

        if (!array_key_exists('testPrune', $this->skippedTests) && !$this->createSimpleCache() instanceof PruneableInterface) {
            $this->skippedTests['testPrune'] = 'Not a pruneable cache pool.';
        }
    }

    public static function validKeys()
    {
        return array_merge(parent::validKeys(), array(array("a\0b")));
    }

    public function testDefaultLifeTime()
    {
        if (isset($this->skippedTests[__FUNCTION__])) {
            $this->markTestSkipped($this->skippedTests[__FUNCTION__]);
        }

        $cache = $this->createSimpleCache(2);

        $cache->set('key.dlt', 'value');
        sleep(1);

        $this->assertSame('value', $cache->get('key.dlt'));

        sleep(2);
        $this->assertNull($cache->get('key.dlt'));
    }

    public function testNotUnserializable()
    {
        if (isset($this->skippedTests[__FUNCTION__])) {
            $this->markTestSkipped($this->skippedTests[__FUNCTION__]);
        }

        $cache = $this->createSimpleCache();

        $cache->set('foo', new NotUnserializable());

        $this->assertNull($cache->get('foo'));

        $cache->setMultiple(array('foo' => new NotUnserializable()));

        foreach ($cache->getMultiple(array('foo')) as $value) {
        }
        $this->assertNull($value);
    }

    public function testPrune()
    {
        if (isset($this->skippedTests[__FUNCTION__])) {
            $this->markTestSkipped($this->skippedTests[__FUNCTION__]);
        }

        if (!method_exists($this, 'isPruned')) {
            $this->fail('Test classes for pruneable caches must implement `isPruned($cache, $name)` method.');
        }

        /** @var PruneableInterface|CacheInterface $cache */
        $cache = $this->createSimpleCache();

        $cache->set('foo', 'foo-val', new \DateInterval('PT05S'));
        $cache->set('bar', 'bar-val', new \DateInterval('PT10S'));
        $cache->set('baz', 'baz-val', new \DateInterval('PT15S'));
        $cache->set('qux', 'qux-val', new \DateInterval('PT20S'));

        sleep(30);
        $cache->prune();
        $this->assertTrue($this->isPruned($cache, 'foo'));
        $this->assertTrue($this->isPruned($cache, 'bar'));
        $this->assertTrue($this->isPruned($cache, 'baz'));
        $this->assertTrue($this->isPruned($cache, 'qux'));

        $cache->set('foo', 'foo-val');
        $cache->set('bar', 'bar-val', new \DateInterval('PT20S'));
        $cache->set('baz', 'baz-val', new \DateInterval('PT40S'));
        $cache->set('qux', 'qux-val', new \DateInterval('PT80S'));

        $cache->prune();
        $this->assertFalse($this->isPruned($cache, 'foo'));
        $this->assertFalse($this->isPruned($cache, 'bar'));
        $this->assertFalse($this->isPruned($cache, 'baz'));
        $this->assertFalse($this->isPruned($cache, 'qux'));

        sleep(30);
        $cache->prune();
        $this->assertFalse($this->isPruned($cache, 'foo'));
        $this->assertTrue($this->isPruned($cache, 'bar'));
        $this->assertFalse($this->isPruned($cache, 'baz'));
        $this->assertFalse($this->isPruned($cache, 'qux'));

        sleep(30);
        $cache->prune();
        $this->assertFalse($this->isPruned($cache, 'foo'));
        $this->assertTrue($this->isPruned($cache, 'baz'));
        $this->assertFalse($this->isPruned($cache, 'qux'));

        sleep(30);
        $cache->prune();
        $this->assertFalse($this->isPruned($cache, 'foo'));
        $this->assertTrue($this->isPruned($cache, 'qux'));
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
