<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Tests\Component\Routing\Loader;

use Symfony\Component\Config\Loader\LoaderResolver;
use Symfony\Component\Routing\Loader\AnnotationClassLoader;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

class AnnotationClassLoaderTest extends \PHPUnit_Framework_TestCase
{
    protected $loader;

    protected function setUp()
    {
        $this->loader = $this->getMockBuilder('Symfony\Component\Routing\Loader\AnnotationClassLoader')
            ->disableOriginalConstructor()
            ->getMockForAbstractClass();
    }

    protected function tearDown()
    {
        $this->loader = null;
    }

    /**
     * @covers Symfony\Component\Routing\Loader\AnnotationClassLoader::supports
     * @dataProvider provideTestSupportsChecksResource
     */
    public function testSupportsChecksResource($resource, $expectedSupports)
    {
        $this->assertSame($expectedSupports, $this->loader->supports($resource), '->supports() returns true if the resource is loadable');
    }

    public function provideTestSupportsChecksResource()
    {
        return array(
            array('class', true),
            array('\fully\qualified\class\name', true),
            array('namespaced\class\without\leading\slash', true),
            array('ÿClassWithLegalSpecialCharacters', true),
            array('5', false),
            array('foo.foo', false),
            array(null, false),
        );
    }

    /**
     * @covers Symfony\Component\Routing\Loader\AnnotationClassLoader::supports
     */
    public function testSupportsChecksTypeIfSpecified()
    {
        $this->assertTrue($this->loader->supports('class', 'annotation'), '->supports() checks the resource type if specified');
        $this->assertFalse($this->loader->supports('class', 'foo'), '->supports() checks the resource type if specified');
    }

}
