<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Config\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\ConfigCache;
use Symfony\Component\Config\Tests\Resource\ResourceStub;

class ConfigCacheTest extends TestCase
{
    private $cacheFile = null;

    protected function setUp(): void
    {
        $this->cacheFile = tempnam(sys_get_temp_dir(), 'config_');
    }

    protected function tearDown(): void
    {
        $files = array($this->cacheFile, $this->cacheFile.'.meta');

        foreach ($files as $file) {
            if (file_exists($file)) {
                unlink($file);
            }
        }
    }

    /**
     * @dataProvider debugModes
     */
    public function testCacheIsNotValidIfNothingHasBeenCached($debug): void
    {
        unlink($this->cacheFile); // remove tempnam() side effect
        $cache = new ConfigCache($this->cacheFile, $debug);

        $this->assertFalse($cache->isFresh());
    }

    public function testIsAlwaysFreshInProduction(): void
    {
        $staleResource = new ResourceStub();
        $staleResource->setFresh(false);

        $cache = new ConfigCache($this->cacheFile, false);
        $cache->write('', array($staleResource));

        $this->assertTrue($cache->isFresh());
    }

    /**
     * @dataProvider debugModes
     */
    public function testIsFreshWhenNoResourceProvided($debug): void
    {
        $cache = new ConfigCache($this->cacheFile, $debug);
        $cache->write('', array());
        $this->assertTrue($cache->isFresh());
    }

    public function testFreshResourceInDebug(): void
    {
        $freshResource = new ResourceStub();
        $freshResource->setFresh(true);

        $cache = new ConfigCache($this->cacheFile, true);
        $cache->write('', array($freshResource));

        $this->assertTrue($cache->isFresh());
    }

    public function testStaleResourceInDebug(): void
    {
        $staleResource = new ResourceStub();
        $staleResource->setFresh(false);

        $cache = new ConfigCache($this->cacheFile, true);
        $cache->write('', array($staleResource));

        $this->assertFalse($cache->isFresh());
    }

    public function debugModes()
    {
        return array(
            array(true),
            array(false),
        );
    }
}
