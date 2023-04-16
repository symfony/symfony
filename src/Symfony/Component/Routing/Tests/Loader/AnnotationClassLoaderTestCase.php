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
use Symfony\Component\Routing\Loader\AnnotationClassLoader;
use Symfony\Component\Routing\Tests\Fixtures\AnnotationFixtures\AbstractClassController;

abstract class AnnotationClassLoaderTestCase extends TestCase
{
    /**
     * @var AnnotationClassLoader
     */
    protected $loader;

    /**
     * @dataProvider provideTestSupportsChecksResource
     */
    public function testSupportsChecksResource($resource, $expectedSupports)
    {
        $this->assertSame($expectedSupports, $this->loader->supports($resource), '->supports() returns true if the resource is loadable');
    }

    public static function provideTestSupportsChecksResource()
    {
        return [
            ['class', true],
            ['\fully\qualified\class\name', true],
            ['namespaced\class\without\leading\slash', true],
            ['ÿClassWithLegalSpecialCharacters', true],
            ['5', false],
            ['foo.foo', false],
            [null, false],
        ];
    }

    public function testSupportsChecksTypeIfSpecified()
    {
        $this->assertTrue($this->loader->supports('class', 'annotation'), '->supports() checks the resource type if specified');
        $this->assertTrue($this->loader->supports('class', 'attribute'), '->supports() checks the resource type if specified');
        $this->assertFalse($this->loader->supports('class', 'foo'), '->supports() checks the resource type if specified');
    }

    public function testSimplePathRoute()
    {
        $routes = $this->loader->load($this->getNamespace().'\ActionPathController');
        $this->assertCount(1, $routes);
        $this->assertEquals('/path', $routes->get('action')->getPath());
    }

    public function testRequirementsWithoutPlaceholderName()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('A placeholder name must be a string (0 given). Did you forget to specify the placeholder key for the requirement "foo"');

        $this->loader->load($this->getNamespace().'\RequirementsWithoutPlaceholderNameController');
    }

    public function testInvokableControllerLoader()
    {
        $routes = $this->loader->load($this->getNamespace().'\InvokableController');
        $this->assertCount(1, $routes);
        $this->assertEquals('/here', $routes->get('lol')->getPath());
        $this->assertEquals(['GET', 'POST'], $routes->get('lol')->getMethods());
        $this->assertEquals(['https'], $routes->get('lol')->getSchemes());
    }

    public function testInvokableLocalizedControllerLoading()
    {
        $routes = $this->loader->load($this->getNamespace().'\InvokableLocalizedController');
        $this->assertCount(2, $routes);
        $this->assertEquals('/here', $routes->get('action.en')->getPath());
        $this->assertEquals('/hier', $routes->get('action.nl')->getPath());
    }

    public function testLocalizedPathRoutes()
    {
        $routes = $this->loader->load($this->getNamespace().'\LocalizedActionPathController');
        $this->assertCount(2, $routes);
        $this->assertEquals('/path', $routes->get('action.en')->getPath());
        $this->assertEquals('/pad', $routes->get('action.nl')->getPath());

        $this->assertEquals('nl', $routes->get('action.nl')->getRequirement('_locale'));
        $this->assertEquals('en', $routes->get('action.en')->getRequirement('_locale'));
    }

    public function testLocalizedPathRoutesWithExplicitPathPropety()
    {
        $routes = $this->loader->load($this->getNamespace().'\ExplicitLocalizedActionPathController');
        $this->assertCount(2, $routes);
        $this->assertEquals('/path', $routes->get('action.en')->getPath());
        $this->assertEquals('/pad', $routes->get('action.nl')->getPath());
    }

    public function testDefaultValuesForMethods()
    {
        $routes = $this->loader->load($this->getNamespace().'\DefaultValueController');
        $this->assertCount(5, $routes);
        $this->assertEquals('/{default}/path', $routes->get('action')->getPath());
        $this->assertEquals('value', $routes->get('action')->getDefault('default'));
        $this->assertEquals('Symfony', $routes->get('hello_with_default')->getDefault('name'));
        $this->assertEquals('World', $routes->get('hello_without_default')->getDefault('name'));
        $this->assertEquals('diamonds', $routes->get('string_enum_action')->getDefault('default'));
        $this->assertEquals(20, $routes->get('int_enum_action')->getDefault('default'));
    }

    public function testMethodActionControllers()
    {
        $routes = $this->loader->load($this->getNamespace().'\MethodActionControllers');
        $this->assertSame(['put', 'post'], array_keys($routes->all()));
        $this->assertEquals('/the/path', $routes->get('put')->getPath());
        $this->assertEquals('/the/path', $routes->get('post')->getPath());
    }

    public function testInvokableClassRouteLoadWithMethodAnnotation()
    {
        $routes = $this->loader->load($this->getNamespace().'\LocalizedMethodActionControllers');
        $this->assertCount(4, $routes);
        $this->assertEquals('/the/path', $routes->get('put.en')->getPath());
        $this->assertEquals('/the/path', $routes->get('post.en')->getPath());
    }

    public function testGlobalDefaultsRoutesLoadWithAnnotation()
    {
        $routes = $this->loader->load($this->getNamespace().'\GlobalDefaultsClass');
        $this->assertCount(2, $routes);

        $specificLocaleRoute = $routes->get('specific_locale');

        $this->assertSame('/defaults/specific-locale', $specificLocaleRoute->getPath());
        $this->assertSame('s_locale', $specificLocaleRoute->getDefault('_locale'));
        $this->assertSame('g_format', $specificLocaleRoute->getDefault('_format'));

        $specificFormatRoute = $routes->get('specific_format');

        $this->assertSame('/defaults/specific-format', $specificFormatRoute->getPath());
        $this->assertSame('g_locale', $specificFormatRoute->getDefault('_locale'));
        $this->assertSame('s_format', $specificFormatRoute->getDefault('_format'));
    }

    public function testUtf8RoutesLoadWithAnnotation()
    {
        $routes = $this->loader->load($this->getNamespace().'\Utf8ActionControllers');
        $this->assertSame(['one', 'two'], array_keys($routes->all()));
        $this->assertTrue($routes->get('one')->getOption('utf8'), 'The route must accept utf8');
        $this->assertFalse($routes->get('two')->getOption('utf8'), 'The route must not accept utf8');
    }

    public function testRouteWithPathWithPrefix()
    {
        $routes = $this->loader->load($this->getNamespace().'\PrefixedActionPathController');
        $this->assertCount(1, $routes);
        $route = $routes->get('action');
        $this->assertEquals('/prefix/path', $route->getPath());
        $this->assertEquals('lol=fun', $route->getCondition());
        $this->assertEquals('frankdejonge.nl', $route->getHost());
    }

    public function testLocalizedRouteWithPathWithPrefix()
    {
        $routes = $this->loader->load($this->getNamespace().'\PrefixedActionLocalizedRouteController');
        $this->assertCount(2, $routes);
        $this->assertEquals('/prefix/path', $routes->get('action.en')->getPath());
        $this->assertEquals('/prefix/pad', $routes->get('action.nl')->getPath());
    }

    public function testLocalizedPrefixLocalizedRoute()
    {
        $routes = $this->loader->load($this->getNamespace().'\LocalizedPrefixLocalizedActionController');
        $this->assertCount(2, $routes);
        $this->assertEquals('/nl/actie', $routes->get('action.nl')->getPath());
        $this->assertEquals('/en/action', $routes->get('action.en')->getPath());
    }

    public function testInvokableClassMultipleRouteLoad()
    {
        $routeCollection = $this->loader->load($this->getNamespace().'\BazClass');
        $route = $routeCollection->get('route1');

        $this->assertSame('/1', $route->getPath(), '->load preserves class route path');
        $this->assertSame(['https'], $route->getSchemes(), '->load preserves class route schemes');
        $this->assertSame(['GET'], $route->getMethods(), '->load preserves class route methods');

        $route = $routeCollection->get('route2');

        $this->assertSame('/2', $route->getPath(), '->load preserves class route path');
        $this->assertEquals(['https'], $route->getSchemes(), '->load preserves class route schemes');
        $this->assertEquals(['GET'], $route->getMethods(), '->load preserves class route methods');
    }

    public function testMissingPrefixLocale()
    {
        $this->expectException(\LogicException::class);
        $this->loader->load($this->getNamespace().'\LocalizedPrefixMissingLocaleActionController');
    }

    public function testMissingRouteLocale()
    {
        $this->expectException(\LogicException::class);
        $this->loader->load($this->getNamespace().'\LocalizedPrefixMissingRouteLocaleActionController');
    }

    public function testRouteWithoutName()
    {
        $routes = $this->loader->load($this->getNamespace().'\MissingRouteNameController')->all();
        $this->assertCount(1, $routes);
        $this->assertEquals('/path', reset($routes)->getPath());
    }

    public function testNothingButName()
    {
        $routes = $this->loader->load($this->getNamespace().'\NothingButNameController')->all();
        $this->assertCount(1, $routes);
        $this->assertEquals('/', reset($routes)->getPath());
    }

    public function testNonExistingClass()
    {
        $this->expectException(\LogicException::class);
        $this->loader->load('ClassThatDoesNotExist');
    }

    public function testLoadingAbstractClass()
    {
        $this->expectException(\LogicException::class);
        $this->loader->load(AbstractClassController::class);
    }

    public function testLocalizedPrefixWithoutRouteLocale()
    {
        $routes = $this->loader->load($this->getNamespace().'\LocalizedPrefixWithRouteWithoutLocale');
        $this->assertCount(2, $routes);
        $this->assertEquals('/en/suffix', $routes->get('action.en')->getPath());
        $this->assertEquals('/nl/suffix', $routes->get('action.nl')->getPath());
    }

    public function testLoadingRouteWithPrefix()
    {
        $routes = $this->loader->load($this->getNamespace().'\RouteWithPrefixController');
        $this->assertCount(1, $routes);
        $this->assertEquals('/prefix/path', $routes->get('action')->getPath());
    }

    public function testWhenEnv()
    {
        $routes = $this->loader->load($this->getNamespace().'\RouteWithEnv');
        $this->assertCount(0, $routes);

        $this->setUp('some-env');
        $routes = $this->loader->load($this->getNamespace().'\RouteWithEnv');
        $this->assertCount(1, $routes);
        $this->assertSame('/path', $routes->get('action')->getPath());
    }

    public function testMethodsAndSchemes()
    {
        $routes = $this->loader->load($this->getNamespace().'\MethodsAndSchemes');

        $this->assertSame(['GET', 'POST'], $routes->get('array_many')->getMethods());
        $this->assertSame(['http', 'https'], $routes->get('array_many')->getSchemes());
        $this->assertSame(['GET'], $routes->get('array_one')->getMethods());
        $this->assertSame(['http'], $routes->get('array_one')->getSchemes());
        $this->assertSame(['POST'], $routes->get('string')->getMethods());
        $this->assertSame(['https'], $routes->get('string')->getSchemes());
    }

    abstract protected function getNamespace(): string;
}
