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

abstract class CacheTestCase extends SimpleCacheTest
{
    public static function validKeys()
    {
        if (defined('HHVM_VERSION')) {
            return parent::validKeys();
        }

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
