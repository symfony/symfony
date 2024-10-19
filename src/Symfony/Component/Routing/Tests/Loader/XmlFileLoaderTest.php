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
use Symfony\Component\Config\Loader\LoaderResolver;
use Symfony\Component\Config\Resource\FileResource;
use Symfony\Component\Routing\Loader\AttributeClassLoader;
use Symfony\Component\Routing\Loader\Psr4DirectoryLoader;
use Symfony\Component\Routing\Loader\XmlFileLoader;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;
use Symfony\Component\Routing\Tests\Fixtures\CustomXmlFileLoader;
use Symfony\Component\Routing\Tests\Fixtures\Psr4Controllers\MyController;

class XmlFileLoaderTest extends TestCase
{
    public function testSupports()
    {
        $loader = new XmlFileLoader($this->createMock(FileLocator::class));

        $this->assertTrue($loader->supports('foo.xml'), '->supports() returns true if the resource is loadable');
        $this->assertFalse($loader->supports('foo.foo'), '->supports() returns true if the resource is loadable');

        $this->assertTrue($loader->supports('foo.xml', 'xml'), '->supports() checks the resource type if specified');
        $this->assertFalse($loader->supports('foo.xml', 'foo'), '->supports() checks the resource type if specified');
    }

    public function testLoadWithRoute()
    {
        $loader = new XmlFileLoader(new FileLocator([__DIR__.'/../Fixtures']));
        $routeCollection = $loader->load('validpattern.xml');
        $route = $routeCollection->get('blog_show');

        $this->assertInstanceOf(Route::class, $route);
        $this->assertSame('/blog/{slug}', $route->getPath());
        $this->assertSame('{locale}.example.com', $route->getHost());
        $this->assertSame('MyBundle:Blog:show', $route->getDefault('_controller'));
        $this->assertSame('\w+', $route->getRequirement('locale'));
        $this->assertSame('RouteCompiler', $route->getOption('compiler_class'));
        $this->assertEquals(['GET', 'POST', 'PUT', 'OPTIONS'], $route->getMethods());
        $this->assertEquals(['https'], $route->getSchemes());
        $this->assertEquals('context.getMethod() == "GET"', $route->getCondition());
        $this->assertTrue($route->getDefault('_stateless'));
    }

    public function testLoadWithNamespacePrefix()
    {
        $loader = new XmlFileLoader(new FileLocator([__DIR__.'/../Fixtures']));
        $routeCollection = $loader->load('namespaceprefix.xml');

        $this->assertCount(1, $routeCollection->all(), 'One route is loaded');

        $route = $routeCollection->get('blog_show');
        $this->assertSame('/blog/{slug}', $route->getPath());
        $this->assertSame('{_locale}.example.com', $route->getHost());
        $this->assertSame('MyBundle:Blog:show', $route->getDefault('_controller'));
        $this->assertSame('\w+', $route->getRequirement('slug'));
        $this->assertSame('en|fr|de', $route->getRequirement('_locale'));
        $this->assertNull($route->getDefault('slug'));
        $this->assertSame('RouteCompiler', $route->getOption('compiler_class'));
        $this->assertSame(1, $route->getDefault('page'));
    }

    public function testLoadWithImport()
    {
        $loader = new XmlFileLoader(new FileLocator([__DIR__.'/../Fixtures']));
        $routeCollection = $loader->load('validresource.xml');
        $routes = $routeCollection->all();

        $this->assertCount(2, $routes, 'Two routes are loaded');
        $this->assertContainsOnly('Symfony\Component\Routing\Route', $routes);

        foreach ($routes as $route) {
            $this->assertSame('/{foo}/blog/{slug}', $route->getPath());
            $this->assertSame('123', $route->getDefault('foo'));
            $this->assertSame('\d+', $route->getRequirement('foo'));
            $this->assertSame('bar', $route->getOption('foo'));
            $this->assertSame('', $route->getHost());
            $this->assertSame('(context.getMethod() == "GET") and (context.getMethod() == "POST")', $route->getCondition());
        }
    }

    public function testLoadingRouteWithDefaults()
    {
        $loader = new XmlFileLoader(new FileLocator([__DIR__.'/../Fixtures']));
        $routes = $loader->load('defaults.xml');

        $this->assertCount(1, $routes);

        $defaultsRoute = $routes->get('defaults');

        $this->assertSame('/defaults', $defaultsRoute->getPath());
        $this->assertSame('en', $defaultsRoute->getDefault('_locale'));
        $this->assertSame('html', $defaultsRoute->getDefault('_format'));
        $this->assertTrue($defaultsRoute->getDefault('_stateless'));
    }

    public function testLoadingImportedRoutesWithDefaults()
    {
        $loader = new XmlFileLoader(new FileLocator([__DIR__.'/../Fixtures']));
        $routes = $loader->load('importer-with-defaults.xml');

        $this->assertCount(2, $routes);

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

        $this->assertEquals($expectedRoutes, $routes);
    }

    public function testLoadingUtf8Route()
    {
        $loader = new XmlFileLoader(new FileLocator([__DIR__.'/../Fixtures/localized']));
        $routes = $loader->load('utf8.xml');

        $this->assertCount(2, $routes);

        $expectedRoutes = new RouteCollection();
        $expectedRoutes->add('app_utf8', $route = new Route('/utf8'));
        $route->setOption('utf8', true);

        $expectedRoutes->add('app_no_utf8', $route = new Route('/no-utf8'));
        $route->setOption('utf8', false);

        $expectedRoutes->addResource(new FileResource(__DIR__.'/../Fixtures/localized/utf8.xml'));

        $this->assertEquals($expectedRoutes, $routes);
    }

    public function testLoadingUtf8ImportedRoutes()
    {
        $loader = new XmlFileLoader(new FileLocator([__DIR__.'/../Fixtures/localized']));
        $routes = $loader->load('importer-with-utf8.xml');

        $this->assertCount(2, $routes);

        $expectedRoutes = new RouteCollection();
        $expectedRoutes->add('utf8_one', $one = new Route('/one'));
        $one->setOption('utf8', true);

        $expectedRoutes->add('utf8_two', $two = new Route('/two'));
        $two->setOption('utf8', true);

        $expectedRoutes->addResource(new FileResource(__DIR__.'/../Fixtures/localized/imported-with-utf8.xml'));
        $expectedRoutes->addResource(new FileResource(__DIR__.'/../Fixtures/localized/importer-with-utf8.xml'));

        $this->assertEquals($expectedRoutes, $routes);
    }

    public function testLoadLocalized()
    {
        $loader = new XmlFileLoader(new FileLocator([__DIR__.'/../Fixtures']));
        $routeCollection = $loader->load('localized.xml');
        $routes = $routeCollection->all();

        $this->assertCount(2, $routes, 'Two routes are loaded');
        $this->assertContainsOnly('Symfony\Component\Routing\Route', $routes);

        $this->assertEquals('/route', $routeCollection->get('localized.fr')->getPath());
        $this->assertEquals('/path', $routeCollection->get('localized.en')->getPath());
    }

    public function testLocalizedImports()
    {
        $loader = new XmlFileLoader(new FileLocator([__DIR__.'/../Fixtures/localized']));
        $routeCollection = $loader->load('importer-with-locale.xml');
        $routes = $routeCollection->all();

        $this->assertCount(2, $routes, 'Two routes are loaded');
        $this->assertContainsOnly('Symfony\Component\Routing\Route', $routes);

        $this->assertEquals('/le-prefix/le-suffix', $routeCollection->get('imported.fr')->getPath());
        $this->assertEquals('/the-prefix/suffix', $routeCollection->get('imported.en')->getPath());

        $this->assertEquals('fr', $routeCollection->get('imported.fr')->getRequirement('_locale'));
        $this->assertEquals('en', $routeCollection->get('imported.en')->getRequirement('_locale'));
    }

    public function testLocalizedImportsOfNotLocalizedRoutes()
    {
        $loader = new XmlFileLoader(new FileLocator([__DIR__.'/../Fixtures/localized']));
        $routeCollection = $loader->load('importer-with-locale-imports-non-localized-route.xml');
        $routes = $routeCollection->all();

        $this->assertCount(2, $routes, 'Two routes are loaded');
        $this->assertContainsOnly('Symfony\Component\Routing\Route', $routes);

        $this->assertEquals('/le-prefix/suffix', $routeCollection->get('imported.fr')->getPath());
        $this->assertEquals('/the-prefix/suffix', $routeCollection->get('imported.en')->getPath());

        $this->assertSame('fr', $routeCollection->get('imported.fr')->getRequirement('_locale'));
        $this->assertSame('en', $routeCollection->get('imported.en')->getRequirement('_locale'));
    }

    /**
     * @dataProvider getPathsToInvalidFiles
     */
    public function testLoadThrowsExceptionWithInvalidFile($filePath)
    {
        $loader = new XmlFileLoader(new FileLocator([__DIR__.'/../Fixtures']));

        $this->expectException(\InvalidArgumentException::class);

        $loader->load($filePath);
    }

    /**
     * @dataProvider getPathsToInvalidFiles
     */
    public function testLoadThrowsExceptionWithInvalidFileEvenWithoutSchemaValidation(string $filePath)
    {
        $loader = new CustomXmlFileLoader(new FileLocator([__DIR__.'/../Fixtures']));

        $this->expectException(\InvalidArgumentException::class);

        $loader->load($filePath);
    }

    public static function getPathsToInvalidFiles()
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
        $loader = new XmlFileLoader(new FileLocator([__DIR__.'/../Fixtures']));

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Document types are not allowed.');

        $loader->load('withdoctype.xml');
    }

    public function testNullValues()
    {
        $loader = new XmlFileLoader(new FileLocator([__DIR__.'/../Fixtures']));
        $routeCollection = $loader->load('null_values.xml');
        $route = $routeCollection->get('blog_show');

        $this->assertTrue($route->hasDefault('foo'));
        $this->assertNull($route->getDefault('foo'));
        $this->assertTrue($route->hasDefault('bar'));
        $this->assertNull($route->getDefault('bar'));
        $this->assertEquals('foo', $route->getDefault('foobar'));
        $this->assertEquals('bar', $route->getDefault('baz'));
    }

    public function testScalarDataTypeDefaults()
    {
        $loader = new XmlFileLoader(new FileLocator([__DIR__.'/../Fixtures']));
        $routeCollection = $loader->load('scalar_defaults.xml');
        $route = $routeCollection->get('blog');

        $this->assertSame(
            [
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
            ],
            $route->getDefaults()
        );
    }

    public function testListDefaults()
    {
        $loader = new XmlFileLoader(new FileLocator([__DIR__.'/../Fixtures']));
        $routeCollection = $loader->load('list_defaults.xml');
        $route = $routeCollection->get('blog');

        $this->assertSame(
            [
                '_controller' => 'AcmeBlogBundle:Blog:index',
                'values' => [true, 1, 3.5, 'foo'],
            ],
            $route->getDefaults()
        );
    }

    public function testListInListDefaults()
    {
        $loader = new XmlFileLoader(new FileLocator([__DIR__.'/../Fixtures']));
        $routeCollection = $loader->load('list_in_list_defaults.xml');
        $route = $routeCollection->get('blog');

        $this->assertSame(
            [
                '_controller' => 'AcmeBlogBundle:Blog:index',
                'values' => [[true, 1, 3.5, 'foo']],
            ],
            $route->getDefaults()
        );
    }

    public function testListInMapDefaults()
    {
        $loader = new XmlFileLoader(new FileLocator([__DIR__.'/../Fixtures']));
        $routeCollection = $loader->load('list_in_map_defaults.xml');
        $route = $routeCollection->get('blog');

        $this->assertSame(
            [
                '_controller' => 'AcmeBlogBundle:Blog:index',
                'values' => ['list' => [true, 1, 3.5, 'foo']],
            ],
            $route->getDefaults()
        );
    }

    public function testMapDefaults()
    {
        $loader = new XmlFileLoader(new FileLocator([__DIR__.'/../Fixtures']));
        $routeCollection = $loader->load('map_defaults.xml');
        $route = $routeCollection->get('blog');

        $this->assertSame(
            [
                '_controller' => 'AcmeBlogBundle:Blog:index',
                'values' => [
                    'public' => true,
                    'page' => 1,
                    'price' => 3.5,
                    'title' => 'foo',
                ],
            ],
            $route->getDefaults()
        );
    }

    public function testMapInListDefaults()
    {
        $loader = new XmlFileLoader(new FileLocator([__DIR__.'/../Fixtures']));
        $routeCollection = $loader->load('map_in_list_defaults.xml');
        $route = $routeCollection->get('blog');

        $this->assertSame(
            [
                '_controller' => 'AcmeBlogBundle:Blog:index',
                'values' => [[
                    'public' => true,
                    'page' => 1,
                    'price' => 3.5,
                    'title' => 'foo',
                ]],
            ],
            $route->getDefaults()
        );
    }

    public function testMapInMapDefaults()
    {
        $loader = new XmlFileLoader(new FileLocator([__DIR__.'/../Fixtures']));
        $routeCollection = $loader->load('map_in_map_defaults.xml');
        $route = $routeCollection->get('blog');

        $this->assertSame(
            [
                '_controller' => 'AcmeBlogBundle:Blog:index',
                'values' => ['map' => [
                    'public' => true,
                    'page' => 1,
                    'price' => 3.5,
                    'title' => 'foo',
                ]],
            ],
            $route->getDefaults()
        );
    }

    public function testNullValuesInList()
    {
        $loader = new XmlFileLoader(new FileLocator([__DIR__.'/../Fixtures']));
        $routeCollection = $loader->load('list_null_values.xml');
        $route = $routeCollection->get('blog');

        $this->assertSame([null, null, null, null, null, null], $route->getDefault('list'));
    }

    public function testNullValuesInMap()
    {
        $loader = new XmlFileLoader(new FileLocator([__DIR__.'/../Fixtures']));
        $routeCollection = $loader->load('map_null_values.xml');
        $route = $routeCollection->get('blog');

        $this->assertSame(
            [
                'boolean' => null,
                'integer' => null,
                'float' => null,
                'string' => null,
                'list' => null,
                'map' => null,
            ],
            $route->getDefault('map')
        );
    }

    public function testLoadRouteWithControllerAttribute()
    {
        $loader = new XmlFileLoader(new FileLocator([__DIR__.'/../Fixtures/controller']));
        $routeCollection = $loader->load('routing.xml');

        $route = $routeCollection->get('app_homepage');

        $this->assertSame('AppBundle:Homepage:show', $route->getDefault('_controller'));
    }

    public function testLoadRouteWithoutControllerAttribute()
    {
        $loader = new XmlFileLoader(new FileLocator([__DIR__.'/../Fixtures/controller']));
        $routeCollection = $loader->load('routing.xml');

        $route = $routeCollection->get('app_logout');

        $this->assertNull($route->getDefault('_controller'));
    }

    public function testLoadRouteWithControllerSetInDefaults()
    {
        $loader = new XmlFileLoader(new FileLocator([__DIR__.'/../Fixtures/controller']));
        $routeCollection = $loader->load('routing.xml');

        $route = $routeCollection->get('app_blog');

        $this->assertSame('AppBundle:Blog:list', $route->getDefault('_controller'));
    }

    public function testOverrideControllerInDefaults()
    {
        $loader = new XmlFileLoader(new FileLocator([__DIR__.'/../Fixtures/controller']));

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/The routing file "[^"]*" must not specify both the "controller" attribute and the defaults key "_controller" for "app_blog"/');

        $loader->load('override_defaults.xml');
    }

    /**
     * @dataProvider provideFilesImportingRoutesWithControllers
     */
    public function testImportRouteWithController(string $file)
    {
        $loader = new XmlFileLoader(new FileLocator([__DIR__.'/../Fixtures/controller']));
        $routeCollection = $loader->load($file);

        $route = $routeCollection->get('app_homepage');
        $this->assertSame('FrameworkBundle:Template:template', $route->getDefault('_controller'));

        $route = $routeCollection->get('app_blog');
        $this->assertSame('FrameworkBundle:Template:template', $route->getDefault('_controller'));

        $route = $routeCollection->get('app_logout');
        $this->assertSame('FrameworkBundle:Template:template', $route->getDefault('_controller'));
    }

    public static function provideFilesImportingRoutesWithControllers()
    {
        yield ['import_controller.xml'];
        yield ['import__controller.xml'];
    }

    public function testImportWithOverriddenController()
    {
        $loader = new XmlFileLoader(new FileLocator([__DIR__.'/../Fixtures/controller']));

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/The routing file "[^"]*" must not specify both the "controller" attribute and the defaults key "_controller" for the "import" tag/');

        $loader->load('import_override_defaults.xml');
    }

    public function testImportRouteWithGlobMatchingSingleFile()
    {
        $loader = new XmlFileLoader(new FileLocator([__DIR__.'/../Fixtures/glob']));
        $routeCollection = $loader->load('import_single.xml');

        $route = $routeCollection->get('bar_route');
        $this->assertSame('AppBundle:Bar:view', $route->getDefault('_controller'));
    }

    public function testImportRouteWithGlobMatchingMultipleFiles()
    {
        $loader = new XmlFileLoader(new FileLocator([__DIR__.'/../Fixtures/glob']));
        $routeCollection = $loader->load('import_multiple.xml');

        $route = $routeCollection->get('bar_route');
        $this->assertSame('AppBundle:Bar:view', $route->getDefault('_controller'));

        $route = $routeCollection->get('baz_route');
        $this->assertSame('AppBundle:Baz:view', $route->getDefault('_controller'));
    }

    public function testImportRouteWithNamePrefix()
    {
        $loader = new XmlFileLoader(new FileLocator([__DIR__.'/../Fixtures/import_with_name_prefix']));
        $routeCollection = $loader->load('routing.xml');

        $this->assertNotNull($routeCollection->get('app_blog'));
        $this->assertEquals('/blog', $routeCollection->get('app_blog')->getPath());
        $this->assertNotNull($routeCollection->get('api_app_blog'));
        $this->assertEquals('/api/blog', $routeCollection->get('api_app_blog')->getPath());
    }

    public function testImportRouteWithNoTrailingSlash()
    {
        $loader = new XmlFileLoader(new FileLocator([__DIR__.'/../Fixtures/import_with_no_trailing_slash']));
        $routeCollection = $loader->load('routing.xml');

        $this->assertEquals('/slash/', $routeCollection->get('a_app_homepage')->getPath());
        $this->assertEquals('/no-slash', $routeCollection->get('b_app_homepage')->getPath());
    }

    public function testImportingRoutesWithHostsInImporter()
    {
        $loader = new XmlFileLoader(new FileLocator([__DIR__.'/../Fixtures/locale_and_host']));
        $routes = $loader->load('importer-with-host.xml');

        $expectedRoutes = require __DIR__.'/../Fixtures/locale_and_host/import-with-host-expected-collection.php';

        $this->assertEquals($expectedRoutes('xml'), $routes);
    }

    public function testImportingRoutesWithLocalesAndHostInImporter()
    {
        $loader = new XmlFileLoader(new FileLocator([__DIR__.'/../Fixtures/locale_and_host']));
        $routes = $loader->load('importer-with-locale-and-host.xml');

        $expectedRoutes = require __DIR__.'/../Fixtures/locale_and_host/import-with-locale-and-host-expected-collection.php';

        $this->assertEquals($expectedRoutes('xml'), $routes);
    }

    public function testImportingRoutesWithoutHostsInImporter()
    {
        $loader = new XmlFileLoader(new FileLocator([__DIR__.'/../Fixtures/locale_and_host']));
        $routes = $loader->load('importer-without-host.xml');

        $expectedRoutes = require __DIR__.'/../Fixtures/locale_and_host/import-without-host-expected-collection.php';

        $this->assertEquals($expectedRoutes('xml'), $routes);
    }

    public function testImportingRoutesWithSingleHostsInImporter()
    {
        $loader = new XmlFileLoader(new FileLocator([__DIR__.'/../Fixtures/locale_and_host']));
        $routes = $loader->load('importer-with-single-host.xml');

        $expectedRoutes = require __DIR__.'/../Fixtures/locale_and_host/import-with-single-host-expected-collection.php';

        $this->assertEquals($expectedRoutes('xml'), $routes);
    }

    public function testWhenEnv()
    {
        $loader = new XmlFileLoader(new FileLocator([__DIR__.'/../Fixtures']), 'some-env');
        $routes = $loader->load('when-env.xml');

        $this->assertSame(['b', 'a'], array_keys($routes->all()));
        $this->assertSame('/b', $routes->get('b')->getPath());
        $this->assertSame('/a1', $routes->get('a')->getPath());
    }

    public function testImportingAliases()
    {
        $loader = new XmlFileLoader(new FileLocator([__DIR__.'/../Fixtures/alias']));
        $routes = $loader->load('alias.xml');

        $expectedRoutes = require __DIR__.'/../Fixtures/alias/expected.php';

        $this->assertEquals($expectedRoutes('xml'), $routes);
    }

    /**
     * @dataProvider providePsr4ConfigFiles
     */
    public function testImportAttributesWithPsr4Prefix(string $configFile)
    {
        $locator = new FileLocator(\dirname(__DIR__).'/Fixtures');
        new LoaderResolver([
            $loader = new XmlFileLoader($locator),
            new Psr4DirectoryLoader($locator),
            new class extends AttributeClassLoader {
                protected function configureRoute(Route $route, \ReflectionClass $class, \ReflectionMethod $method, object $annot): void
                {
                    $route->setDefault('_controller', $class->getName().'::'.$method->getName());
                }
            },
        ]);

        $route = $loader->load($configFile)->get('my_route');
        $this->assertSame('/my-prefix/my/route', $route->getPath());
        $this->assertSame(MyController::class.'::__invoke', $route->getDefault('_controller'));
    }

    public static function providePsr4ConfigFiles(): array
    {
        return [
            ['psr4-attributes.xml'],
            ['psr4-controllers-redirection.xml'],
        ];
    }

    public function testImportAttributesFromClass()
    {
        new LoaderResolver([
            $loader = new XmlFileLoader(new FileLocator(\dirname(__DIR__).'/Fixtures')),
            new class extends AttributeClassLoader {
                protected function configureRoute(Route $route, \ReflectionClass $class, \ReflectionMethod $method, object $annot): void
                {
                    $route->setDefault('_controller', $class->getName().'::'.$method->getName());
                }
            },
        ]);

        $route = $loader->load('class-attributes.xml')->get('my_route');
        $this->assertSame('/my-prefix/my/route', $route->getPath());
        $this->assertSame(MyController::class.'::__invoke', $route->getDefault('_controller'));
    }
}
