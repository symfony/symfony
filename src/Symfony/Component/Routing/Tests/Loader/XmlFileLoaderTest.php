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

use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\Config\Resource\FileResource;
use Symfony\Component\Routing\Loader\XmlFileLoader;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;
use Symfony\Component\Routing\Tests\Fixtures\CustomXmlFileLoader;

class XmlFileLoaderTest extends TestCase
{
    public function testSupports()
    {
        $loader = new XmlFileLoader(self::createMock(FileLocator::class));

        self::assertTrue($loader->supports('foo.xml'), '->supports() returns true if the resource is loadable');
        self::assertFalse($loader->supports('foo.foo'), '->supports() returns true if the resource is loadable');

        self::assertTrue($loader->supports('foo.xml', 'xml'), '->supports() checks the resource type if specified');
        self::assertFalse($loader->supports('foo.xml', 'foo'), '->supports() checks the resource type if specified');
    }

    public function testLoadWithRoute()
    {
        $loader = new XmlFileLoader(new FileLocator([__DIR__.'/../Fixtures']));
        $routeCollection = $loader->load('validpattern.xml');
        $route = $routeCollection->get('blog_show');

        self::assertInstanceOf(Route::class, $route);
        self::assertSame('/blog/{slug}', $route->getPath());
        self::assertSame('{locale}.example.com', $route->getHost());
        self::assertSame('MyBundle:Blog:show', $route->getDefault('_controller'));
        self::assertSame('\w+', $route->getRequirement('locale'));
        self::assertSame('RouteCompiler', $route->getOption('compiler_class'));
        self::assertEquals(['GET', 'POST', 'PUT', 'OPTIONS'], $route->getMethods());
        self::assertEquals(['https'], $route->getSchemes());
        self::assertEquals('context.getMethod() == "GET"', $route->getCondition());
        self::assertTrue($route->getDefault('_stateless'));
    }

    public function testLoadWithNamespacePrefix()
    {
        $loader = new XmlFileLoader(new FileLocator([__DIR__.'/../Fixtures']));
        $routeCollection = $loader->load('namespaceprefix.xml');

        self::assertCount(1, $routeCollection->all(), 'One route is loaded');

        $route = $routeCollection->get('blog_show');
        self::assertSame('/blog/{slug}', $route->getPath());
        self::assertSame('{_locale}.example.com', $route->getHost());
        self::assertSame('MyBundle:Blog:show', $route->getDefault('_controller'));
        self::assertSame('\w+', $route->getRequirement('slug'));
        self::assertSame('en|fr|de', $route->getRequirement('_locale'));
        self::assertNull($route->getDefault('slug'));
        self::assertSame('RouteCompiler', $route->getOption('compiler_class'));
        self::assertSame(1, $route->getDefault('page'));
    }

    public function testLoadWithImport()
    {
        $loader = new XmlFileLoader(new FileLocator([__DIR__.'/../Fixtures']));
        $routeCollection = $loader->load('validresource.xml');
        $routes = $routeCollection->all();

        self::assertCount(2, $routes, 'Two routes are loaded');
        self::assertContainsOnly('Symfony\Component\Routing\Route', $routes);

        foreach ($routes as $route) {
            self::assertSame('/{foo}/blog/{slug}', $route->getPath());
            self::assertSame('123', $route->getDefault('foo'));
            self::assertSame('\d+', $route->getRequirement('foo'));
            self::assertSame('bar', $route->getOption('foo'));
            self::assertSame('', $route->getHost());
            self::assertSame('context.getMethod() == "POST"', $route->getCondition());
        }
    }

    public function testLoadingRouteWithDefaults()
    {
        $loader = new XmlFileLoader(new FileLocator([__DIR__.'/../Fixtures']));
        $routes = $loader->load('defaults.xml');

        self::assertCount(1, $routes);

        $defaultsRoute = $routes->get('defaults');

        self::assertSame('/defaults', $defaultsRoute->getPath());
        self::assertSame('en', $defaultsRoute->getDefault('_locale'));
        self::assertSame('html', $defaultsRoute->getDefault('_format'));
        self::assertTrue($defaultsRoute->getDefault('_stateless'));
    }

    public function testLoadingImportedRoutesWithDefaults()
    {
        $loader = new XmlFileLoader(new FileLocator([__DIR__.'/../Fixtures']));
        $routes = $loader->load('importer-with-defaults.xml');

        self::assertCount(2, $routes);

        $expectedRoutes = new RouteCollection();
        $expectedRoutes->add('one', $localeRoute = new Route('/defaults/one'));
        $localeRoute->setDefault('_locale', 'g_locale');
        $localeRoute->setDefault('_format', 'g_format');
        $localeRoute->setDefault('_stateless', true);
        $expectedRoutes->add('two', $formatRoute = new Route('/defaults/two'));
        $formatRoute->setDefault('_locale', 'g_locale');
        $formatRoute->setDefault('_format', 'g_format');
        $formatRoute->setDefault('_stateless', true);
        $formatRoute->setDefault('specific', 'imported');

        $expectedRoutes->addResource(new FileResource(__DIR__.'/../Fixtures/imported-with-defaults.xml'));
        $expectedRoutes->addResource(new FileResource(__DIR__.'/../Fixtures/importer-with-defaults.xml'));

        self::assertEquals($expectedRoutes, $routes);
    }

    public function testLoadingUtf8Route()
    {
        $loader = new XmlFileLoader(new FileLocator([__DIR__.'/../Fixtures/localized']));
        $routes = $loader->load('utf8.xml');

        self::assertCount(2, $routes);

        $expectedRoutes = new RouteCollection();
        $expectedRoutes->add('app_utf8', $route = new Route('/utf8'));
        $route->setOption('utf8', true);

        $expectedRoutes->add('app_no_utf8', $route = new Route('/no-utf8'));
        $route->setOption('utf8', false);

        $expectedRoutes->addResource(new FileResource(__DIR__.'/../Fixtures/localized/utf8.xml'));

        self::assertEquals($expectedRoutes, $routes);
    }

    public function testLoadingUtf8ImportedRoutes()
    {
        $loader = new XmlFileLoader(new FileLocator([__DIR__.'/../Fixtures/localized']));
        $routes = $loader->load('importer-with-utf8.xml');

        self::assertCount(2, $routes);

        $expectedRoutes = new RouteCollection();
        $expectedRoutes->add('utf8_one', $one = new Route('/one'));
        $one->setOption('utf8', true);

        $expectedRoutes->add('utf8_two', $two = new Route('/two'));
        $two->setOption('utf8', true);

        $expectedRoutes->addResource(new FileResource(__DIR__.'/../Fixtures/localized/imported-with-utf8.xml'));
        $expectedRoutes->addResource(new FileResource(__DIR__.'/../Fixtures/localized/importer-with-utf8.xml'));

        self::assertEquals($expectedRoutes, $routes);
    }

    public function testLoadLocalized()
    {
        $loader = new XmlFileLoader(new FileLocator([__DIR__.'/../Fixtures']));
        $routeCollection = $loader->load('localized.xml');
        $routes = $routeCollection->all();

        self::assertCount(2, $routes, 'Two routes are loaded');
        self::assertContainsOnly('Symfony\Component\Routing\Route', $routes);

        self::assertEquals('/route', $routeCollection->get('localized.fr')->getPath());
        self::assertEquals('/path', $routeCollection->get('localized.en')->getPath());
    }

    public function testLocalizedImports()
    {
        $loader = new XmlFileLoader(new FileLocator([__DIR__.'/../Fixtures/localized']));
        $routeCollection = $loader->load('importer-with-locale.xml');
        $routes = $routeCollection->all();

        self::assertCount(2, $routes, 'Two routes are loaded');
        self::assertContainsOnly('Symfony\Component\Routing\Route', $routes);

        self::assertEquals('/le-prefix/le-suffix', $routeCollection->get('imported.fr')->getPath());
        self::assertEquals('/the-prefix/suffix', $routeCollection->get('imported.en')->getPath());

        self::assertEquals('fr', $routeCollection->get('imported.fr')->getRequirement('_locale'));
        self::assertEquals('en', $routeCollection->get('imported.en')->getRequirement('_locale'));
    }

    public function testLocalizedImportsOfNotLocalizedRoutes()
    {
        $loader = new XmlFileLoader(new FileLocator([__DIR__.'/../Fixtures/localized']));
        $routeCollection = $loader->load('importer-with-locale-imports-non-localized-route.xml');
        $routes = $routeCollection->all();

        self::assertCount(2, $routes, 'Two routes are loaded');
        self::assertContainsOnly('Symfony\Component\Routing\Route', $routes);

        self::assertEquals('/le-prefix/suffix', $routeCollection->get('imported.fr')->getPath());
        self::assertEquals('/the-prefix/suffix', $routeCollection->get('imported.en')->getPath());

        self::assertSame('fr', $routeCollection->get('imported.fr')->getRequirement('_locale'));
        self::assertSame('en', $routeCollection->get('imported.en')->getRequirement('_locale'));
    }

    /**
     * @dataProvider getPathsToInvalidFiles
     */
    public function testLoadThrowsExceptionWithInvalidFile($filePath)
    {
        self::expectException(\InvalidArgumentException::class);
        $loader = new XmlFileLoader(new FileLocator([__DIR__.'/../Fixtures']));
        $loader->load($filePath);
    }

    /**
     * @dataProvider getPathsToInvalidFiles
     */
    public function testLoadThrowsExceptionWithInvalidFileEvenWithoutSchemaValidation($filePath)
    {
        self::expectException(\InvalidArgumentException::class);
        $loader = new CustomXmlFileLoader(new FileLocator([__DIR__.'/../Fixtures']));
        $loader->load($filePath);
    }

    public function getPathsToInvalidFiles()
    {
        return [
            ['nonvalidnode.xml'],
            ['nonvalidroute.xml'],
            ['nonvalid.xml'],
            ['missing_id.xml'],
            ['missing_path.xml'],
            ['nonvalid-deprecated-route.xml'],
            ['alias/invalid-deprecated-no-package.xml'],
            ['alias/invalid-deprecated-no-version.xml'],
        ];
    }

    public function testDocTypeIsNotAllowed()
    {
        self::expectException(\InvalidArgumentException::class);
        self::expectExceptionMessage('Document types are not allowed.');
        $loader = new XmlFileLoader(new FileLocator([__DIR__.'/../Fixtures']));
        $loader->load('withdoctype.xml');
    }

    public function testNullValues()
    {
        $loader = new XmlFileLoader(new FileLocator([__DIR__.'/../Fixtures']));
        $routeCollection = $loader->load('null_values.xml');
        $route = $routeCollection->get('blog_show');

        self::assertTrue($route->hasDefault('foo'));
        self::assertNull($route->getDefault('foo'));
        self::assertTrue($route->hasDefault('bar'));
        self::assertNull($route->getDefault('bar'));
        self::assertEquals('foo', $route->getDefault('foobar'));
        self::assertEquals('bar', $route->getDefault('baz'));
    }

    public function testScalarDataTypeDefaults()
    {
        $loader = new XmlFileLoader(new FileLocator([__DIR__.'/../Fixtures']));
        $routeCollection = $loader->load('scalar_defaults.xml');
        $route = $routeCollection->get('blog');

        self::assertSame([
            '_controller' => 'AcmeBlogBundle:Blog:index',
            'slug' => null,
            'published' => true,
            'page' => 1,
            'price' => 3.5,
            'archived' => false,
            'free' => true,
            'locked' => false,
            'foo' => null,
            'bar' => null,
        ], $route->getDefaults());
    }

    public function testListDefaults()
    {
        $loader = new XmlFileLoader(new FileLocator([__DIR__.'/../Fixtures']));
        $routeCollection = $loader->load('list_defaults.xml');
        $route = $routeCollection->get('blog');

        self::assertSame([
            '_controller' => 'AcmeBlogBundle:Blog:index',
            'values' => [true, 1, 3.5, 'foo'],
        ], $route->getDefaults());
    }

    public function testListInListDefaults()
    {
        $loader = new XmlFileLoader(new FileLocator([__DIR__.'/../Fixtures']));
        $routeCollection = $loader->load('list_in_list_defaults.xml');
        $route = $routeCollection->get('blog');

        self::assertSame([
            '_controller' => 'AcmeBlogBundle:Blog:index',
            'values' => [[true, 1, 3.5, 'foo']],
        ], $route->getDefaults());
    }

    public function testListInMapDefaults()
    {
        $loader = new XmlFileLoader(new FileLocator([__DIR__.'/../Fixtures']));
        $routeCollection = $loader->load('list_in_map_defaults.xml');
        $route = $routeCollection->get('blog');

        self::assertSame([
            '_controller' => 'AcmeBlogBundle:Blog:index',
            'values' => ['list' => [true, 1, 3.5, 'foo']],
        ], $route->getDefaults());
    }

    public function testMapDefaults()
    {
        $loader = new XmlFileLoader(new FileLocator([__DIR__.'/../Fixtures']));
        $routeCollection = $loader->load('map_defaults.xml');
        $route = $routeCollection->get('blog');

        self::assertSame([
            '_controller' => 'AcmeBlogBundle:Blog:index',
            'values' => [
                'public' => true,
                'page' => 1,
                'price' => 3.5,
                'title' => 'foo',
            ],
        ], $route->getDefaults());
    }

    public function testMapInListDefaults()
    {
        $loader = new XmlFileLoader(new FileLocator([__DIR__.'/../Fixtures']));
        $routeCollection = $loader->load('map_in_list_defaults.xml');
        $route = $routeCollection->get('blog');

        self::assertSame([
            '_controller' => 'AcmeBlogBundle:Blog:index',
            'values' => [[
                'public' => true,
                'page' => 1,
                'price' => 3.5,
                'title' => 'foo',
            ]],
        ], $route->getDefaults());
    }

    public function testMapInMapDefaults()
    {
        $loader = new XmlFileLoader(new FileLocator([__DIR__.'/../Fixtures']));
        $routeCollection = $loader->load('map_in_map_defaults.xml');
        $route = $routeCollection->get('blog');

        self::assertSame([
            '_controller' => 'AcmeBlogBundle:Blog:index',
            'values' => ['map' => [
                'public' => true,
                'page' => 1,
                'price' => 3.5,
                'title' => 'foo',
            ]],
        ], $route->getDefaults());
    }

    public function testNullValuesInList()
    {
        $loader = new XmlFileLoader(new FileLocator([__DIR__.'/../Fixtures']));
        $routeCollection = $loader->load('list_null_values.xml');
        $route = $routeCollection->get('blog');

        self::assertSame([null, null, null, null, null, null], $route->getDefault('list'));
    }

    public function testNullValuesInMap()
    {
        $loader = new XmlFileLoader(new FileLocator([__DIR__.'/../Fixtures']));
        $routeCollection = $loader->load('map_null_values.xml');
        $route = $routeCollection->get('blog');

        self::assertSame([
            'boolean' => null,
            'integer' => null,
            'float' => null,
            'string' => null,
            'list' => null,
            'map' => null,
        ], $route->getDefault('map'));
    }

    public function testLoadRouteWithControllerAttribute()
    {
        $loader = new XmlFileLoader(new FileLocator([__DIR__.'/../Fixtures/controller']));
        $routeCollection = $loader->load('routing.xml');

        $route = $routeCollection->get('app_homepage');

        self::assertSame('AppBundle:Homepage:show', $route->getDefault('_controller'));
    }

    public function testLoadRouteWithoutControllerAttribute()
    {
        $loader = new XmlFileLoader(new FileLocator([__DIR__.'/../Fixtures/controller']));
        $routeCollection = $loader->load('routing.xml');

        $route = $routeCollection->get('app_logout');

        self::assertNull($route->getDefault('_controller'));
    }

    public function testLoadRouteWithControllerSetInDefaults()
    {
        $loader = new XmlFileLoader(new FileLocator([__DIR__.'/../Fixtures/controller']));
        $routeCollection = $loader->load('routing.xml');

        $route = $routeCollection->get('app_blog');

        self::assertSame('AppBundle:Blog:list', $route->getDefault('_controller'));
    }

    public function testOverrideControllerInDefaults()
    {
        self::expectException(\InvalidArgumentException::class);
        self::expectExceptionMessageMatches('/The routing file "[^"]*" must not specify both the "controller" attribute and the defaults key "_controller" for "app_blog"/');
        $loader = new XmlFileLoader(new FileLocator([__DIR__.'/../Fixtures/controller']));
        $loader->load('override_defaults.xml');
    }

    /**
     * @dataProvider provideFilesImportingRoutesWithControllers
     */
    public function testImportRouteWithController($file)
    {
        $loader = new XmlFileLoader(new FileLocator([__DIR__.'/../Fixtures/controller']));
        $routeCollection = $loader->load($file);

        $route = $routeCollection->get('app_homepage');
        self::assertSame('FrameworkBundle:Template:template', $route->getDefault('_controller'));

        $route = $routeCollection->get('app_blog');
        self::assertSame('FrameworkBundle:Template:template', $route->getDefault('_controller'));

        $route = $routeCollection->get('app_logout');
        self::assertSame('FrameworkBundle:Template:template', $route->getDefault('_controller'));
    }

    public function provideFilesImportingRoutesWithControllers()
    {
        yield ['import_controller.xml'];
        yield ['import__controller.xml'];
    }

    public function testImportWithOverriddenController()
    {
        self::expectException(\InvalidArgumentException::class);
        self::expectExceptionMessageMatches('/The routing file "[^"]*" must not specify both the "controller" attribute and the defaults key "_controller" for the "import" tag/');
        $loader = new XmlFileLoader(new FileLocator([__DIR__.'/../Fixtures/controller']));
        $loader->load('import_override_defaults.xml');
    }

    public function testImportRouteWithGlobMatchingSingleFile()
    {
        $loader = new XmlFileLoader(new FileLocator([__DIR__.'/../Fixtures/glob']));
        $routeCollection = $loader->load('import_single.xml');

        $route = $routeCollection->get('bar_route');
        self::assertSame('AppBundle:Bar:view', $route->getDefault('_controller'));
    }

    public function testImportRouteWithGlobMatchingMultipleFiles()
    {
        $loader = new XmlFileLoader(new FileLocator([__DIR__.'/../Fixtures/glob']));
        $routeCollection = $loader->load('import_multiple.xml');

        $route = $routeCollection->get('bar_route');
        self::assertSame('AppBundle:Bar:view', $route->getDefault('_controller'));

        $route = $routeCollection->get('baz_route');
        self::assertSame('AppBundle:Baz:view', $route->getDefault('_controller'));
    }

    public function testImportRouteWithNamePrefix()
    {
        $loader = new XmlFileLoader(new FileLocator([__DIR__.'/../Fixtures/import_with_name_prefix']));
        $routeCollection = $loader->load('routing.xml');

        self::assertNotNull($routeCollection->get('app_blog'));
        self::assertEquals('/blog', $routeCollection->get('app_blog')->getPath());
        self::assertNotNull($routeCollection->get('api_app_blog'));
        self::assertEquals('/api/blog', $routeCollection->get('api_app_blog')->getPath());
    }

    public function testImportRouteWithNoTrailingSlash()
    {
        $loader = new XmlFileLoader(new FileLocator([__DIR__.'/../Fixtures/import_with_no_trailing_slash']));
        $routeCollection = $loader->load('routing.xml');

        self::assertEquals('/slash/', $routeCollection->get('a_app_homepage')->getPath());
        self::assertEquals('/no-slash', $routeCollection->get('b_app_homepage')->getPath());
    }

    public function testImportingRoutesWithHostsInImporter()
    {
        $loader = new XmlFileLoader(new FileLocator([__DIR__.'/../Fixtures/locale_and_host']));
        $routes = $loader->load('importer-with-host.xml');

        $expectedRoutes = require __DIR__.'/../Fixtures/locale_and_host/import-with-host-expected-collection.php';

        self::assertEquals($expectedRoutes('xml'), $routes);
    }

    public function testImportingRoutesWithLocalesAndHostInImporter()
    {
        $loader = new XmlFileLoader(new FileLocator([__DIR__.'/../Fixtures/locale_and_host']));
        $routes = $loader->load('importer-with-locale-and-host.xml');

        $expectedRoutes = require __DIR__.'/../Fixtures/locale_and_host/import-with-locale-and-host-expected-collection.php';

        self::assertEquals($expectedRoutes('xml'), $routes);
    }

    public function testImportingRoutesWithoutHostsInImporter()
    {
        $loader = new XmlFileLoader(new FileLocator([__DIR__.'/../Fixtures/locale_and_host']));
        $routes = $loader->load('importer-without-host.xml');

        $expectedRoutes = require __DIR__.'/../Fixtures/locale_and_host/import-without-host-expected-collection.php';

        self::assertEquals($expectedRoutes('xml'), $routes);
    }

    public function testImportingRoutesWithSingleHostsInImporter()
    {
        $loader = new XmlFileLoader(new FileLocator([__DIR__.'/../Fixtures/locale_and_host']));
        $routes = $loader->load('importer-with-single-host.xml');

        $expectedRoutes = require __DIR__.'/../Fixtures/locale_and_host/import-with-single-host-expected-collection.php';

        self::assertEquals($expectedRoutes('xml'), $routes);
    }

    public function testWhenEnv()
    {
        $loader = new XmlFileLoader(new FileLocator([__DIR__.'/../Fixtures']), 'some-env');
        $routes = $loader->load('when-env.xml');

        self::assertSame(['b', 'a'], array_keys($routes->all()));
        self::assertSame('/b', $routes->get('b')->getPath());
        self::assertSame('/a1', $routes->get('a')->getPath());
    }

    public function testImportingAliases()
    {
        $loader = new XmlFileLoader(new FileLocator([__DIR__.'/../Fixtures/alias']));
        $routes = $loader->load('alias.xml');

        $expectedRoutes = require __DIR__.'/../Fixtures/alias/expected.php';

        self::assertEquals($expectedRoutes('xml'), $routes);
    }
}
