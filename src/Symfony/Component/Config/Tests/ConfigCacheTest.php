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
use Symfony\Component\Config\Resource\FileResource;
use Symfony\Component\Config\Resource\SelfCheckingResourceChecker;
use Symfony\Component\Config\Tests\Resource\ResourceStub;

class ConfigCacheTest extends TestCase
{
    private string $cacheFile;

    protected function setUp(): void
    {
        $this->cacheFile = tempnam(sys_get_temp_dir(), 'config_');
    }

    protected function tearDown(): void
    {
        $files = [$this->cacheFile, $this->cacheFile.'.meta'];

        foreach ($files as $file) {
            if (file_exists($file)) {
                @unlink($file);
            }
        }
    }

    /**
     * @dataProvider debugModes
     */
    public function testCacheIsNotValidIfNothingHasBeenCached(bool $debug)
    {
        unlink($this->cacheFile); // remove tempnam() side effect
        $cache = new ConfigCache($this->cacheFile, $debug);

        $this->assertFalse($cache->isFresh());
    }

    public function testIsAlwaysFreshInProduction()
    {
        $staleResource = new ResourceStub();
        $staleResource->setFresh(false);

        $cache = new ConfigCache($this->cacheFile, false);
        $cache->write('', [$staleResource]);

        $this->assertTrue($cache->isFresh());
    }

    /**
     * @dataProvider debugModes
     */
    public function testIsFreshWhenNoResourceProvided(bool $debug)
    {
        $cache = new ConfigCache($this->cacheFile, $debug);
        $cache->write('', []);
        $this->assertTrue($cache->isFresh());
    }

    public function testFreshResourceInDebug()
    {
        $p = (new \ReflectionClass(SelfCheckingResourceChecker::class))->getProperty('cache');
        $p->setValue(null, []);

        $freshResource = new ResourceStub();
        $freshResource->setFresh(true);

        $cache = new ConfigCache($this->cacheFile, true);
        $cache->write('', [$freshResource]);

        $this->assertTrue($cache->isFresh());
    }

    public function testStaleResourceInDebug()
    {
        $p = (new \ReflectionClass(SelfCheckingResourceChecker::class))->getProperty('cache');
        $p->setValue(null, []);

        $staleResource = new ResourceStub();
        $staleResource->setFresh(false);

        $cache = new ConfigCache($this->cacheFile, true);
        $cache->write('', [$staleResource]);

        $this->assertFalse($cache->isFresh());
    }

    public function testIsNotFreshWhenResourceChangesWhileCacheIsBeingBuilt()
    {
        $p = (new \ReflectionClass(SelfCheckingResourceChecker::class))->getProperty('cache');
        $p->setValue(null, []);

        $resourceFile = tempnam(sys_get_temp_dir(), 'resource_');
        file_put_contents($resourceFile, 'v1');

        $cache = new ConfigCache($this->cacheFile, true);

        $content = file_get_contents($resourceFile); // the build reads the resource
        file_put_contents($resourceFile, 'v2'); // an editor saves the file while the build still runs
        clearstatcache();

        $cache->write('<?php return '.var_export($content, true).';', [new FileResource($resourceFile)]);

        // The resource and the cache file were written in the same second;
        // force that condition so the test does not depend on wall-clock timing.
        touch($this->cacheFile, filemtime($resourceFile));
        clearstatcache();

        try {
            $this->assertSame('v1', require $cache->getPath());
            $this->assertFalse($cache->isFresh(), '->isFresh() returns false when a resource changed while the cache was being built');
        } finally {
            @unlink($resourceFile);
        }
    }

    public static function debugModes(): array
    {
        return [
            [true],
            [false],
        ];
    }
}
