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

use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Version;
use Psr\SimpleCache\CacheInterface;
use Symfony\Component\Cache\Simple\PdoCache;
use Symfony\Component\Cache\Tests\Traits\PdoPruneableTrait;

/**
 * @group time-sensitive
 * @group legacy
 */
class PdoDbalCacheTest extends CacheTestCase
{
    use PdoPruneableTrait;

    protected static $dbFile;

    public static function setUpBeforeClass(): void
    {
        if (!\extension_loaded('pdo_sqlite')) {
            self::markTestSkipped('Extension pdo_sqlite required.');
        }

        if (\PHP_VERSION_ID >= 80000 && class_exists(Version::class)) {
            self::markTestSkipped('Doctrine DBAL 2.x is incompatible with PHP 8.');
        }

        self::$dbFile = tempnam(sys_get_temp_dir(), 'sf_sqlite_cache');

        $pool = new PdoCache(DriverManager::getConnection(['driver' => 'pdo_sqlite', 'path' => self::$dbFile]));
        $pool->createTable();
    }

    public static function tearDownAfterClass(): void
    {
        @unlink(self::$dbFile);
    }

    public function createSimpleCache(int $defaultLifetime = 0): CacheInterface
    {
        return new PdoCache(DriverManager::getConnection(['driver' => 'pdo_sqlite', 'path' => self::$dbFile]), '', $defaultLifetime);
    }
}
