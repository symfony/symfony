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
use Symfony\Component\Routing\Loader\AnnotationFileLoader;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

class AnnotationFileLoaderTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @covers Symfony\Component\Routing\Loader\AnnotationFileLoader::supports
     */
    public function testSupports()
    {
        $annotationClassLoader = $this->getMockBuilder('Symfony\Component\Routing\Loader\AnnotationClassLoader')
           ->disableOriginalConstructor()
           ->getMockForAbstractClass();

        $loader = new AnnotationFileLoader($this->getMock('Symfony\Component\Config\FileLocator'), $annotationClassLoader);

        $fixture = __DIR__.'/../Fixtures/annotated.php';

        $this->assertTrue($loader->supports($fixture), '->supports() returns true if the resource is loadable');
        $this->assertFalse($loader->supports('foo.foo'), '->supports() returns true if the resource is loadable');

        $this->assertTrue($loader->supports($fixture, 'annotation'), '->supports() checks the resource type if specified');
        $this->assertFalse($loader->supports($fixture, 'foo'), '->supports() checks the resource type if specified');
    }
}
