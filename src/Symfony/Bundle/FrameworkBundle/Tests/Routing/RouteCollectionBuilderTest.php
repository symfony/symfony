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
        $addedCollection = $addedBuilder->build();
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
        $this->assertInstanceOf('Symfony\Component\Routing\Route', $addedRoute);
        $this->assertEquals('AppBundle:Order:checkout', $addedRoute->getDefault('_controller'));

        $finalCollection = $collectionBuilder->build();
        $this->assertSame($addedRoute2, $finalCollection->get('blog_list'));
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
        $collectionBuilder->add('/checkout', 'AppBundle:Order:checkout', 'checkout_route');
        // 2) Add a collection directly
        $collectionBuilder->addRouteCollection($loadedCollection1);
        // 3) Import from a file
        $collectionBuilder->import('admin_routing.yml');
        // 4) Add another route
        $collectionBuilder->add('/', 'AppBundle:Default:homepage', 'homepage');
        // 5) Add another collection
        $collectionBuilder->addRouteCollection($loadedCollection2);
        // 6) Add another route
        $collectionBuilder->add('/admin', 'AppBundle:Admin:dashboard', 'admin_dashboard');

        // set a default value
        $collectionBuilder->setDefault('_locale', 'fr');
        // set an extra resource
        $collectionBuilder->addResource(new FileResource('foo_routing.xml'));

        $actualCollection = $collectionBuilder->build();

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
            'admin_dashboard',
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
        $collectionBuilder->add('/admin', 'AppBundle:Admin:dashboard', 'admin_dashboard');
        // add an unnamed route
        $collectionBuilder->add('/blogs', 'AppBundle:Blog:list')
            ->setMethods('GET');

        // integer route names are allowed - they don't confuse things
        $collectionBuilder->add('/products', 'AppBundle:Product:list', 100);

        $actualCollection = $collectionBuilder->build();
        $actualRouteNames = array_keys($actualCollection->all());
        $this->assertEquals(array(
            'admin_dashboard',
            'GET_blogs',
            '100'
        ), $actualRouteNames);
    }

    public function testFlushSetsDetailsOnChildrenRoutes()
    {
        $loader = $this->getLoader();
        $routes = new RouteCollectionBuilder($loader);

        $routes->add('/blogs/{page}', 'listAction', 'blog_list')
            // unique things for the route
            ->setDefault('page', 1)
            ->setRequirement('id', '\d+')
            ->setOption('expose', true)
            // things that the collection will try to override (but won't)
            ->setDefault('_format', 'html')
            ->setRequirement('_format', 'json|xml')
            ->setOption('fooBar', true)
            ->setHost('example.com')
            ->setCondition('request.isSecure()')
            ->setSchemes('https')
            ->setMethods('POST');

        // a simple route, nothing added to it
        $routes->add('/blogs/{id}', 'editAction', 'blog_edit');

        // configure the collection itself
        $routes
            // things that will not override the child route
            ->setDefault('_format', 'json')
            ->setRequirement('_format', 'xml')
            ->setOption('fooBar', false)
            ->setHost('symfony.com')
            ->setCondition('request.query.get("page")==1')
            // some unique things that should be set on the child
            ->setDefault('_locale', 'fr')
            ->setRequirement('_locale', 'fr|en')
            ->setOption('niceRoute', true)
            ->setSchemes('http')
            ->setMethods(array('GET', 'POST'));

        $collection = $routes->build();
        $actualListRoute = $collection->get('blog_list');

        $this->assertEquals(1, $actualListRoute->getDefault('page'));
        $this->assertEquals('\d+', $actualListRoute->getRequirement('id'));
        $this->assertTrue($actualListRoute->getOption('expose'));
        // none of these should be overridden
        $this->assertEquals('html', $actualListRoute->getDefault('_format'));
        $this->assertEquals('json|xml', $actualListRoute->getRequirement('_format'));
        $this->assertTrue($actualListRoute->getOption('fooBar'));
        $this->assertEquals('example.com', $actualListRoute->getHost());
        $this->assertEquals('request.isSecure()', $actualListRoute->getCondition());
        $this->assertEquals(array('https'), $actualListRoute->getSchemes());
        $this->assertEquals(array('POST'), $actualListRoute->getMethods());
        // inherited from the main collection
        $this->assertEquals('fr', $actualListRoute->getDefault('_locale'));
        $this->assertEquals('fr|en', $actualListRoute->getRequirement('_locale'));
        $this->assertTrue($actualListRoute->getOption('niceRoute'));

        $actualEditRoute = $collection->get('blog_edit');
        // inherited from the collection
        $this->assertEquals('symfony.com', $actualEditRoute->getHost());
        $this->assertEquals('request.query.get("page")==1', $actualEditRoute->getCondition());
        $this->assertEquals(array('http'), $actualEditRoute->getSchemes());
        $this->assertEquals(array('GET', 'POST'), $actualEditRoute->getMethods());
    }

    /**
     * @dataProvider providePrefixTests
     */
    public function testFlushPrefixesPaths($collectionPrefix, $routePath, $expectedPath)
    {
        $loader = $this->getLoader();
        $routes = new RouteCollectionBuilder($loader);
        $routes->setPrefix($collectionPrefix);

        $routes->add($routePath, 'someController', 'test_route');
        $collection = $routes->build();

        $this->assertEquals($expectedPath, $collection->get('test_route')->getPath());
    }

    public function providePrefixTests()
    {
        $tests = array();
        // empty prefix is of course ok
        $tests[] = array('', '/foo', '/foo');
        // normal prefix - does not matter if it's a wildcard
        $tests[] = array('/{admin}', '/foo', '/{admin}/foo');
        // shows that a prefix will always be given the starting slash
        $tests = array();
        $tests[] = array('0', '/foo', '/0/foo');

        // spaces are ok, and double slahses at the end are cleaned
        $tests[] = array('/ /', '/foo', '/ /foo');

        return $tests;
    }

    public function testFlushSetsPrefixedWithMultipleLevels()
    {
        $loader = $this->getLoader();
        $routes = new RouteCollectionBuilder($loader);

        $routes->add('homepage', 'MainController::homepageAction', 'homepage');

        $adminRoutes = $routes->createCollection('/admin');
        $adminRoutes->add('/dashboard', 'AdminController::dashboardAction', 'admin_dashboard');

        // embedded collection under /admin
        $adminBlogRoutes = $routes->createCollection('/blog');
        $adminBlogRoutes->add('/new', 'BlogController::newAction', 'admin_blog_new');
        // mount into admin, but before the parent collection has been mounted
        $adminRoutes->mount($adminBlogRoutes);

        // now mount the /admin routes, above should all still be /blog/admin
        $routes->mount($adminRoutes);
        // add a route after mounting
        $adminRoutes->add('/users', 'AdminController::userAction', 'admin_users');

        // add another sub-collection after the mount
        $otherAdminRoutes = $routes->createCollection('/stats');
        $otherAdminRoutes->add('/sales', 'StatsController::indexAction', 'admin_stats_sales');
        $adminRoutes->mount($otherAdminRoutes);

        // add a normal collection and see that it is also prefixed
        $importedCollection = new RouteCollection();
        $importedCollection->add('imported_route', new Route('/foo'));
        $loader
            ->expects($this->any())
            ->method('import')
            ->will($this->returnValue($importedCollection));
        // import this from the /admin route builder
        $adminRoutes->import('admin.yml', '/imported');

        $collection = $routes->build();
        $this->assertEquals('/admin/dashboard', $collection->get('admin_dashboard')->getPath(), 'Routes before mounting have the prefix');
        $this->assertEquals('/admin/users', $collection->get('admin_users')->getPath(), 'Routes after mounting have the prefix');
        $this->assertEquals('/admin/blog/new', $collection->get('admin_blog_new')->getPath(), 'Sub-collections receive prefix even if mounted before parent prefix');
        $this->assertEquals('/admin/stats/sales', $collection->get('admin_stats_sales')->getPath(), 'Sub-collections receive prefix if mounted after parent prefix');
        $this->assertEquals('/admin/imported/foo', $collection->get('imported_route')->getPath(), 'Normal RouteCollections are also prefixed properly');
    }

    /**
     * @dataProvider provideControllerClassTests
     */
    public function testSetControllerClass($routeController, $controllerClass, $expectedFinalController)
    {
        $loader = $this->getLoader();
        $routes = new RouteCollectionBuilder($loader);
        $routes->setControllerClass('Symfony\Bundle\FrameworkBundle\Tests\Functional\Bundle\TestBundle\Controller\FragmentController');

        $routes->add('/', $routeController, 'test_route');
        $collection = $routes->build();
        $this->assertEquals($expectedFinalController, $collection->get('test_route')->getDefault('_controller'));
    }

    public function provideControllerClassTests()
    {
        $controllerClass = 'Symfony\Bundle\FrameworkBundle\Tests\Functional\Bundle\TestBundle\Controller\FragmentController';

        $tests = array();
        $tests[] = array('withControllerAction', $controllerClass, $controllerClass.'::'.'withControllerAction');

        // the controllerClass should not be used in many cases
        $tests[] = array('', $controllerClass, '');
        $tests[] = array('Some\Class\FooController::fooAction', $controllerClass, 'Some\Class\FooController::fooAction');
        $tests[] = array('AppBundle:Default:index', $controllerClass, 'AppBundle:Default:index');
        $tests[] = array('foo_controller:fooAction', $controllerClass, 'foo_controller:fooAction');
        $tests[] = array(array('Acme\FooController', 'fooAction'), $controllerClass, array('Acme\FooController', 'fooAction'));

        $closure = function() {};
        $tests[] = array($closure, $controllerClass, $closure);

        return $tests;
    }

    /**
     * @expectedException InvalidArgumentException
     */
    public function testExceptiononBadControllerClass()
    {
        $loader = $this->getLoader();
        $routes = new RouteCollectionBuilder($loader);

        $routes->setControllerClass('Acme\FakeController');
    }

    private function getLoader()
    {
        $loader = $this->getMockBuilder('Symfony\Component\Config\Loader\Loader')
            ->disableOriginalConstructor()
            ->getMock();

        return $loader;
    }
}
