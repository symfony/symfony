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

use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Loader\AnnotationFileLoader;
use Symfony\Component\Config\FileLocator;

class AnnotationFileLoaderTest extends AbstractAnnotationLoaderTest
{
    protected $loader;
    protected $reader;

    protected function setUp()
    {
        parent::setUp();

        $this->reader = $this->getReader();
        $this->loader = new AnnotationFileLoader(new FileLocator(), $this->getClassLoader($this->reader));
    }

    public function testLoad()
    {
        $this->reader->expects($this->once())->method('getClassAnnotation');

        $this->loader->load(__DIR__.'/../Fixtures/AnnotatedClasses/FooClass.php');
    }

    public function testSupports()
    {
        $fixture = __DIR__.'/../Fixtures/annotated.php';

        $this->assertTrue($this->loader->supports($fixture), '->supports() returns true if the resource is loadable');
        $this->assertFalse($this->loader->supports('foo.foo'), '->supports() returns true if the resource is loadable');

        $this->assertTrue($this->loader->supports($fixture, 'annotation'), '->supports() checks the resource type if specified');
        $this->assertFalse($this->loader->supports($fixture, 'foo'), '->supports() checks the resource type if specified');
    }

    public function testRoutesWithPriority()
    {
        $routeDatas = array(
            'foo' => new Route(array('name' => 'foo', 'path' => '/foo')),
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

        $routeCollection = $this->loader->load(__DIR__.'/../Fixtures/AnnotatedClasses/BarClass.php');
        $expectedOrder = array( 'static_contact', 'static_location', 'static_id', 'foo', 'bar', 'home', 'login');
        $this->assertEquals($expectedOrder, array_keys($routeCollection->all()));
    }
}
