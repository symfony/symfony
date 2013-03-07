<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Config\Tests\Resource;

use Symfony\Component\Config\Resource\DirectoryResource;

class DirectoryResourceTest extends \PHPUnit_Framework_TestCase
{
    protected $directory;

    protected function touch($file, $reltime = 0)
    {
        touch($file, time() + $reltime);
        clearstatcache($file);
    }

    protected function setUp()
    {
        $this->directory = sys_get_temp_dir().'/DirectoryResourceTest';
        if (!file_exists($this->directory)) {
            mkdir($this->directory);
        }
        $this->touch($this->directory.'/tmp.xml', -86410);
        $this->touch($this->directory, -86420); // touch dir afterwards because will be updated when creating file
    }

    protected function tearDown()
    {
        if (!is_dir($this->directory)) {
            return;
        }
        $this->removeDirectory($this->directory);
    }

    protected function removeDirectory($directory)
    {
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($directory), \RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($iterator as $path) {
            if (preg_match('#[/\\\\]\.\.?$#', $path->__toString())) {
                continue;
            }
            if ($path->isDir()) {
               rmdir($path->__toString());
            } else {
               unlink($path->__toString());
            }
        }
        rmdir($directory);
    }

    public function testGetPattern()
    {
        $resource = new DirectoryResource('foo', 'bar');
        $this->assertEquals('bar', $resource->getPattern());
    }

    /**
     * @covers Symfony\Component\Config\Resource\DirectoryResource::isFresh
     */
    public function testIsFresh()
    {
        $resource = new DirectoryResource($this->directory);
        $this->assertTrue($resource->isFresh(), '->isFresh() returns true if the resource has not changed');

        $this->touch($this->directory.'/tmp2.xml', 20);
        $this->assertFalse($resource->isFresh(), '->isFresh() returns false if the resource has been updated');

        /*
         * A DirectoryResource for a nonexistent directory will stay fresh as long as the directory
         * does not exist.
         *
         * For clients of ConfigCache, that's the right way to say "I checked that directory when I built
         * the cache's contents. As long as nobody creates this dir and puts something in it that matches
         * my pattern, the cache is valid.".
         *
         */
        $dir = $this->directory.'/subdir';
        $resource = new DirectoryResource($dir);
        $this->assertTrue($resource->isFresh(), '->isFresh() returns true if the resource does not exist');
        mkdir($dir);
        $this->assertTrue($resource->isFresh(), '->isFresh() returns true if a directory is created but does not contain anything');
        $this->touch($dir."/foo.xml", 20);
        $this->assertFalse($resource->isFresh(), '->isFresh() returns false if files become available in a previously non-existing directory');
    }

    /**
     * @covers Symfony\Component\Config\Resource\DirectoryResource::isFresh
     */
    public function testIsFreshUpdateFile()
    {
        $resource = new DirectoryResource($this->directory);
        $this->touch($this->directory.'/tmp.xml', 20);
        $this->assertFalse($resource->isFresh(), '->isFresh() returns false if an existing file is modified');
    }

    /**
     * @covers Symfony\Component\Config\Resource\DirectoryResource::isFresh
     */
    public function testIsFreshNewFile()
    {
        $resource = new DirectoryResource($this->directory);
        $this->touch($this->directory.'/new.xml', 20);
        $this->assertFalse($resource->isFresh(), '->isFresh() returns false if a new file is added');
    }

    /**
     * @covers Symfony\Component\Config\Resource\DirectoryResource::isFresh
     */
    public function testIsFreshDeleteFile()
    {
        $resource = new DirectoryResource($this->directory);
        unlink($this->directory.'/tmp.xml');
        $this->assertFalse($resource->isFresh(), '->isFresh() returns false if an existing file is removed');
    }

    /**
     * @covers Symfony\Component\Config\Resource\DirectoryResource::isFresh
     */
    public function testIsFreshDeleteDirectory()
    {
        $resource = new DirectoryResource($this->directory);
        $this->removeDirectory($this->directory);
        $this->assertFalse($resource->isFresh(), '->isFresh() returns false if the whole resource is removed');
    }

    /**
     * @covers Symfony\Component\Config\Resource\DirectoryResource::isFresh
     */
    public function testIsFreshCreateFileInSubdirectory()
    {
        $subdirectory = $this->directory.'/subdirectory';
        mkdir($subdirectory);

        $resource = new DirectoryResource($this->directory);
        $this->assertTrue($resource->isFresh(), '->isFresh() returns true if an unmodified subdirectory exists');

        $this->touch($subdirectory.'/newfile.xml', 20);
        $this->assertFalse($resource->isFresh(), '->isFresh() returns false if a new file in a subdirectory is added');
    }

    /**
     * @covers Symfony\Component\Config\Resource\DirectoryResource::isFresh
     */
    public function testIsFreshModifySubdirectory()
    {
        $resource = new DirectoryResource($this->directory);

        $subdirectory = $this->directory.'/subdirectory';
        mkdir($subdirectory);
        $this->touch($subdirectory.'/newfile.xml', 20);

        $this->assertFalse($resource->isFresh(), '->isFresh() returns false if a subdirectory is modified (e.g. a file is added)');

        unlink($subdirectory.'/newfile.xml');

        $this->assertTrue($resource->isFresh(), '->isFresh() returns true if a subdirectory is reverted to its previous state');
    }

    /**
     * @covers Symfony\Component\Config\Resource\DirectoryResource::isFresh
     */
    public function testFilterRegexListNoMatch()
    {
        $resource = new DirectoryResource($this->directory, '/\.(foo|xml)$/');

        $this->touch($this->directory.'/new.bar', 20);
        $this->assertTrue($resource->isFresh(), '->isFresh() returns true if a new file not matching the filter regex is created');
    }

    /**
     * @covers Symfony\Component\Config\Resource\DirectoryResource::isFresh
     */
    public function testFilterRegexListMatch()
    {
        $resource = new DirectoryResource($this->directory, '/\.(foo|xml)$/');

        $this->touch($this->directory.'/new.xml', 20);
        $this->assertFalse($resource->isFresh(), '->isFresh() returns false if an new file matching the filter regex is created ');
    }
}
