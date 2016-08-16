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
use Symfony\Component\HttpKernel\Profiler\Profile;

class FileProfilerStorageTest extends AbstractProfilerStorageTest
{
    private $tmpDir;
    private $storage;

    protected function cleanDir()
    {
        $flags = \FilesystemIterator::SKIP_DOTS;
        $iterator = new \RecursiveDirectoryIterator($this->tmpDir, $flags);
        $iterator = new \RecursiveIteratorIterator($iterator, \RecursiveIteratorIterator::SELF_FIRST);

        foreach ($iterator as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
    }

    protected function setUp()
    {
        $this->tmpDir = sys_get_temp_dir().'/sf2_profiler_file_storage';
        if (is_dir($this->tmpDir)) {
            self::cleanDir();
        }
        $this->storage = new FileProfilerStorage('file:'.$this->tmpDir);
        $this->storage->purge();
    }

    protected function tearDown()
    {
        self::cleanDir();
    }

    /**
     * @return \Symfony\Component\HttpKernel\Profiler\ProfilerStorageInterface
     */
    protected function getStorage()
    {
        return $this->storage;
    }

    public function testMultiRowIndexFile()
    {
        $iteration = 3;
        for ($i = 0; $i < $iteration; ++$i) {
            $profile = new Profile('token'.$i);
            $profile->setIp('127.0.0.'.$i);
            $profile->setUrl('http://foo.bar/'.$i);
            $storage = $this->getStorage();

            $storage->write($profile);
            $storage->write($profile);
            $storage->write($profile);
        }

        $handle = fopen($this->tmpDir.'/index.csv', 'r');
        for ($i = 0; $i < $iteration; ++$i) {
            $row = fgetcsv($handle);
            $this->assertEquals('token'.$i, $row[0]);
            $this->assertEquals('127.0.0.'.$i, $row[1]);
            $this->assertEquals('http://foo.bar/'.$i, $row[3]);
        }
        $this->assertFalse(fgetcsv($handle));
    }

    public function testReadLineFromFile()
    {
        $r = new \ReflectionMethod($this->storage, 'readLineFromFile');

        $r->setAccessible(true);

        $h = tmpfile();

        fwrite($h, "line1\n\n\nline2\n");
        fseek($h, 0, SEEK_END);

        $this->assertEquals('line2', $r->invoke($this->storage, $h));
        $this->assertEquals('line1', $r->invoke($this->storage, $h));
    }
}
