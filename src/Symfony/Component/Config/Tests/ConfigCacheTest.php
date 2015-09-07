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

    /**
     * @dataProvider debugModes
     */
    public function testCacheIsValidIfNoValidatorProvided($debug)
    {
        /* For example in prod mode, you may choose not to run any validators
           at all. In that case, the cache should always be considered fresh. */
        $cache = new ConfigCache($this->cacheFile, $debug);
        $this->assertTrue($cache->isValid(array()));
    }

    /**
     * @dataProvider debugModes
     */
    public function testResourcesWithoutValidatorsAreIgnoredAndConsideredFresh($debug)
    {
        /* As in the previous test, but this time we have a resource. */
        $cache = new ConfigCache($this->cacheFile, true);
        $cache->write('', array(new ResourceStub()));

        $this->assertTrue($cache->isValid(array())); // no (matching) MetadataValidator passed
    }

    /**
     * @dataProvider debugModes
     */
    public function testCacheIsNotValidIfNothingHasBeenCached($debug)
    {
        $validator = $this->getMock('\Symfony\Component\Config\MetadataValidatorInterface')
            ->expects($this->never())->method('supports');

        /* If there is nothing in the cache, it needs to be filled (and thus it's not fresh).
            It does not matter if you provide validators or not. */

        unlink($this->cacheFile); // remove tempnam() side effect
        $cache = new ConfigCache($this->cacheFile, $debug);

        $this->assertFalse($cache->isValid(array($validator)));
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
        $validator = $this->getMock('\Symfony\Component\Config\MetadataValidatorInterface');

        $validator->expects($this->once())
                  ->method('supports')
                  ->willReturn(true);

        $validator->expects($this->once())
                  ->method('isFresh')
                  ->willReturn(true);

        $cache = new ConfigCache($this->cacheFile, $debug);
        $cache->write('', array(new ResourceStub()));

        $this->assertTrue($cache->isValid(array($validator)));
    }

    /**
     * @dataProvider debugModes
     */
    public function testIsNotValidWithStaleResource($debug)
    {
        $validator = $this->getMock('\Symfony\Component\Config\MetadataValidatorInterface');

        $validator->expects($this->once())
                  ->method('supports')
                  ->willReturn(true);

        $validator->expects($this->once())
                  ->method('isFresh')
                  ->willReturn(false);

        $cache = new ConfigCache($this->cacheFile, $debug);
        $cache->write('', array(new ResourceStub()));

        $this->assertFalse($cache->isValid(array($validator)));
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
            array(false),
        );
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
