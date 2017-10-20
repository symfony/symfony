<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Routing\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\Config\Resource\FileResource;
use Symfony\Component\Routing\Loader\YamlFileLoader;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;
use Symfony\Component\Routing\RouteCollectionBuilder;

class RouteCollectionBuilderTest extends TestCase
{
    public function testImport()
    {
        $resolvedLoader = $this->getMockBuilder('Symfony\Component\Config\Loader\LoaderInterface')->getMock();
        $resolver = $this->getMockBuilder('Symfony\Component\Config\Loader\LoaderResolverInterface')->getMock();
        $resolver->expects($this->once())
            ->method('resolve')
            ->with('admin_routing.yml', 'yaml')
            ->will($this->returnValue($resolvedLoader));

        $originalRoute = new Route('/foo/path');
        $expectedCollection = new RouteCollection();
        $expectedCollection->add('one_test_route', $originalRoute);
        $expectedCollection->addResource(new FileResource(__DIR__.'/Fixtures/file_resource.yml'));

        $resolvedLoader
            ->expects($this->once())
            ->method('load')
            ->with('admin_routing.yml', 'yaml')
            ->will($this->returnValue($expectedCollection));

        $loader = $this->getMockBuilder('Symfony\Component\Config\Loader\LoaderInterface')->getMock();
        $loader->expects($this->any())
            ->method('getResolver')
            ->will($this->returnValue($resolver));

        // import the file!
        $routes = new RouteCollectionBuilder($loader);
        $importedRoutes = $routes->import('admin_routing.yml', '/', 'yaml');

        // we should get back a RouteCollectionBuilder
        $this->assertInstanceOf('Symfony\Component\Routing\RouteCollectionBuilder', $importedRoutes);

        // get the collection back so we can look at it
        $addedCollection = $importedRoutes->build();
        $route = $addedCollection->get('one_test_route');
        $this->assertSame($originalRoute, $route);
        // should return file_resource.yml, which is in the original collection
        $this->assertCount(1, $addedCollection->getResources());

        // make sure the routes were imported into the top-level builder
        $routeCollection = $routes->build();
        $this->assertCount(1, $routes->build());
        $this->assertCount(1, $routeCollection->getResources());
    }

    public function testImportAddResources()
    {
        $routeCollectionBuilder = new RouteCollectionBuilder(new YamlFileLoader(new FileLocator(array(__DIR__.'/Fixtures/'))));
        $routeCollectionBuilder->import('file_resource.yml');
        $routeCollection = $routeCollectionBuilder->build();

        $this->assertCount(1, $routeCollection->getResources());
    }

    /**
     * @expectedException \BadMethodCallException
     */
    public function testImportWithoutLoaderThrowsException()
    {
        $collectionBuilder = new RouteCollectionBuilder();
        $collectionBuilder->import('routing.yml');
    }

    public function testAdd()
    {
        $collectionBuilder = new RouteCollectionBuilder();

        $addedRoute = $collectionBuilder->add('/checkout', 'AppBundle:Order:checkout');
        $addedRoute2 = $collectionBuilder->add('/blogs', 'AppBundle:Blog:list', 'blog_list');
        $this->assertInstanceOf('Symfony\Component\Routing\Route', $addedRoute);
        $this->assertEquals('AppBundle:Order:checkout', $addedRoute->getDefault('_controller'));

        $finalCollection = $collectionBuilder->build();
        $this->assertSame($addedRoute2, $finalCollection->get('blog_list'));
    }

    public function testFlushOrdering()
    {
        $importedCollection = new RouteCollection();
        $importedCollection->add('imported_route1', new Route('/imported/foo1'));
        $importedCollection->add('imported_route2', new Route('/imported/foo2'));

        $loader = $this->getMockBuilder('Symfony\Component\Config\Loader\LoaderInterface')->getMock();
        // make this loader able to do the import - keeps mocking simple
        $loader->expects($this->any())
            ->method('supports')
            ->will($this->returnValue(true));
        $loader
            ->expects($this->once())
            ->method('load')
            ->will($this->returnValue($importedCollection));

        $routes = new RouteCollectionBuilder($loader);

        // 1) Add a route
        $routes->add('/checkout', 'AppBundle:Order:checkout', 'checkout_route');
        // 2) Import from a file
        $routes->mount('/', $routes->import('admin_routing.yml'));
        // 3) Add another route
        $routes->add('/', 'AppBundle:Default:homepage', 'homepage');
        // 4) Add another route
        $routes->add('/admin', 'AppBundle:Admin:dashboard', 'admin_dashboard');

        // set a default value
        $routes->setDefault('_locale', 'fr');

        $actualCollection = $routes->build();

        $this->assertCount(5, $actualCollection);
        $actualRouteNames = array_keys($actualCollection->all());
        $this->assertEquals(array(
            'checkout_route',
            'imported_route1',
            'imported_route2',
            'homepage',
            'admin_dashboard',
        ), $actualRouteNames);

        // make sure the defaults were set
        $checkoutRoute = $actualCollection->get('checkout_route');
        $defaults = $checkoutRoute->getDefaults();
        $this->assertArrayHasKey('_locale', $defaults);
        $this->assertEquals('fr', $defaults['_locale']);
    }

    public function testFlushSetsRouteNames()
    {
        $collectionBuilder = new RouteCollectionBuilder();

        // add a "named" route
        $collectionBuilder->add('/admin', 'AppBundle:Admin:dashboard', 'admin_dashboard');
        // add an unnamed route
        $collectionBuilder->add('/blogs', 'AppBundle:Blog:list')
            ->setMethods(array('GET'));

        // integer route names are allowed - they don't confuse things
        $collectionBuilder->add('/products', 'AppBundle:Product:list', 100);

        $actualCollection = $collectionBuilder->build();
        $actualRouteNames = array_keys($actualCollection->all());
        $this->assertEquals(array(
            'admin_dashboard',
            'GET_blogs',
            '100',
        ), $actualRouteNames);
    }

    public function testFlushSetsDetailsOnChildrenRoutes()
    {
        $routes = new RouteCollectionBuilder();

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
            ->setSchemes(array('https'))
            ->setMethods(array('POST'));

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
            ->setSchemes(array('http'))
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
        $routes = new RouteCollectionBuilder();

        $routes->add($routePath, 'someController', 'test_route');

        $outerRoutes = new RouteCollectionBuilder();
        $outerRoutes->mount($collectionPrefix, $routes);

        $collection = $outerRoutes->build();

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
        $tests[] = array('0', '/foo', '/0/foo');

        // spaces are ok, and double slahses at the end are cleaned
        $tests[] = array('/ /', '/foo', '/ /foo');

        return $tests;
    }

    public function testFlushSetsPrefixedWithMultipleLevels()
    {
        $loader = $this->getMockBuilder('Symfony\Component\Config\Loader\LoaderInterface')->getMock();
        $routes = new RouteCollectionBuilder($loader);

        $routes->add('homepage', 'MainController::homepageAction', 'homepage');

        $adminRoutes = $routes->createBuilder();
        $adminRoutes->add('/dashboard', 'AdminController::dashboardAction', 'admin_dashboard');

        // embedded collection under /admin
        $adminBlogRoutes = $routes->createBuilder();
        $adminBlogRoutes->add('/new', 'BlogController::newAction', 'admin_blog_new');
        // mount into admin, but before the parent collection has been mounted
        $adminRoutes->mount('/blog', $adminBlogRoutes);

        // now mount the /admin routes, above should all still be /blog/admin
        $routes->mount('/admin', $adminRoutes);
        // add a route after mounting
        $adminRoutes->add('/users', 'AdminController::userAction', 'admin_users');

        // add another sub-collection after the mount
        $otherAdminRoutes = $routes->createBuilder();
        $otherAdminRoutes->add('/sales', 'StatsController::indexAction', 'admin_stats_sales');
        $adminRoutes->mount('/stats', $otherAdminRoutes);

        // add a normal collection and see that it is also prefixed
        $importedCollection = new RouteCollection();
        $importedCollection->add('imported_route', new Route('/foo'));
        // make this loader able to do the import - keeps mocking simple
        $loader->expects($this->any())
            ->method('supports')
            ->will($this->returnValue(true));
        $loader
            ->expects($this->any())
            ->method('load')
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

    public function testAutomaticRouteNamesDoNotConflict()
    {
        $routes = new RouteCollectionBuilder();

        $adminRoutes = $routes->createBuilder();
        // route 1
        $adminRoutes->add('/dashboard', '');

        $accountRoutes = $routes->createBuilder();
        // route 2
        $accountRoutes->add('/dashboard', '')
            ->setMethods(array('GET'));
        // route 3
        $accountRoutes->add('/dashboard', '')
            ->setMethods(array('POST'));

        $routes->mount('/admin', $adminRoutes);
        $routes->mount('/account', $accountRoutes);

        $collection = $routes->build();
        // there are 2 routes (i.e. with non-conflicting names)
        $this->assertCount(3, $collection->all());
    }
}
