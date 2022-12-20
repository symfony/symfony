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

abstract class AnnotationClassLoaderTest extends TestCase
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
        self::assertSame($expectedSupports, $this->loader->supports($resource), '->supports() returns true if the resource is loadable');
    }

    public function provideTestSupportsChecksResource()
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
        self::assertTrue($this->loader->supports('class', 'annotation'), '->supports() checks the resource type if specified');
        self::assertFalse($this->loader->supports('class', 'foo'), '->supports() checks the resource type if specified');
    }

    public function testSimplePathRoute()
    {
        $routes = $this->loader->load($this->getNamespace().'\ActionPathController');
        self::assertCount(1, $routes);
        self::assertEquals('/path', $routes->get('action')->getPath());
    }

    public function testRequirementsWithoutPlaceholderName()
    {
        self::expectException(\InvalidArgumentException::class);
        self::expectExceptionMessage('A placeholder name must be a string (0 given). Did you forget to specify the placeholder key for the requirement "foo"');

        $this->loader->load($this->getNamespace().'\RequirementsWithoutPlaceholderNameController');
    }

    public function testInvokableControllerLoader()
    {
        $routes = $this->loader->load($this->getNamespace().'\InvokableController');
        self::assertCount(1, $routes);
        self::assertEquals('/here', $routes->get('lol')->getPath());
        self::assertEquals(['GET', 'POST'], $routes->get('lol')->getMethods());
        self::assertEquals(['https'], $routes->get('lol')->getSchemes());
    }

    public function testInvokableLocalizedControllerLoading()
    {
        $routes = $this->loader->load($this->getNamespace().'\InvokableLocalizedController');
        self::assertCount(2, $routes);
        self::assertEquals('/here', $routes->get('action.en')->getPath());
        self::assertEquals('/hier', $routes->get('action.nl')->getPath());
    }

    public function testLocalizedPathRoutes()
    {
        $routes = $this->loader->load($this->getNamespace().'\LocalizedActionPathController');
        self::assertCount(2, $routes);
        self::assertEquals('/path', $routes->get('action.en')->getPath());
        self::assertEquals('/pad', $routes->get('action.nl')->getPath());

        self::assertEquals('nl', $routes->get('action.nl')->getRequirement('_locale'));
        self::assertEquals('en', $routes->get('action.en')->getRequirement('_locale'));
    }

    public function testLocalizedPathRoutesWithExplicitPathPropety()
    {
        $routes = $this->loader->load($this->getNamespace().'\ExplicitLocalizedActionPathController');
        self::assertCount(2, $routes);
        self::assertEquals('/path', $routes->get('action.en')->getPath());
        self::assertEquals('/pad', $routes->get('action.nl')->getPath());
    }

    public function testDefaultValuesForMethods()
    {
        $routes = $this->loader->load($this->getNamespace().'\DefaultValueController');
        self::assertCount(3, $routes);
        self::assertEquals('/{default}/path', $routes->get('action')->getPath());
        self::assertEquals('value', $routes->get('action')->getDefault('default'));
        self::assertEquals('Symfony', $routes->get('hello_with_default')->getDefault('name'));
        self::assertEquals('World', $routes->get('hello_without_default')->getDefault('name'));
    }

    public function testMethodActionControllers()
    {
        $routes = $this->loader->load($this->getNamespace().'\MethodActionControllers');
        self::assertSame(['put', 'post'], array_keys($routes->all()));
        self::assertEquals('/the/path', $routes->get('put')->getPath());
        self::assertEquals('/the/path', $routes->get('post')->getPath());
    }

    public function testInvokableClassRouteLoadWithMethodAnnotation()
    {
        $routes = $this->loader->load($this->getNamespace().'\LocalizedMethodActionControllers');
        self::assertCount(4, $routes);
        self::assertEquals('/the/path', $routes->get('put.en')->getPath());
        self::assertEquals('/the/path', $routes->get('post.en')->getPath());
    }

    public function testGlobalDefaultsRoutesLoadWithAnnotation()
    {
        $routes = $this->loader->load($this->getNamespace().'\GlobalDefaultsClass');
        self::assertCount(2, $routes);

        $specificLocaleRoute = $routes->get('specific_locale');

        self::assertSame('/defaults/specific-locale', $specificLocaleRoute->getPath());
        self::assertSame('s_locale', $specificLocaleRoute->getDefault('_locale'));
        self::assertSame('g_format', $specificLocaleRoute->getDefault('_format'));

        $specificFormatRoute = $routes->get('specific_format');

        self::assertSame('/defaults/specific-format', $specificFormatRoute->getPath());
        self::assertSame('g_locale', $specificFormatRoute->getDefault('_locale'));
        self::assertSame('s_format', $specificFormatRoute->getDefault('_format'));
    }

    public function testUtf8RoutesLoadWithAnnotation()
    {
        $routes = $this->loader->load($this->getNamespace().'\Utf8ActionControllers');
        self::assertSame(['one', 'two'], array_keys($routes->all()));
        self::assertTrue($routes->get('one')->getOption('utf8'), 'The route must accept utf8');
        self::assertFalse($routes->get('two')->getOption('utf8'), 'The route must not accept utf8');
    }

    public function testRouteWithPathWithPrefix()
    {
        $routes = $this->loader->load($this->getNamespace().'\PrefixedActionPathController');
        self::assertCount(1, $routes);
        $route = $routes->get('action');
        self::assertEquals('/prefix/path', $route->getPath());
        self::assertEquals('lol=fun', $route->getCondition());
        self::assertEquals('frankdejonge.nl', $route->getHost());
    }

    public function testLocalizedRouteWithPathWithPrefix()
    {
        $routes = $this->loader->load($this->getNamespace().'\PrefixedActionLocalizedRouteController');
        self::assertCount(2, $routes);
        self::assertEquals('/prefix/path', $routes->get('action.en')->getPath());
        self::assertEquals('/prefix/pad', $routes->get('action.nl')->getPath());
    }

    public function testLocalizedPrefixLocalizedRoute()
    {
        $routes = $this->loader->load($this->getNamespace().'\LocalizedPrefixLocalizedActionController');
        self::assertCount(2, $routes);
        self::assertEquals('/nl/actie', $routes->get('action.nl')->getPath());
        self::assertEquals('/en/action', $routes->get('action.en')->getPath());
    }

    public function testInvokableClassMultipleRouteLoad()
    {
        $routeCollection = $this->loader->load($this->getNamespace().'\BazClass');
        $route = $routeCollection->get('route1');

        self::assertSame('/1', $route->getPath(), '->load preserves class route path');
        self::assertSame(['https'], $route->getSchemes(), '->load preserves class route schemes');
        self::assertSame(['GET'], $route->getMethods(), '->load preserves class route methods');

        $route = $routeCollection->get('route2');

        self::assertSame('/2', $route->getPath(), '->load preserves class route path');
        self::assertEquals(['https'], $route->getSchemes(), '->load preserves class route schemes');
        self::assertEquals(['GET'], $route->getMethods(), '->load preserves class route methods');
    }

    public function testMissingPrefixLocale()
    {
        self::expectException(\LogicException::class);
        $this->loader->load($this->getNamespace().'\LocalizedPrefixMissingLocaleActionController');
    }

    public function testMissingRouteLocale()
    {
        self::expectException(\LogicException::class);
        $this->loader->load($this->getNamespace().'\LocalizedPrefixMissingRouteLocaleActionController');
    }

    public function testRouteWithoutName()
    {
        $routes = $this->loader->load($this->getNamespace().'\MissingRouteNameController')->all();
        self::assertCount(1, $routes);
        self::assertEquals('/path', reset($routes)->getPath());
    }

    public function testNothingButName()
    {
        $routes = $this->loader->load($this->getNamespace().'\NothingButNameController')->all();
        self::assertCount(1, $routes);
        self::assertEquals('/', reset($routes)->getPath());
    }

    public function testNonExistingClass()
    {
        self::expectException(\LogicException::class);
        $this->loader->load('ClassThatDoesNotExist');
    }

    public function testLoadingAbstractClass()
    {
        self::expectException(\LogicException::class);
        $this->loader->load(AbstractClassController::class);
    }

    public function testLocalizedPrefixWithoutRouteLocale()
    {
        $routes = $this->loader->load($this->getNamespace().'\LocalizedPrefixWithRouteWithoutLocale');
        self::assertCount(2, $routes);
        self::assertEquals('/en/suffix', $routes->get('action.en')->getPath());
        self::assertEquals('/nl/suffix', $routes->get('action.nl')->getPath());
    }

    public function testLoadingRouteWithPrefix()
    {
        $routes = $this->loader->load($this->getNamespace().'\RouteWithPrefixController');
        self::assertCount(1, $routes);
        self::assertEquals('/prefix/path', $routes->get('action')->getPath());
    }

    public function testWhenEnv()
    {
        $routes = $this->loader->load($this->getNamespace().'\RouteWithEnv');
        self::assertCount(0, $routes);

        self::setUp('some-env');
        $routes = $this->loader->load($this->getNamespace().'\RouteWithEnv');
        self::assertCount(1, $routes);
        self::assertSame('/path', $routes->get('action')->getPath());
    }

    public function testMethodsAndSchemes()
    {
        $routes = $this->loader->load($this->getNamespace().'\MethodsAndSchemes');

        self::assertSame(['GET', 'POST'], $routes->get('array_many')->getMethods());
        self::assertSame(['http', 'https'], $routes->get('array_many')->getSchemes());
        self::assertSame(['GET'], $routes->get('array_one')->getMethods());
        self::assertSame(['http'], $routes->get('array_one')->getSchemes());
        self::assertSame(['POST'], $routes->get('string')->getMethods());
        self::assertSame(['https'], $routes->get('string')->getSchemes());
    }

    abstract protected function getNamespace(): string;
}
