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

class YamlFileLoaderTest extends TestCase
{
    public function testSupports()
    {
        $loader = new YamlFileLoader($this->getMockBuilder('Symfony\Component\Config\FileLocator')->getMock());

        $this->assertTrue($loader->supports('foo.yml'), '->supports() returns true if the resource is loadable');
        $this->assertTrue($loader->supports('foo.yaml'), '->supports() returns true if the resource is loadable');
        $this->assertFalse($loader->supports('foo.foo'), '->supports() returns true if the resource is loadable');

        $this->assertTrue($loader->supports('foo.yml', 'yaml'), '->supports() checks the resource type if specified');
        $this->assertTrue($loader->supports('foo.yaml', 'yaml'), '->supports() checks the resource type if specified');
        $this->assertFalse($loader->supports('foo.yml', 'foo'), '->supports() checks the resource type if specified');
    }

    public function testLoadDoesNothingIfEmpty()
    {
        $loader = new YamlFileLoader(new FileLocator([__DIR__.'/../Fixtures']));
        $collection = $loader->load('empty.yml');

        $this->assertEquals([], $collection->all());
        $this->assertEquals([new FileResource(realpath(__DIR__.'/../Fixtures/empty.yml'))], $collection->getResources());
    }

    /**
     * @expectedException \InvalidArgumentException
     * @dataProvider getPathsToInvalidFiles
     */
    public function testLoadThrowsExceptionWithInvalidFile($filePath)
    {
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
        ];
    }

    public function testLoadSpecialRouteName()
    {
        $loader = new YamlFileLoader(new FileLocator([__DIR__.'/../Fixtures']));
        $routeCollection = $loader->load('special_route_name.yml');
        $route = $routeCollection->get('#$péß^a|');

        $this->assertInstanceOf('Symfony\Component\Routing\Route', $route);
        $this->assertSame('/true', $route->getPath());
    }

    public function testLoadWithRoute()
    {
        $loader = new YamlFileLoader(new FileLocator([__DIR__.'/../Fixtures']));
        $routeCollection = $loader->load('validpattern.yml');
        $route = $routeCollection->get('blog_show');

        $this->assertInstanceOf('Symfony\Component\Routing\Route', $route);
        $this->assertSame('/blog/{slug}', $route->getPath());
        $this->assertSame('{locale}.example.com', $route->getHost());
        $this->assertSame('MyBundle:Blog:show', $route->getDefault('_controller'));
        $this->assertSame('\w+', $route->getRequirement('locale'));
        $this->assertSame('RouteCompiler', $route->getOption('compiler_class'));
        $this->assertEquals(['GET', 'POST', 'PUT', 'OPTIONS'], $route->getMethods());
        $this->assertEquals(['https'], $route->getSchemes());
        $this->assertEquals('context.getMethod() == "GET"', $route->getCondition());
    }

    public function testLoadWithResource()
    {
        $loader = new YamlFileLoader(new FileLocator([__DIR__.'/../Fixtures']));
        $routeCollection = $loader->load('validresource.yml');
        $routes = $routeCollection->all();

        $this->assertCount(2, $routes, 'Two routes are loaded');
        $this->assertContainsOnly('Symfony\Component\Routing\Route', $routes);

        foreach ($routes as $route) {
            $this->assertSame('/{foo}/blog/{slug}', $route->getPath());
            $this->assertSame('123', $route->getDefault('foo'));
            $this->assertSame('\d+', $route->getRequirement('foo'));
            $this->assertSame('bar', $route->getOption('foo'));
            $this->assertSame('', $route->getHost());
            $this->assertSame('context.getMethod() == "POST"', $route->getCondition());
        }
    }

    public function testLoadRouteWithControllerAttribute()
    {
        $loader = new YamlFileLoader(new FileLocator([__DIR__.'/../Fixtures/controller']));
        $routeCollection = $loader->load('routing.yml');

        $route = $routeCollection->get('app_homepage');

        $this->assertSame('AppBundle:Homepage:show', $route->getDefault('_controller'));
    }

    public function testLoadRouteWithoutControllerAttribute()
    {
        $loader = new YamlFileLoader(new FileLocator([__DIR__.'/../Fixtures/controller']));
        $routeCollection = $loader->load('routing.yml');

        $route = $routeCollection->get('app_logout');

        $this->assertNull($route->getDefault('_controller'));
    }

    public function testLoadRouteWithControllerSetInDefaults()
    {
        $loader = new YamlFileLoader(new FileLocator([__DIR__.'/../Fixtures/controller']));
        $routeCollection = $loader->load('routing.yml');

        $route = $routeCollection->get('app_blog');

        $this->assertSame('AppBundle:Blog:list', $route->getDefault('_controller'));
    }

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessageRegExp /The routing file "[^"]*" must not specify both the "controller" key and the defaults key "_controller" for "app_blog"/
     */
    public function testOverrideControllerInDefaults()
    {
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
        $this->assertSame('FrameworkBundle:Template:template', $route->getDefault('_controller'));

        $route = $routeCollection->get('app_blog');
        $this->assertSame('FrameworkBundle:Template:template', $route->getDefault('_controller'));

        $route = $routeCollection->get('app_logout');
        $this->assertSame('FrameworkBundle:Template:template', $route->getDefault('_controller'));
    }

    public function provideFilesImportingRoutesWithControllers()
    {
        yield ['import_controller.yml'];
        yield ['import__controller.yml'];
    }

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessageRegExp /The routing file "[^"]*" must not specify both the "controller" key and the defaults key "_controller" for "_static"/
     */
    public function testImportWithOverriddenController()
    {
        $loader = new YamlFileLoader(new FileLocator([__DIR__.'/../Fixtures/controller']));
        $loader->load('import_override_defaults.yml');
    }

    public function testImportRouteWithGlobMatchingSingleFile()
    {
        $loader = new YamlFileLoader(new FileLocator([__DIR__.'/../Fixtures/glob']));
        $routeCollection = $loader->load('import_single.yml');

        $route = $routeCollection->get('bar_route');
        $this->assertSame('AppBundle:Bar:view', $route->getDefault('_controller'));
    }

    public function testImportRouteWithGlobMatchingMultipleFiles()
    {
        $loader = new YamlFileLoader(new FileLocator([__DIR__.'/../Fixtures/glob']));
        $routeCollection = $loader->load('import_multiple.yml');

        $route = $routeCollection->get('bar_route');
        $this->assertSame('AppBundle:Bar:view', $route->getDefault('_controller'));

        $route = $routeCollection->get('baz_route');
        $this->assertSame('AppBundle:Baz:view', $route->getDefault('_controller'));
    }

    public function testImportRouteWithNamePrefix()
    {
        $loader = new YamlFileLoader(new FileLocator([__DIR__.'/../Fixtures/import_with_name_prefix']));
        $routeCollection = $loader->load('routing.yml');

        $this->assertNotNull($routeCollection->get('app_blog'));
        $this->assertEquals('/blog', $routeCollection->get('app_blog')->getPath());
        $this->assertNotNull($routeCollection->get('api_app_blog'));
        $this->assertEquals('/api/blog', $routeCollection->get('api_app_blog')->getPath());
    }

    public function testRemoteSourcesAreNotAccepted()
    {
        $loader = new YamlFileLoader(new FileLocatorStub());
        $this->expectException(\InvalidArgumentException::class);
        $loader->load('http://remote.com/here.yml');
    }

    public function testLoadingLocalizedRoute()
    {
        $loader = new YamlFileLoader(new FileLocator([__DIR__.'/../Fixtures/localized']));
        $routes = $loader->load('localized-route.yml');

        $this->assertCount(3, $routes);
    }

    public function testImportingRoutesFromDefinition()
    {
        $loader = new YamlFileLoader(new FileLocator([__DIR__.'/../Fixtures/localized']));
        $routes = $loader->load('importing-localized-route.yml');

        $this->assertCount(3, $routes);
        $this->assertEquals('/nl', $routes->get('home.nl')->getPath());
        $this->assertEquals('/en', $routes->get('home.en')->getPath());
        $this->assertEquals('/here', $routes->get('not_localized')->getPath());
    }

    public function testImportingRoutesWithLocales()
    {
        $loader = new YamlFileLoader(new FileLocator([__DIR__.'/../Fixtures/localized']));
        $routes = $loader->load('importer-with-locale.yml');

        $this->assertCount(2, $routes);
        $this->assertEquals('/nl/voorbeeld', $routes->get('imported.nl')->getPath());
        $this->assertEquals('/en/example', $routes->get('imported.en')->getPath());
    }

    public function testImportingNonLocalizedRoutesWithLocales()
    {
        $loader = new YamlFileLoader(new FileLocator([__DIR__.'/../Fixtures/localized']));
        $routes = $loader->load('importer-with-locale-imports-non-localized-route.yml');

        $this->assertCount(2, $routes);
        $this->assertEquals('/nl/imported', $routes->get('imported.nl')->getPath());
        $this->assertEquals('/en/imported', $routes->get('imported.en')->getPath());
    }

    public function testImportingRoutesWithOfficialLocales()
    {
        $loader = new YamlFileLoader(new FileLocator([__DIR__.'/../Fixtures/localized']));
        $routes = $loader->load('officially_formatted_locales.yml');

        $this->assertCount(3, $routes);
        $this->assertEquals('/omelette-au-fromage', $routes->get('official.fr.UTF-8')->getPath());
        $this->assertEquals('/eu-não-sou-espanhol', $routes->get('official.pt-PT')->getPath());
        $this->assertEquals('/churrasco', $routes->get('official.pt_BR')->getPath());
    }

    public function testImportingRoutesFromDefinitionMissingLocalePrefix()
    {
        $loader = new YamlFileLoader(new FileLocator([__DIR__.'/../Fixtures/localized']));
        $this->expectException(\InvalidArgumentException::class);
        $loader->load('missing-locale-in-importer.yml');
    }

    public function testImportingRouteWithoutPathOrLocales()
    {
        $loader = new YamlFileLoader(new FileLocator([__DIR__.'/../Fixtures/localized']));
        $this->expectException(\InvalidArgumentException::class);
        $loader->load('route-without-path-or-locales.yml');
    }

    public function testImportingWithControllerDefault()
    {
        $loader = new YamlFileLoader(new FileLocator([__DIR__.'/../Fixtures/localized']));
        $routes = $loader->load('importer-with-controller-default.yml');
        $this->assertCount(3, $routes);
        $this->assertEquals('DefaultController::defaultAction', $routes->get('home.en')->getDefault('_controller'));
        $this->assertEquals('DefaultController::defaultAction', $routes->get('home.nl')->getDefault('_controller'));
        $this->assertEquals('DefaultController::defaultAction', $routes->get('not_localized')->getDefault('_controller'));
    }

    public function testImportRouteWithNoTrailingSlash()
    {
        $loader = new YamlFileLoader(new FileLocator([__DIR__.'/../Fixtures/import_with_no_trailing_slash']));
        $routeCollection = $loader->load('routing.yml');

        $this->assertEquals('/slash/', $routeCollection->get('a_app_homepage')->getPath());
        $this->assertEquals('/no-slash', $routeCollection->get('b_app_homepage')->getPath());
    }

    /**
     * @group legacy
     * @expectedDeprecation A placeholder name must be a string (0 given). Did you forget to specify the placeholder key for the requirement "\d+" of route "foo" in "%srequirements_without_placeholder_name.yml"?
     */
    public function testRequirementsWithoutPlaceholderName()
    {
        $loader = new YamlFileLoader(new FileLocator([__DIR__.'/../Fixtures']));
        $loader->load('requirements_without_placeholder_name.yml');
    }
}
