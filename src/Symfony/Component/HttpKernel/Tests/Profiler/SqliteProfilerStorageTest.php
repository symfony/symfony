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
 * @group legacy
 */
class SqliteProfilerStorageTest extends AbstractProfilerStorageTest
{
    private $dbFile;
    private $storage;

    protected function setUp()
    {
        if (!class_exists('SQLite3') && (!class_exists('PDO') || !in_array('sqlite', \PDO::getAvailableDrivers()))) {
            $this->markTestSkipped('This test requires SQLite support in your environment');
        }

        $this->dbFile = tempnam(sys_get_temp_dir(), 'sf2_sqlite_storage');
        if (file_exists($this->dbFile)) {
            @unlink($this->dbFile);
        }
        $this->storage = new SqliteProfilerStorage('sqlite:'.$this->dbFile);

        $this->storage->purge();
    }

    protected function tearDown()
    {
        @unlink($this->dbFile);
    }

    /**
     * @return \Symfony\Component\HttpKernel\Profiler\ProfilerStorageInterface
     */
    protected function getStorage()
    {
        return $this->storage;
    }
}
