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

use Symfony\Component\HttpKernel\Profiler\FileProfilerStorage;

class FileProfilerStorageTest extends AbstractProfilerStorageTest
{
    protected static $tmpDir;
    protected static $storage;

    protected static function cleanDir()
    {
        $flags = \FilesystemIterator::SKIP_DOTS;
        $iterator = new \RecursiveDirectoryIterator(self::$tmpDir, $flags);
        $iterator = new \RecursiveIteratorIterator($iterator, \RecursiveIteratorIterator::SELF_FIRST);

        foreach ($iterator as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
    }

    public static function setUpBeforeClass()
    {
        self::$tmpDir = sys_get_temp_dir() . '/sf2_profiler_file_storage';
        if (is_dir(self::$tmpDir)) {
            self::cleanDir();
        }
        self::$storage = new FileProfilerStorage('file:'.self::$tmpDir);
    }

    public static function tearDownAfterClass()
    {
        self::cleanDir();
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
