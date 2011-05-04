<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Tests\Component\HttpFoundation\File;

use Symfony\Component\HttpFoundation\File\TemporaryStorage;

class TemporaryStorageTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @expectedException Symfony\Component\HttpFoundation\File\Exception\DirectoryCreationException
     */
    public function testThrowsAnExceptionWhenUnableToCreateTheDirectory()
    {
        $storage = new TemporaryStorage('secret', __DIR__.'/Fixtures/test.gif');
    }

    /**
     * @expectedException Symfony\Component\HttpFoundation\File\Exception\UnexpectedTypeException
     */
    public function testGetTempDirThrowsAnExceptionIfTokenIsNotAString()
    {
        $storage = new TemporaryStorage('secret', __DIR__.'/Fixtures/storage');
        $storage->getTempDir(array());
    }

    public function testDoNotTruncateWhenSizeIsZero()
    {
        $path = __DIR__.'/Fixtures/storage';
        $storage = new TemporaryStorage('secret', $path);
        $file = new \SplFileObject($path.'/foo', 'w');
        $file->fwrite("foobar");

        $this->assertTrue(file_exists($path.'/foo'));

        unlink($file);
    }

    public function testTruncateFilesWhenSizeIsExceeded()
    {
        $path = __DIR__.'/Fixtures/storage';

        $files = array(
            $path.'/sub1/foo_1',
            $path.'/sub1/foo_2',
            $path.'/sub2/foo_3',
            $path.'/sub2/foo_4',
        );

        foreach ($files as $i => $file) {
            $file = new \SplFileObject($file, 'w');
            $size = $file->fwrite("foobar");
            if ($i % 2) {
                touch($file, time() - 500);
            }
        }

        $storage = new TemporaryStorage('secret', $path, 2 * $size);

        foreach ($files as $i => $file) {
            if ($i % 2) {
                $this->assertFalse(file_exists($file));
            } else {
                $this->assertTrue(file_exists($file));
                unlink($file);
            }
        }
    }

    public function testDoNotTruncateWhenSizeIsNotExceeded()
    {
        $path = __DIR__.'/Fixtures/storage';

        $files = array(
            $path.'/sub1/foo_1',
            $path.'/sub1/foo_2',
            $path.'/sub2/foo_3',
            $path.'/sub2/foo_4',
        );

        foreach ($files as $i => $file) {
            $file = new \SplFileObject($file, 'w');
            $size = $file->fwrite("foobar");
        }

        $storage = new TemporaryStorage('secret', $path, 4 * $size);

        foreach ($files as $i => $file) {
            $this->assertTrue(file_exists($file));
            unlink($file);
        }
    }

    public function testTruncateOldFiles()
    {
        $path = __DIR__.'/Fixtures/storage';

        $files = array(
            $path.'/sub1/foo_1',
            $path.'/sub1/foo_2',
            $path.'/sub2/foo_3',
            $path.'/sub2/foo_4',
        );

        foreach ($files as $i => $file) {
            $file = new \SplFileObject($file, 'w');
            $size = $file->fwrite("foobar");
            if ($i % 2) {
                touch($file, time() - 35 * 60);
            }
        }

        $storage = new TemporaryStorage('secret', $path, 0, 30);

        foreach ($files as $i => $file) {
            if ($i % 2) {
                $this->assertFalse(file_exists($file));
            } else {
                $this->assertTrue(file_exists($file));
                unlink($file);
            }
        }
    }
}