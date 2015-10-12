<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\Tests\Profiler;

use Symfony\Component\HttpKernel\Profiler\SqliteProfilerStorage;

/**
 * @requires extension pdo_sqlite
 */
class SqliteProfilerStorageTest extends AbstractProfilerStorageTest
{
    protected static $dbFile;
    protected static $storage;

    public static function setUpBeforeClass()
    {
        self::$dbFile = tempnam(sys_get_temp_dir(), 'sf2_sqlite_storage');
        if (file_exists(self::$dbFile)) {
            @unlink(self::$dbFile);
        }
        self::$storage = new SqliteProfilerStorage('sqlite:'.self::$dbFile);
    }

    public static function tearDownAfterClass()
    {
        @unlink(self::$dbFile);
    }

    protected function setUp()
    {
        self::$storage->purge();
    }

    /**
     * @return \Symfony\Component\HttpKernel\Profiler\ProfilerStorageInterface
     */
    protected function getStorage()
    {
        return self::$storage;
    }
}
