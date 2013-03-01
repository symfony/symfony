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

    protected function touch($file, $reltime = 0)
    {
        touch($file, time() + $reltime);
        clearstatcache($file);
    }

    protected function setUp()
    {
        $this->file = sys_get_temp_dir().'/tmp.xml';
        $this->touch($this->file, -86400);
        $this->resource = new FileResource($this->file);
    }

    protected function tearDown()
    {
        unlink($this->file);
    }

    /**
     * @covers Symfony\Component\Config\Resource\FileResource::isFresh
     */
    public function testIsFresh()
    {
        $this->assertTrue($this->resource->isFresh(), '->isFresh() returns true if the resource has not changed');

        $this->touch($this->file);
        $this->assertFalse($this->resource->isFresh(), '->isFresh() returns false if the resource has been updated');

        $resource = new FileResource('/____foo/foobar'.rand(1, 999999));
        $this->assertFalse($resource->isFresh(), '->isFresh() returns false if the resource does not exist');
    }
}
