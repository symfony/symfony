<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Tests\Component\Config\Resource;

use Symfony\Component\Config\Resource\DirectoryResource;

class DirectoryResourceTest extends \PHPUnit_Framework_TestCase
{
    protected $resource;
    protected $directory;

    protected function setUp()
    {
        $this->directory = sys_get_temp_dir().'/symfonyDirectoryIterator';
        if (!file_exists($this->directory)) {
            mkdir($this->directory);
        }
        touch($this->directory.'/tmp.xml');
        $this->resource = new DirectoryResource($this->directory);
    }

    protected function tearDown()
    {
        if (!is_dir($this->directory)) {
            return;
        }
        $this->removeDirectory($this->directory);
    }

    protected function removeDirectory($directory) {
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($directory), \RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($iterator as $path) {
            if (preg_match('#/\.\.?$#', $path->__toString())) {
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

    /**
     * @covers Symfony\Component\Config\Resource\DirectoryResource::getResource
     */
    public function testGetResource()
    {
        $this->assertEquals($this->directory, $this->resource->getResource(), '->getResource() returns the path to the resource');
    }

    /**
     * @covers Symfony\Component\Config\Resource\DirectoryResource::isFresh
     */
    public function testIsFresh()
    {
        $this->assertTrue($this->resource->isFresh(time() + 10), '->isFresh() returns true if the resource has not changed');
        $this->assertFalse($this->resource->isFresh(time() - 86400), '->isFresh() returns false if the resource has been updated');

        $resource = new DirectoryResource('/____foo/foobar'.rand(1, 999999));
        $this->assertFalse($resource->isFresh(time()), '->isFresh() returns false if the resource does not exist');
    }

    /**
     * @covers Symfony\Component\Config\Resource\DirectoryResource::isFresh
     */
    public function testIsFreshUpdateFile()
    {
        touch($this->directory.'/tmp.xml', time() + 20);
        $this->assertFalse($this->resource->isFresh(time() + 10), '->isFresh() returns false if an existing file is modified');
    }

    /**
     * @covers Symfony\Component\Config\Resource\DirectoryResource::isFresh
     */
    public function testIsFreshNewFile()
    {
        touch($this->directory.'/new.xml', time() + 20);
        $this->assertFalse($this->resource->isFresh(time() + 10), '->isFresh() returns false if a new file is added');
    }

    /**
     * @covers Symfony\Component\Config\Resource\DirectoryResource::isFresh
     */
    public function testIsFreshDeleteFile()
    {
        unlink($this->directory.'/tmp.xml');
        $this->assertFalse($this->resource->isFresh(time()), '->isFresh() returns false if an existing file is removed');
    }

    /**
     * @covers Symfony\Component\Config\Resource\DirectoryResource::isFresh
     */
    public function testIsFreshDeleteDirectory()
    {
        $this->removeDirectory($this->directory);
        $this->assertFalse($this->resource->isFresh(time()), '->isFresh() returns false if the whole resource is removed');
    }

    /**
     * @covers Symfony\Component\Config\Resource\DirectoryResource::isFresh
     */
    public function testIsFreshCreateFileInSubdirectory()
    {
        $subdirectory = $this->directory.'/subdirectory';
        mkdir($subdirectory);

        $this->assertTrue($this->resource->isFresh(time() + 10), '->isFresh() returns true if an unmodified subdirectory exists');

        touch($subdirectory.'/newfile.xml', time() + 20);
        $this->assertFalse($this->resource->isFresh(time() + 10), '->isFresh() returns false if a new file in a subdirectory is added');
    }

    /**
     * @covers Symfony\Component\Config\Resource\DirectoryResource::isFresh
     */
    public function testIsFreshModifySubdirectory()
    {
        $subdirectory = $this->directory.'/subdirectory';
        mkdir($subdirectory);
        
        touch($subdirectory, time() + 20);
        $this->assertFalse($this->resource->isFresh(time() + 10), '->isFresh() returns false if a subdirectory is modified (e.g. a file gets deleted)');
    }

    /**
     * @covers Symfony\Component\Config\Resource\DirectoryResource::setFilterRegexList
     * @covers Symfony\Component\Config\Resource\DirectoryResource::getFilterRegexList
     */
public function testSetFilterRegexList()
    {
        $regexes = array('#\.foo$#', '#\.xml$#');
        $this->resource->setFilterRegexList($regexes);

        $this->assertEquals($regexes, $this->resource->getFilterRegexList(), '->getFilterRegexList() returns the previously defined list of filter regexes');
    }

    /**
     * @covers Symfony\Component\Config\Resource\DirectoryResource::isFresh
     */
    public function testFilterRegexListNoMatch()
    {
        $regexes = array('#\.foo$#', '#\.xml$#');
        $this->resource->setFilterRegexList($regexes);

        touch($this->directory.'/new.bar', time() + 20);
        $this->assertTrue($this->resource->isFresh(time() + 10), '->isFresh() returns true if a new file not matching the filter regex is created');
    }

    /**
     * @covers Symfony\Component\Config\Resource\DirectoryResource::isFresh
     */
    public function testFilterRegexListMatch()
    {
        $regexes = array('#\.foo$#', '#\.xml$#');
        $this->resource->setFilterRegexList($regexes);

        touch($this->directory.'/new.xml', time() + 20);
        $this->assertFalse($this->resource->isFresh(time() + 10), '->isFresh() returns false if an new file matching the filter regex is created ');
    }

}
