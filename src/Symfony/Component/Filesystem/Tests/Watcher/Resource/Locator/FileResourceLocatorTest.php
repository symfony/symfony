<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Filesystem\Tests\Watcher\Resource\Locator;

use Symfony\Component\Filesystem\Tests\FilesystemTestCase;
use Symfony\Component\Filesystem\Watcher\Resource\ArrayResource;
use Symfony\Component\Filesystem\Watcher\Resource\DirectoryResource;
use Symfony\Component\Filesystem\Watcher\Resource\FileResource;
use Symfony\Component\Filesystem\Watcher\Resource\Locator\FileResourceLocator;

class FileResourceLocatorTest extends FilesystemTestCase
{
    public function testLocateIterator()
    {
        $locator = new FileResourceLocator();

        $path = new \ArrayIterator(array($this->createFile('foo.txt')));

        $this->assertEquals(new ArrayResource(array(new FileResource($this->workspace.'/foo.txt'))), $locator->locate($path));
    }

    public function testLocateSplFileInfo()
    {
        $locator = new FileResourceLocator();

        $path = new \SplFileInfo($this->createFile('foo.txt'));

        $this->assertEquals(new FileResource($this->workspace.'/foo.txt'), $locator->locate($path));
    }

    public function testFilePath()
    {
        $locator = new FileResourceLocator();

        $path = $this->createFile('foo.txt');

        $this->assertEquals(new FileResource($this->workspace.'/foo.txt'), $locator->locate($path));
    }

    public function testGlob()
    {
        $locator = new FileResourceLocator();

        $this->createFile('bar.txt');
        $this->createFile('foo.txt');

        $this->assertEquals(
            new ArrayResource(array(new FileResource($this->workspace.'/bar.txt'), new FileResource($this->workspace.'/foo.txt'))),
            $locator->locate($this->workspace.'/*.txt')
        );
    }

    public function testArray()
    {
        $locator = new FileResourceLocator();

        $path = array($this->createFile('foo.txt'));

        $this->assertEquals(new ArrayResource(array(new FileResource($this->workspace.'/foo.txt'))), $locator->locate($path));
    }

    public function testDirectory()
    {
        $locator = new FileResourceLocator();

        $dir = $this->createDirecty('foobar');

        $this->assertEquals(new DirectoryResource($this->workspace.'/foobar'), $locator->locate($dir));
    }

    private function createFile(string $file)
    {
        $fullPath = $this->workspace.'/'.$file;
        touch($fullPath);

        return $fullPath;
    }

    private function createDirecty(string $dir)
    {
        $fullPath = $this->workspace.'/'.$dir;

        mkdir($fullPath, 0777, true);

        return $fullPath;
    }
}
