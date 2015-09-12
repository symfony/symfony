<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Tests\Routing;

use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;
use Symfony\Bundle\FrameworkBundle\Routing\RouteCollectionBuilder;
use Symfony\Component\Config\Resource\FileResource;

class RouteCollectionBuilderTest extends \PHPUnit_Framework_TestCase
{
    public function testImport()
    {
        $loader = $this->getLoader();

        $expectedCollection = new RouteCollection();
        $expectedCollection->add('one_test_route', new Route('/foo/path'));

        $loader
            ->expects($this->once())
            ->method('import')
            ->with('admin_routing.yml', 'yaml')
            ->will($this->returnValue($expectedCollection));

        // import the file! (with a prefix)
        $collectionBuilder = new RouteCollectionBuilder($loader);
        $addedBuilder = $collectionBuilder->import('admin_routing.yml', '/admin', 'yaml');

        // we should get back a RouteCollectionBuilder
        $this->assertInstanceOf('Symfony\Bundle\FrameworkBundle\Routing\RouteCollectionBuilder', $addedBuilder);

        // get the collection back so we can look at it
        $addedCollection = $addedBuilder->flush();
        $route = $addedCollection->get('one_test_route');
        $this->assertNotNull($route);
        $this->assertEquals('/admin/foo/path', $route->getPath(), 'The prefix should be applied');
    }

    public function testAdd()
    {
        $loader = $this->getLoader();
        $collectionBuilder = new RouteCollectionBuilder($loader);

        $addedRoute = $collectionBuilder->add('/checkout', 'AppBundle:Order:checkout');
        $addedRoute2 = $collectionBuilder->add('/blogs', 'AppBundle:Blog:list', 'blog_list');
        $this->assertInstanceOf('Symfony\Bundle\FrameworkBundle\Routing\Route', $addedRoute);
        $this->assertEquals('AppBundle:Order:checkout', $addedRoute->getDefault('_controller'));
        $this->assertEquals('blog_list', $addedRoute2->getName());
    }

    public function testFlushOrdering()
    {
        $loadedCollection1 = new RouteCollection();
        $loadedCollection1->add('first_collection_route1', new Route('/collection/first/blog1'));
        $loadedCollection1->add('first_collection_route2', new Route('/collection/first/blog2'));

        $loadedCollection2 = new RouteCollection();
        $loadedCollection2->add('second_collection_route1', new Route('/collection/second/product1'));
        $loadedCollection2->add('second_collection_route2', new Route('/collection/second/product2'));

        $importedCollection = new RouteCollection();
        $importedCollection->add('imported_route1', new Route('/imported/foo1'));
        $importedCollection->add('imported_route2', new Route('/imported/foo2'));

        $loader = $this->getLoader();
        $loader
            ->expects($this->once())
            ->method('import')
            ->will($this->returnValue($importedCollection));

        $collectionBuilder = new RouteCollectionBuilder($loader);

        // 1) Add a route
        $collectionBuilder->add('/checkout', 'AppBundle:Order:checkout')
            ->setName('checkout_route');
        // 2) Add a collection directly
        $collectionBuilder->addRouteCollection($loadedCollection1);
        // 3) Import from a file
        $collectionBuilder->import('admin_routing.yml');
        // 4) Add another route
        $collectionBuilder->add('/', 'AppBundle:Default:homepage')
            ->setName('homepage');
        // 5) Add another collection
        $collectionBuilder->addRouteCollection($loadedCollection2);
        // 6) Add another route
        $collectionBuilder->add('/admin', 'AppBundle:Admin:dashboard')
            ->setName('admin_dashboard');

        // set a default value
        $collectionBuilder->setDefault('_locale', 'fr');
        // set an extra resource
        $collectionBuilder->addResource(new FileResource('foo_routing.xml'));

        $actualCollection = $collectionBuilder->flush();

        $this->assertCount(9, $actualCollection);
        $actualRouteNames = array_keys($actualCollection->all());
        $this->assertEquals(array(
            'checkout_route',
            'first_collection_route1',
            'first_collection_route2',
            'imported_route1',
            'imported_route2',
            'homepage',
            'second_collection_route1',
            'second_collection_route2',
            'admin_dashboard'
        ), $actualRouteNames);

        // make sure the defaults were set
        $checkoutRoute = $actualCollection->get('checkout_route');
        $defaults = $checkoutRoute->getDefaults();
        $this->assertArrayHasKey('_locale', $defaults);
        $this->assertEquals('fr', $defaults['_locale']);

        // technically, we should expect 2 here (admin_routing.yml + foo_routing.xml)
        // but, admin_routing.yml would be added to the collection via the loader, which is mocked
        $this->assertCount(1, $actualCollection->getResources(), 'The added resource is included');
    }

    public function testFlushSetsRouteNames()
    {
        $loader = $this->getLoader();
        $collectionBuilder = new RouteCollectionBuilder($loader);

        // add a "named" route
        $collectionBuilder->add('/admin', 'AppBundle:Admin:dashboard')
            ->setName('admin_dashboard');
        // add an unnamed route
        $collectionBuilder->add('/blogs', 'AppBundle:Blog:list')
            ->setMethods('GET');

        $actualCollection = $collectionBuilder->flush();
        $actualRouteNames = array_keys($actualCollection->all());
        $this->assertEquals(array(
            'admin_dashboard',
            'GET_blogs',
        ), $actualRouteNames);
    }

    public function testFlushClearsEverything()
    {
        $loader = $this->getLoader();
        $collectionBuilder = new RouteCollectionBuilder($loader);

        // add a "named" route
        $collectionBuilder->add('/post', 'AppBundle:Admin:dashboard')
            ->setName('admin_post');
        $collectionBuilder->setPrefix('/admin');
        $collectionBuilder->setDefault('_locale', 'fr');
        $collectionBuilder->setMethods('POST');
        $collectionBuilder->setSchemes('https');
        $collectionBuilder->setCondition('foo');
        $collectionBuilder->setControllerClass('Symfony\Bundle\FrameworkBundle\Tests\Functional\Bundle\TestBundle\Controller\FragmentController');
        $collectionBuilder->setHost('example.com');
        $collectionBuilder->setOption('expose', true);
        $collectionBuilder->setRequirement('id', '\d+');
        $collectionBuilder->addResource(new FileResource('foo_routing.xml'));

        // flush once
        $collectionBuilder->flush();

        // flush again - should not contain previous stuff
        $collectionBuilder->add('/blogs', 'list')
            ->setName('blog_list');
        $secondCollection = $collectionBuilder->flush();

        $this->assertCount(1, $secondCollection);
        $this->assertCount(0, $secondCollection->getResources());
        $blogListRoute = $secondCollection->get('blog_list');
        $this->assertArrayNotHasKey('_locale', $blogListRoute->getDefaults());
        $this->assertEmpty($blogListRoute->getMethods());
        $this->assertEmpty($blogListRoute->getSchemes());
        $this->assertEmpty($blogListRoute->getCondition());
        // controller class should not have been added
        $this->assertEquals('list', $blogListRoute->getDefault('_controller'));
        $this->assertEmpty($blogListRoute->getHost());
        $this->assertNull($blogListRoute->getOption('expose'));
        $this->assertNull($blogListRoute->getRequirement('id'));
    }

    private function getLoader()
    {
        $loader = $this->getMockBuilder('Symfony\Component\Config\Loader\Loader')
            ->disableOriginalConstructor()
            ->getMock();

        return $loader;
    }
}
