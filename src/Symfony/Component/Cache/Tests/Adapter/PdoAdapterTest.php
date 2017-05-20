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

use Symfony\Component\Cache\Adapter\PdoAdapter;

/**
 * @group time-sensitive
 */
class PdoAdapterTest extends AdapterTestCase
{
    protected static $dbFile;

    public static function setupBeforeClass()
    {
        if (!extension_loaded('pdo_sqlite')) {
            self::markTestSkipped('Extension pdo_sqlite required.');
        }

        self::$dbFile = tempnam(sys_get_temp_dir(), 'sf_sqlite_cache');

        $pool = new PdoAdapter('sqlite:'.self::$dbFile);
        $pool->createTable();
    }

    public static function tearDownAfterClass()
    {
        @unlink(self::$dbFile);
    }

    public function createCachePool($defaultLifetime = 0)
    {
        return new PdoAdapter('sqlite:'.self::$dbFile, 'ns', $defaultLifetime);
    }
}
