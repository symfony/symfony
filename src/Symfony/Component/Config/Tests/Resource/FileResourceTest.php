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

use Symfony\Component\Config\Resource\FileResource;

class FileResourceTest extends \PHPUnit_Framework_TestCase
{
    protected $resource;
    protected $file;

    protected function setUp()
    {
        $this->file = realpath(sys_get_temp_dir()).'/tmp.xml';
        touch($this->file);
        $this->resource = new FileResource($this->file);
    }

    protected function tearDown()
    {
        unlink($this->file);
    }

    public function testGetResource()
    {
        $this->assertSame(realpath($this->file), $this->resource->getResource(), '->getResource() returns the path to the resource');
    }

    public function testToString()
    {
        $this->assertSame(realpath($this->file), (string) $this->resource);
    }

    public function testIsFresh()
    {
        $this->assertTrue($this->resource->isFresh(time() + 10), '->isFresh() returns true if the resource has not changed');
        $this->assertFalse($this->resource->isFresh(time() - 86400), '->isFresh() returns false if the resource has been updated');

        $resource = new FileResource('/____foo/foobar'.rand(1, 999999));
        $this->assertFalse($resource->isFresh(time()), '->isFresh() returns false if the resource does not exist');
    }

    public function testSerializeUnserialize()
    {
        $unserialized = unserialize(serialize($this->resource));

        $this->assertSame(realpath($this->file), $this->resource->getResource());
    }
}
