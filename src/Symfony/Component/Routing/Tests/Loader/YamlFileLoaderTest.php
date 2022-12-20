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
use Symfony\Component\Routing\Loader\YamlFileLoader;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

class YamlFileLoaderTest extends TestCase
{
    public function testSupports()
    {
        $loader = new YamlFileLoader(self::createMock(FileLocator::class));

        self::assertTrue($loader->supports('foo.yml'), '->supports() returns true if the resource is loadable');
        self::assertTrue($loader->supports('foo.yaml'), '->supports() returns true if the resource is loadable');
        self::assertFalse($loader->supports('foo.foo'), '->supports() returns true if the resource is loadable');

        self::assertTrue($loader->supports('foo.yml', 'yaml'), '->supports() checks the resource type if specified');
        self::assertTrue($loader->supports('foo.yaml', 'yaml'), '->supports() checks the resource type if specified');
        self::assertFalse($loader->supports('foo.yml', 'foo'), '->supports() checks the resource type if specified');
    }

    public function testLoadDoesNothingIfEmpty()
    {
        $loader = new YamlFileLoader(new FileLocator([__DIR__.'/../Fixtures']));
        $collection = $loader->load('empty.yml');

        self::assertEquals([], $collection->all());
        self::assertEquals([new FileResource(realpath(__DIR__.'/../Fixtures/empty.yml'))], $collection->getResources());
    }

    /**
     * @dataProvider getPathsToInvalidFiles
     */
    public function testLoadThrowsExceptionWithInvalidFile($filePath)
    {
        self::expectException(\InvalidArgumentException::class);
        $loader = new YamlFileLoader(new FileLocator([__DIR__.'/../Fixtures']));
        $loader->load($filePath);
    }

    public function getPathsToInvalidFiles()
    {
        return [
            ['nonvalid.yml'],
            ['nonvalid2.yml'],
            ['incomplete.yml'],
            ['nonvalidkeys.yml'],
            ['nonesense_resource_plus_path.yml'],
            ['nonesense_type_without_resource.yml'],
            ['bad_format.yml'],
            ['alias/invalid-alias.yaml'],
            ['alias/invalid-deprecated-no-package.yaml'],
            ['alias/invalid-deprecated-no-version.yaml'],
        ];
    }

    public function testLoadSpecialRouteName()
    {
        $loader = new YamlFileLoader(new FileLocator([__DIR__.'/../Fixtures']));
        $routeCollection = $loader->load('special_route_name.yml');
        $route = $routeCollection->get('#$péß^a|');

        self::assertInstanceOf(Route::class, $route);
        self::assertSame('/true', $route->getPath());
    }

    public function testLoadWithRoute()
    {
        $loader = new YamlFileLoader(new FileLocator([__DIR__.'/../Fixtures']));
        $routeCollection = $loader->load('validpattern.yml');
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

    public function testLoadWithResource()
    {
        $loader = new YamlFileLoader(new FileLocator([__DIR__.'/../Fixtures']));
        $routeCollection = $loader->load('validresource.yml');
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

    public function testLoadRouteWithControllerAttribute()
    {
        $loader = new YamlFileLoader(new FileLocator([__DIR__.'/../Fixtures/controller']));
        $routeCollection = $loader->load('routing.yml');

        $route = $routeCollection->get('app_homepage');

        self::assertSame('AppBundle:Homepage:show', $route->getDefault('_controller'));
    }

    public function testLoadRouteWithoutControllerAttribute()
    {
        $loader = new YamlFileLoader(new FileLocator([__DIR__.'/../Fixtures/controller']));
        $routeCollection = $loader->load('routing.yml');

        $route = $routeCollection->get('app_logout');

        self::assertNull($route->getDefault('_controller'));
    }

    public function testLoadRouteWithControllerSetInDefaults()
    {
        $loader = new YamlFileLoader(new FileLocator([__DIR__.'/../Fixtures/controller']));
        $routeCollection = $loader->load('routing.yml');

        $route = $routeCollection->get('app_blog');

        self::assertSame('AppBundle:Blog:list', $route->getDefault('_controller'));
    }

    public function testOverrideControllerInDefaults()
    {
        self::expectException(\InvalidArgumentException::class);
        self::expectExceptionMessageMatches('/The routing file "[^"]*" must not specify both the "controller" key and the defaults key "_controller" for "app_blog"/');
        $loader = new YamlFileLoader(new FileLocator([__DIR__.'/../Fixtures/controller']));
        $loader->load('override_defaults.yml');
    }

    /**
     * @dataProvider provideFilesImportingRoutesWithControllers
     */
    public function testImportRouteWithController($file)
    {
        $loader = new YamlFileLoader(new FileLocator([__DIR__.'/../Fixtures/controller']));
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
        yield ['import_controller.yml'];
        yield ['import__controller.yml'];
    }

    public function testImportWithOverriddenController()
    {
        self::expectException(\InvalidArgumentException::class);
        self::expectExceptionMessageMatches('/The routing file "[^"]*" must not specify both the "controller" key and the defaults key "_controller" for "_static"/');
        $loader = new YamlFileLoader(new FileLocator([__DIR__.'/../Fixtures/controller']));
        $loader->load('import_override_defaults.yml');
    }

    public function testImportRouteWithGlobMatchingSingleFile()
    {
        $loader = new YamlFileLoader(new FileLocator([__DIR__.'/../Fixtures/glob']));
        $routeCollection = $loader->load('import_single.yml');

        $route = $routeCollection->get('bar_route');
        self::assertSame('AppBundle:Bar:view', $route->getDefault('_controller'));
    }

    public function testImportRouteWithGlobMatchingMultipleFiles()
    {
        $loader = new YamlFileLoader(new FileLocator([__DIR__.'/../Fixtures/glob']));
        $routeCollection = $loader->load('import_multiple.yml');

        $route = $routeCollection->get('bar_route');
        self::assertSame('AppBundle:Bar:view', $route->getDefault('_controller'));

        $route = $routeCollection->get('baz_route');
        self::assertSame('AppBundle:Baz:view', $route->getDefault('_controller'));
    }

    public function testImportRouteWithNamePrefix()
    {
        $loader = new YamlFileLoader(new FileLocator([__DIR__.'/../Fixtures/import_with_name_prefix']));
        $routeCollection = $loader->load('routing.yml');

        self::assertNotNull($routeCollection->get('app_blog'));
        self::assertEquals('/blog', $routeCollection->get('app_blog')->getPath());
        self::assertNotNull($routeCollection->get('api_app_blog'));
        self::assertEquals('/api/blog', $routeCollection->get('api_app_blog')->getPath());
    }

    public function testRemoteSourcesAreNotAccepted()
    {
        $loader = new YamlFileLoader(new FileLocatorStub());
        self::expectException(\InvalidArgumentException::class);
        $loader->load('http://remote.com/here.yml');
    }

    public function testLoadingRouteWithDefaults()
    {
        $loader = new YamlFileLoader(new FileLocator([__DIR__.'/../Fixtures']));
        $routes = $loader->load('defaults.yml');

        self::assertCount(1, $routes);

        $defaultsRoute = $routes->get('defaults');

        self::assertSame('/defaults', $defaultsRoute->getPath());
        self::assertSame('en', $defaultsRoute->getDefault('_locale'));
        self::assertSame('html', $defaultsRoute->getDefault('_format'));
        self::assertTrue($defaultsRoute->getDefault('_stateless'));
    }

    public function testLoadingImportedRoutesWithDefaults()
    {
        $loader = new YamlFileLoader(new FileLocator([__DIR__.'/../Fixtures']));
        $routes = $loader->load('importer-with-defaults.yml');

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

        $expectedRoutes->addResource(new FileResource(__DIR__.'/../Fixtures/imported-with-defaults.yml'));
        $expectedRoutes->addResource(new FileResource(__DIR__.'/../Fixtures/importer-with-defaults.yml'));

        self::assertEquals($expectedRoutes, $routes);
    }

    public function testLoadingUtf8Route()
    {
        $loader = new YamlFileLoader(new FileLocator([__DIR__.'/../Fixtures/localized']));
        $routes = $loader->load('utf8.yml');

        self::assertCount(2, $routes);

        $expectedRoutes = new RouteCollection();
        $expectedRoutes->add('some_route', new Route('/'));

        $expectedRoutes->add('some_utf8_route', $route = new Route('/utf8'));
        $route->setOption('utf8', true);

        $expectedRoutes->addResource(new FileResource(__DIR__.'/../Fixtures/localized/utf8.yml'));

        self::assertEquals($expectedRoutes, $routes);
    }

    public function testLoadingUtf8ImportedRoutes()
    {
        $loader = new YamlFileLoader(new FileLocator([__DIR__.'/../Fixtures/localized']));
        $routes = $loader->load('importer-with-utf8.yml');

        self::assertCount(2, $routes);

        $expectedRoutes = new RouteCollection();
        $expectedRoutes->add('utf8_one', $one = new Route('/one'));
        $one->setOption('utf8', true);

        $expectedRoutes->add('utf8_two', $two = new Route('/two'));
        $two->setOption('utf8', true);

        $expectedRoutes->addResource(new FileResource(__DIR__.'/../Fixtures/localized/imported-with-utf8.yml'));
        $expectedRoutes->addResource(new FileResource(__DIR__.'/../Fixtures/localized/importer-with-utf8.yml'));

        self::assertEquals($expectedRoutes, $routes);
    }

    public function testLoadingLocalizedRoute()
    {
        $loader = new YamlFileLoader(new FileLocator([__DIR__.'/../Fixtures/localized']));
        $routes = $loader->load('localized-route.yml');

        self::assertCount(3, $routes);
    }

    public function testImportingRoutesFromDefinition()
    {
        $loader = new YamlFileLoader(new FileLocator([__DIR__.'/../Fixtures/localized']));
        $routes = $loader->load('importing-localized-route.yml');

        self::assertCount(3, $routes);
        self::assertEquals('/nl', $routes->get('home.nl')->getPath());
        self::assertEquals('/en', $routes->get('home.en')->getPath());
        self::assertEquals('/here', $routes->get('not_localized')->getPath());
    }

    public function testImportingRoutesWithLocales()
    {
        $loader = new YamlFileLoader(new FileLocator([__DIR__.'/../Fixtures/localized']));
        $routes = $loader->load('importer-with-locale.yml');

        self::assertCount(2, $routes);
        self::assertEquals('/nl/voorbeeld', $routes->get('imported.nl')->getPath());
        self::assertEquals('/en/example', $routes->get('imported.en')->getPath());

        self::assertEquals('nl', $routes->get('imported.nl')->getRequirement('_locale'));
        self::assertEquals('en', $routes->get('imported.en')->getRequirement('_locale'));
    }

    public function testImportingNonLocalizedRoutesWithLocales()
    {
        $loader = new YamlFileLoader(new FileLocator([__DIR__.'/../Fixtures/localized']));
        $routes = $loader->load('importer-with-locale-imports-non-localized-route.yml');

        self::assertCount(2, $routes);
        self::assertEquals('/nl/imported', $routes->get('imported.nl')->getPath());
        self::assertEquals('/en/imported', $routes->get('imported.en')->getPath());

        self::assertSame('nl', $routes->get('imported.nl')->getRequirement('_locale'));
        self::assertSame('en', $routes->get('imported.en')->getRequirement('_locale'));
    }

    public function testImportingRoutesWithOfficialLocales()
    {
        $loader = new YamlFileLoader(new FileLocator([__DIR__.'/../Fixtures/localized']));
        $routes = $loader->load('officially_formatted_locales.yml');

        self::assertCount(3, $routes);
        self::assertEquals('/omelette-au-fromage', $routes->get('official.fr.UTF-8')->getPath());
        self::assertEquals('/eu-não-sou-espanhol', $routes->get('official.pt-PT')->getPath());
        self::assertEquals('/churrasco', $routes->get('official.pt_BR')->getPath());
    }

    public function testImportingRoutesFromDefinitionMissingLocalePrefix()
    {
        $loader = new YamlFileLoader(new FileLocator([__DIR__.'/../Fixtures/localized']));
        self::expectException(\InvalidArgumentException::class);
        $loader->load('missing-locale-in-importer.yml');
    }

    public function testImportingRouteWithoutPathOrLocales()
    {
        $loader = new YamlFileLoader(new FileLocator([__DIR__.'/../Fixtures/localized']));
        self::expectException(\InvalidArgumentException::class);
        $loader->load('route-without-path-or-locales.yml');
    }

    public function testImportingWithControllerDefault()
    {
        $loader = new YamlFileLoader(new FileLocator([__DIR__.'/../Fixtures/localized']));
        $routes = $loader->load('importer-with-controller-default.yml');
        self::assertCount(3, $routes);
        self::assertEquals('DefaultController::defaultAction', $routes->get('home.en')->getDefault('_controller'));
        self::assertEquals('DefaultController::defaultAction', $routes->get('home.nl')->getDefault('_controller'));
        self::assertEquals('DefaultController::defaultAction', $routes->get('not_localized')->getDefault('_controller'));
    }

    public function testImportRouteWithNoTrailingSlash()
    {
        $loader = new YamlFileLoader(new FileLocator([__DIR__.'/../Fixtures/import_with_no_trailing_slash']));
        $routeCollection = $loader->load('routing.yml');

        self::assertEquals('/slash/', $routeCollection->get('a_app_homepage')->getPath());
        self::assertEquals('/no-slash', $routeCollection->get('b_app_homepage')->getPath());
    }

    public function testRequirementsWithoutPlaceholderName()
    {
        self::expectException(\InvalidArgumentException::class);
        self::expectExceptionMessage('A placeholder name must be a string (0 given). Did you forget to specify the placeholder key for the requirement "\\d+" of route "foo"');

        $loader = new YamlFileLoader(new FileLocator([__DIR__.'/../Fixtures']));
        $loader->load('requirements_without_placeholder_name.yml');
    }

    public function testImportingRoutesWithHostsInImporter()
    {
        $loader = new YamlFileLoader(new FileLocator([__DIR__.'/../Fixtures/locale_and_host']));
        $routes = $loader->load('importer-with-host.yml');

        $expectedRoutes = require __DIR__.'/../Fixtures/locale_and_host/import-with-host-expected-collection.php';

        self::assertEquals($expectedRoutes('yml'), $routes);
    }

    public function testImportingRoutesWithLocalesAndHostInImporter()
    {
        $loader = new YamlFileLoader(new FileLocator([__DIR__.'/../Fixtures/locale_and_host']));
        $routes = $loader->load('importer-with-locale-and-host.yml');

        $expectedRoutes = require __DIR__.'/../Fixtures/locale_and_host/import-with-locale-and-host-expected-collection.php';

        self::assertEquals($expectedRoutes('yml'), $routes);
    }

    public function testImportingRoutesWithoutHostInImporter()
    {
        $loader = new YamlFileLoader(new FileLocator([__DIR__.'/../Fixtures/locale_and_host']));
        $routes = $loader->load('importer-without-host.yml');

        $expectedRoutes = require __DIR__.'/../Fixtures/locale_and_host/import-without-host-expected-collection.php';

        self::assertEquals($expectedRoutes('yml'), $routes);
    }

    public function testImportingRoutesWithSingleHostInImporter()
    {
        $loader = new YamlFileLoader(new FileLocator([__DIR__.'/../Fixtures/locale_and_host']));
        $routes = $loader->load('importer-with-single-host.yml');

        $expectedRoutes = require __DIR__.'/../Fixtures/locale_and_host/import-with-single-host-expected-collection.php';

        self::assertEquals($expectedRoutes('yml'), $routes);
    }

    public function testWhenEnv()
    {
        $loader = new YamlFileLoader(new FileLocator([__DIR__.'/../Fixtures']), 'some-env');
        $routes = $loader->load('when-env.yml');

        self::assertSame(['b', 'a'], array_keys($routes->all()));
        self::assertSame('/b', $routes->get('b')->getPath());
        self::assertSame('/a1', $routes->get('a')->getPath());
    }

    public function testImportingAliases()
    {
        $loader = new YamlFileLoader(new FileLocator([__DIR__.'/../Fixtures/alias']));
        $routes = $loader->load('alias.yaml');

        $expectedRoutes = require __DIR__.'/../Fixtures/alias/expected.php';

        self::assertEquals($expectedRoutes('yaml'), $routes);
    }
}
