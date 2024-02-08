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

    public static function debugModes(): array
    {
        return [
            [true],
            [false],
        ];
    }
}
