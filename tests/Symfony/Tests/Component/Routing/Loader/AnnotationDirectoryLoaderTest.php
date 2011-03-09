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

use Symfony\Component\Routing\Loader\AnnotationDirectoryLoader;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;
use Symfony\Component\Config\Loader\LoaderResolver;
use Symfony\Component\Config\FileLocator;

class AnnotationDirectoryLoaderTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @covers Symfony\Component\Routing\Loader\AnnotationDirectoryLoader::supports
     */
    public function testSupports()
    {
        $annotationClassLoader = $this->getMockBuilder('Symfony\Component\Routing\Loader\AnnotationClassLoader')
           ->disableOriginalConstructor()
           ->getMockForAbstractClass();

        $loader = new AnnotationDirectoryLoader(new FileLocator(), $annotationClassLoader);

        $fixturesDir = __DIR__.'/../Fixtures';

        $this->assertTrue($loader->supports($fixturesDir), '->supports() returns true if the resource is loadable');
        $this->assertFalse($loader->supports('foo.foo'), '->supports() returns true if the resource is loadable');

        $this->assertTrue($loader->supports($fixturesDir, 'annotation'), '->supports() checks the resource type if specified');
        $this->assertFalse($loader->supports($fixturesDir, 'foo'), '->supports() checks the resource type if specified');
    }
}
