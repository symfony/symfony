<?php

namespace Symfony\Tests\Component\Cache;

use Symfony\Component\Cache\ApcCache;

/**
 * @group GH-1513
 */
class ApcCacheTest extends \PHPUnit_Framework_TestCase
{
    private $cache;

    public function setUp()
    {
        if (!extension_loaded('apc') || !ini_get('apc.enable_cli')) {
            $this->markTestSkipped('APC needs to be installed for this tests.');
        }
        $this->cache = new ApcCache();
    }

    public function testSaveReturnsTrueOnSuccess()
    {
        $ret = $this->cache->save('id', 1);

        $this->assertTrue($ret);
    }

    public function testFetchUnknownReturnsFalse()
    {
        $value = $this->cache->fetch('unknown');
        $this->assertFalse($value);
    }

    public function testFetchKnownReturnsValue()
    {
        $this->cache->save('known', 1);
        $value = $this->cache->fetch('known');

        $this->assertSame(1, $value);
    }

    public function testFetchKnownFalse()
    {
        $this->cache->save('false', false);
        $value = $this->cache->fetch('false');

        $this->assertFalse($value);
    }

    public function testContainsFalseValue()
    {
        $this->cache->save('contains_false', false);

        $this->assertTrue($this->cache->contains('contains_false'));
    }

    public function testContainsValue()
    {
        $this->cache->save('contains', 1234);

        $this->assertTrue($this->cache->contains('contains'));
    }

    public function testDelete()
    {
        $this->cache->save('delete', 1234);

        $this->assertTrue($this->cache->contains('delete'));

        $this->cache->delete('delete');
        $this->assertFalse($this->cache->contains('delete'));
    }
}

