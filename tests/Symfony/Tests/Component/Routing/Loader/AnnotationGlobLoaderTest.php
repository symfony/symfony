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
use Symfony\Component\Routing\Loader\AnnotationGlobLoader;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

class AnnotationGlobLoaderTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @covers Symfony\Component\Routing\Loader\AnnotationGlobLoader::supports
     */
    public function testSupports()
    {
        $annotationClassLoader = $this->getMockBuilder('Symfony\Component\Routing\Loader\AnnotationClassLoader')
           ->disableOriginalConstructor()
           ->getMockForAbstractClass();

        $loader = new AnnotationGlobLoader($this->getMock('Symfony\Component\Config\FileLocator'), $annotationClassLoader);

        $this->assertTrue($loader->supports('*'), '->supports() returns true if the resource is loadable');
        $this->assertFalse($loader->supports('foo.foo'), '->supports() returns true if the resource is loadable');

        $this->assertTrue($loader->supports('*', 'annotation'), '->supports() checks the resource type if specified');
        $this->assertFalse($loader->supports('*', 'foo'), '->supports() checks the resource type if specified');
    }
}
