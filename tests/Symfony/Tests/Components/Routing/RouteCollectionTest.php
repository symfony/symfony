<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Tests\Components\Routing;

use Symfony\Components\Routing\RouteCollection;
use Symfony\Components\Routing\Route;
use Symfony\Components\Routing\Resource\FileResource;

class RouteCollectionTest extends \PHPUnit_Framework_TestCase
{
    public function testRoute()
    {
        $collection = new RouteCollection();
        $route = new Route('/foo');
        $collection->addRoute('foo', $route);
        $this->assertEquals(array('foo' => $route), $collection->getRoutes(), '->addRoute() adds a route');
        $this->assertEquals($route, $collection->getRoute('foo'), '->getRoute() returns a route by name');
        $this->assertNull($collection->getRoute('bar'), '->getRoute() returns null if a route does not exist');
    }

    public function testAddCollection()
    {
        $collection = new RouteCollection();
        $collection->addRoute('foo', $foo = new Route('/foo'));
        $collection1 = new RouteCollection();
        $collection1->addRoute('foo', $foo1 = new Route('/foo1'));
        $collection1->addRoute('bar', $bar1 = new Route('/bar1'));
        $collection->addCollection($collection1);
        $this->assertEquals(array('foo' => $foo1, 'bar' => $bar1), $collection->getRoutes(), '->addCollection() adds routes from another collection');

        $collection = new RouteCollection();
        $collection->addRoute('foo', $foo = new Route('/foo'));
        $collection1 = new RouteCollection();
        $collection1->addRoute('foo', $foo1 = new Route('/foo1'));
        $collection->addCollection($collection1, '/foo');
        $this->assertEquals('/foo/foo1', $collection->getRoute('foo')->getPattern(), '->addCollection() can add a prefix to all merged routes');

        $collection = new RouteCollection();
        $collection->addResource($foo = new FileResource(__DIR__.'/Fixtures/foo.xml'));
        $collection1 = new RouteCollection();
        $collection1->addResource($foo1 = new FileResource(__DIR__.'/Fixtures/foo1.xml'));
        $collection->addCollection($collection1);
        $this->assertEquals(array($foo, $foo1), $collection->getResources(), '->addCollection() merges resources');
    }

    public function testAddPrefix()
    {
        $collection = new RouteCollection();
        $collection->addRoute('foo', $foo = new Route('/foo'));
        $collection->addRoute('bar', $bar = new Route('/bar'));
        $collection->addPrefix('/admin');
        $this->assertEquals('/admin/foo', $collection->getRoute('foo')->getPattern(), '->addPrefix() adds a prefix to all routes');
        $this->assertEquals('/admin/bar', $collection->getRoute('bar')->getPattern(), '->addPrefix() adds a prefix to all routes');
    }

    public function testResource()
    {
        $collection = new RouteCollection();
        $collection->addResource($foo = new FileResource(__DIR__.'/Fixtures/foo.xml'));
        $this->assertEquals(array($foo), $collection->getResources(), '->addResources() adds a resource');
    }
}
