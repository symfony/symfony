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

use Symfony\Component\Config\ConfigCache;
use Symfony\Component\Config\Resource\ResourceInterface;
use Symfony\Component\Config\Resource\ResourceValidator;

class ConfigCacheTest extends \PHPUnit_Framework_TestCase
{
    private $cacheFile = null;

    protected function setUp()
    {
        $this->cacheFile = tempnam(sys_get_temp_dir(), 'config_');
    }

    protected function tearDown()
    {
        $files = array($this->cacheFile, "{$this->cacheFile}.meta");

        foreach ($files as $file) {
            if (file_exists($file)) {
                unlink($file);
            }
        }
    }

    public function testGetPath()
    {
        $cache = new ConfigCache($this->cacheFile, true);

        $this->assertSame($this->cacheFile, $cache->getPath());
    }

    public function testCacheIsNotValidIfNothingHasBeenCached()
    {
        unlink($this->cacheFile); // remove tempnam() side effect
        $cache = new ConfigCache($this->cacheFile, false);

        $this->assertFalse($this->isCacheValid($cache));
    }

    /**
     * @group legacy
     */
    public function testIsFreshAlwaysReturnsTrueInProduction()
    {
        $staleResource = new ResourceStub();
        $staleResource->setFresh(false);

        $cache = new ConfigCache($this->cacheFile, false);
        $cache->write('', array($staleResource));

        $this->assertTrue($cache->isFresh());
    }

    /**
     * @group legacy
     */
    public function testIsFreshWithFreshResourceInDebug()
    {
        $freshResource = new ResourceStub();
        $freshResource->setFresh(true);

        $cache = new ConfigCache($this->cacheFile, true);
        $cache->write('', array($freshResource));

        $this->assertTrue($cache->isFresh());
    }

    /**
     * @group legacy
     */
    public function testIsNotFreshWithStaleResourceInDebug()
    {
        $staleResource = new ResourceStub();
        $staleResource->setFresh(false);

        $cache = new ConfigCache($this->cacheFile, true);
        $cache->write('', array($staleResource));

        $this->assertFalse($cache->isFresh());
    }

    /**
     * @dataProvider debugModes
     */
    public function testIsValidWithFreshResource($debug)
    {
        $freshResource = new ResourceStub();
        $freshResource->setFresh(true);

        $cache = new ConfigCache($this->cacheFile, $debug);
        $cache->write('', array($freshResource));

        $this->assertTrue($this->isCacheValid($cache));
    }

    /**
     * @dataProvider debugModes
     */
    public function testIsNotValidWithStaleResource($debug)
    {
        $staleResource = new ResourceStub();
        $staleResource->setFresh(true);

        $cache = new ConfigCache($this->cacheFile, $debug);
        $cache->write('', array($staleResource));

        $this->assertTrue($this->isCacheValid($cache));
    }

    public function testResourcesWithoutValidatorsAreIgnoredAndConsideredFresh()
    {
        $staleResource = new ResourceStub();
        $staleResource->setFresh(false);

        $cache = new ConfigCache($this->cacheFile, true);
        $cache->write('', array($staleResource));

        $this->assertTrue($cache->isValid(array())); // no (matching) MetadataValidator passed
    }

    public function testCacheKeepsContent()
    {
        $cache = new ConfigCache($this->cacheFile, false);
        $cache->write('FOOBAR');

        $this->assertSame('FOOBAR', file_get_contents($cache->getPath()));
    }

    public function debugModes()
    {
        return array(
            array(true),
            array(false)
        );
    }

    private function isCacheValid(ConfigCache $cache)
    {
        return $cache->isValid(array(new ResourceValidator()));
    }
}

class ResourceStub implements ResourceInterface {
    private $fresh = true;

    public function setFresh($isFresh)
    {
        $this->fresh = $isFresh;
    }

    public function __toString() {
        return 'stub';
    }

    public function isFresh($timestamp)
    {
        return $this->fresh;
    }

    public function getResource()
    {
        return 'stub';
    }
}
