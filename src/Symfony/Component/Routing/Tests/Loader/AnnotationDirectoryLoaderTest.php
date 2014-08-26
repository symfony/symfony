<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Routing\Tests\Loader;

use Symfony\Component\Routing\Loader\AnnotationDirectoryLoader;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\Routing\Annotation\Route;

class AnnotationDirectoryLoaderTest extends AbstractAnnotationLoaderTest
{
    protected $loader;
    protected $reader;

    protected function setUp()
    {
        parent::setUp();

        $this->reader = $this->getReader();
        $this->loader = new AnnotationDirectoryLoader(new FileLocator(), $this->getClassLoader($this->reader));
    }

    public function testLoad()
    {
        $this->reader->expects($this->exactly(2))->method('getClassAnnotation');

        $this->reader
            ->expects($this->any())
            ->method('getMethodAnnotations')
            ->will($this->returnValue(array()))
        ;

        $this->loader->load(__DIR__.'/../Fixtures/AnnotatedClasses');
    }

    public function testSupports()
    {
        $fixturesDir = __DIR__.'/../Fixtures';

        $this->assertTrue($this->loader->supports($fixturesDir), '->supports() returns true if the resource is loadable');
        $this->assertFalse($this->loader->supports('foo.foo'), '->supports() returns true if the resource is loadable');

        $this->assertTrue($this->loader->supports($fixturesDir, 'annotation'), '->supports() checks the resource type if specified');
        $this->assertFalse($this->loader->supports($fixturesDir, 'foo'), '->supports() checks the resource type if specified');
    }

    public function testRoutesWithPriority()
    {
        $routeDatas = array(
            'foo' => new Route(array('name' => 'foo', 'path' => '/foo')),
            'unimportant' => new Route(array('name' => 'unimportant', 'path' => '/unimportant', 'options' => array('priority' => -10))),
            'bar' => new Route(array('name' => 'bar', 'path' => '/bar')),
            'static_id' => new Route(array('name' => 'static_id', 'path' => '/static/{id}', 'options' => array('priority' => 1))),
            'home' => new Route(array('name' => 'home', 'path' => '/home')),
            'static_contact' => new Route(array('name' => 'static_contact', 'path' => '/static/contact', 'options' => array('priority' => 5))),
            'login' => new Route(array('name' => 'login', 'path' => '/login')),
            'static_location' => new Route(array('name' => 'static_location', 'path' => '/static/location', 'options' => array('priority' => 5))),
        );

        $this->reader
            ->expects($this->once())
            ->method('getMethodAnnotations')
            ->will($this->returnValue($routeDatas));

        $routeCollection = $this->loader->load(__DIR__.'/../Fixtures/AnnotatedClasses');
        $expectedOrder = array( 'static_contact', 'static_location', 'static_id', 'foo', 'bar', 'home', 'login', 'unimportant');
        $this->assertEquals($expectedOrder, array_keys($routeCollection->all()));
    }
}
