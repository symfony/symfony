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

use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Resource\FileResource;

class FileResourceTest extends TestCase
{
    protected FileResource $resource;
    protected string $file;
    protected int $time;

    protected function setUp(): void
    {
        $this->file = sys_get_temp_dir().'/tmp.xml';
        $this->time = time();
        touch($this->file, $this->time);
        $this->resource = new FileResource($this->file);
    }

    protected function tearDown(): void
    {
        if (file_exists($this->file)) {
            @unlink($this->file);
        }
    }

    public function testGetResource()
    {
        $this->assertSame(realpath($this->file), $this->resource->getResource(), '->getResource() returns the path to the resource');
    }

    public function testGetResourceWithScheme()
    {
        $resource = new FileResource('file://'.$this->file);
        $this->assertSame('file://'.$this->file, $resource->getResource(), '->getResource() returns the path to the schemed resource');
    }

    public function testToString()
    {
        $this->assertSame(realpath($this->file), (string) $this->resource);
    }

    public function testResourceDoesNotExist()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/The file ".*" does not exist./');
        new FileResource('/____foo/foobar'.random_int(1, 999999));
    }

    public function testIsFresh()
    {
        $this->assertTrue($this->resource->isFresh($this->time), '->isFresh() returns true if the resource has not changed in same second');
        $this->assertTrue($this->resource->isFresh($this->time + 10), '->isFresh() returns true if the resource has not changed');
        $this->assertFalse($this->resource->isFresh($this->time - 86400), '->isFresh() returns false if the resource has been updated');
    }

    public function testIsFreshWhenModifiedInSameSecondAfterBeingLoaded()
    {
        $this->touch($this->time - 10);
        $resource = new FileResource($this->file);
        $this->touch($time = $this->time + 20);

        $this->assertFalse($resource->isFresh($time), '->isFresh() returns false if the resource has been updated in the same second');
        $this->assertTrue($resource->isFresh($time + 1), '->isFresh() returns true if the resource has not changed since the previous second');
    }

    public function testIsFreshComparesContentWhenModifiedInSameSecondAsLoaded()
    {
        $this->touch($time = $this->time + 20);
        $resource = unserialize(serialize(new FileResource($this->file)));

        $this->assertTrue($resource->isFresh($time), '->isFresh() returns true if the content has not changed since the resource was loaded');

        file_put_contents($this->file, 'changed');
        $this->touch($time);

        $this->assertFalse($resource->isFresh($time), '->isFresh() returns false if the content has changed in the same second');
    }

    public function testIsFreshForDeletedResources()
    {
        unlink($this->file);

        $this->assertFalse($this->resource->isFresh($this->time), '->isFresh() returns false if the resource does not exist');
    }

    public function testSerializeUnserialize()
    {
        unserialize(serialize($this->resource));

        $this->assertSame(realpath($this->file), $this->resource->getResource());
    }

    private function touch(int $time): void
    {
        touch($this->file, $time);
        // touch() clears the stat cache only as of PHP 8.4.5
        clearstatcache();
    }
}
