<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DependencyInjection\Tests\Config;

use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Compiler\AutowirePass;
use Symfony\Component\DependencyInjection\Config\AutowireServiceResource;

/**
 * @group legacy
 */
class AutowireServiceResourceTest extends TestCase
{
    /**
     * @var AutowireServiceResource
     */
    private $resource;
    private $file;
    private $class;
    private $time;

    protected function setUp()
    {
        $this->file = realpath(sys_get_temp_dir()).'/tmp.php';
        $this->time = time();
        touch($this->file, $this->time);

        $this->class = __NAMESPACE__.'\Foo';
        $this->resource = new AutowireServiceResource(
            $this->class,
            $this->file,
            array()
        );
    }

    public function testToString()
    {
        $this->assertSame('service.autowire.'.$this->class, (string) $this->resource);
    }

    public function testSerializeUnserialize()
    {
        $unserialized = unserialize(serialize($this->resource));

        $this->assertEquals($this->resource, $unserialized);
    }

    public function testIsFresh()
    {
        $this->assertTrue($this->resource->isFresh($this->time), '->isFresh() returns true if the resource has not changed in same second');
        $this->assertTrue($this->resource->isFresh($this->time + 10), '->isFresh() returns true if the resource has not changed');
        $this->assertFalse($this->resource->isFresh($this->time - 86400), '->isFresh() returns false if the resource has been updated');
    }

    public function testIsFreshForDeletedResources()
    {
        unlink($this->file);

        $this->assertFalse($this->resource->isFresh($this->getStaleFileTime()), '->isFresh() returns false if the resource does not exist');
    }

    public function testIsNotFreshChangedResource()
    {
        $oldResource = new AutowireServiceResource(
            $this->class,
            $this->file,
            array('will_be_different')
        );

        // test with a stale file *and* a resource that *will* be different than the actual
        $this->assertFalse($oldResource->isFresh($this->getStaleFileTime()), '->isFresh() returns false if the constructor arguments have changed');
    }

    public function testIsFreshSameConstructorArgs()
    {
        $oldResource = AutowirePass::createResourceForClass(
            new \ReflectionClass(__NAMESPACE__.'\Foo')
        );

        // test with a stale file *but* the resource will not be changed
        $this->assertTrue($oldResource->isFresh($this->getStaleFileTime()), '->isFresh() returns false if the constructor arguments have changed');
    }

    public function testNotFreshIfClassNotFound()
    {
        $resource = new AutowireServiceResource(
            'Some\Non\Existent\Class',
            $this->file,
            array()
        );

        $this->assertFalse($resource->isFresh($this->getStaleFileTime()), '->isFresh() returns false if the class no longer exists');
    }

    protected function tearDown()
    {
        if (!file_exists($this->file)) {
            return;
        }

        unlink($this->file);
    }

    private function getStaleFileTime()
    {
        return $this->time - 10;
    }
}

class Foo
{
    public function __construct($foo)
    {
    }
}
