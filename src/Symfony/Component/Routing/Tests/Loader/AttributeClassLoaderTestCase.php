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
use Symfony\Bridge\PhpUnit\ExpectDeprecationTrait;
use Symfony\Component\Routing\Alias;
use Symfony\Component\Routing\Loader\AttributeClassLoader;
use Symfony\Component\Routing\Tests\Fixtures\AnnotationFixtures\AbstractClassController;
use Symfony\Component\Routing\Tests\Fixtures\AttributeFixtures\ExtendedRouteOnClassController;
use Symfony\Component\Routing\Tests\Fixtures\AttributeFixtures\ExtendedRouteOnMethodController;

abstract class AttributeClassLoaderTestCase extends TestCase
{
    use ExpectDeprecationTrait;

    protected AttributeClassLoader $loader;

    /**
     * @dataProvider provideTestSupportsChecksResource
     */
    public function testSupportsChecksResource($resource, $expectedSupports)
    {
        $this->assertSame($expectedSupports, $this->loader->supports($resource), '->supports() returns true if the resource is loadable');
    }

    public static function provideTestSupportsChecksResource(): array
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
        $this->assertTrue($this->loader->supports('class', 'attribute'), '->supports() checks the resource type if specified');
        $this->assertFalse($this->loader->supports('class', 'foo'), '->supports() checks the resource type if specified');
    }

    /**
     * @group legacy
     */
    public function testSupportsAnnotations()
    {
        $this->expectDeprecation('Since symfony/routing 6.4: The "annotation" route type is deprecated, use the "attribute" route type instead.');
        $this->assertTrue($this->loader->supports('class', 'annotation'), '->supports() checks the resource type if specified');
    }

    public function testSimplePathRoute()
    {
        $routes = $this->loader->load($this->getNamespace().'\ActionPathController');
        $this->assertCount(1, $routes);
        $this->assertEquals('/path', $routes->get('action')->getPath());
        $this->assertEquals(new Alias('action'), $routes->getAlias($this->getNamespace().'\ActionPathController::action'));
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
        $this->assertEquals(new Alias('lol'), $routes->getAlias($this->getNamespace().'\InvokableController'));
        $this->assertEquals(new Alias('lol'), $routes->getAlias($this->getNamespace().'\InvokableController::__invoke'));
    }

    public function testInvokableFQCNAliasConflictController()
    {
        $routes = $this->loader->load($this->getNamespace().'\InvokableFQCNAliasConflictController');
        $this->assertCount(1, $routes);
        $this->assertEquals('/foobarccc', $routes->get($this->getNamespace().'\InvokableFQCNAliasConflictController')->getPath());
        $this->assertNull($routes->getAlias($this->getNamespace().'\InvokableFQCNAliasConflictController'));
        $this->assertEquals(new Alias($this->getNamespace().'\InvokableFQCNAliasConflictController'), $routes->getAlias($this->getNamespace().'\InvokableFQCNAliasConflictController::__invoke'));
    }

    public function testInvokableMethodControllerLoader()
    {
        $routes = $this->loader->load($this->getNamespace().'\InvokableMethodController');
        $this->assertCount(1, $routes);
        $this->assertEquals('/here', $routes->get('lol')->getPath());
        $this->assertEquals(['GET', 'POST'], $routes->get('lol')->getMethods());
        $this->assertEquals(['https'], $routes->get('lol')->getSchemes());
        $this->assertEquals(new Alias('lol'), $routes->getAlias($this->getNamespace().'\InvokableMethodController'));
        $this->assertEquals(new Alias('lol'), $routes->getAlias($this->getNamespace().'\InvokableMethodController::__invoke'));
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
        $this->assertEquals(new Alias('post'), $routes->getAlias($this->getNamespace().'\MethodActionControllers::post'));
        $this->assertEquals(new Alias('put'), $routes->getAlias($this->getNamespace().'\MethodActionControllers::put'));
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
        $this->assertCount(4, $routes);

        $specificLocaleRoute = $routes->get('specific_locale');

        $this->assertSame('/defaults/specific-locale', $specificLocaleRoute->getPath());
        $this->assertSame('s_locale', $specificLocaleRoute->getDefault('_locale'));
        $this->assertSame('g_format', $specificLocaleRoute->getDefault('_format'));

        $specificFormatRoute = $routes->get('specific_format');

        $this->assertSame('/defaults/specific-format', $specificFormatRoute->getPath());
        $this->assertSame('g_locale', $specificFormatRoute->getDefault('_locale'));
        $this->assertSame('s_format', $specificFormatRoute->getDefault('_format'));

        $this->assertSame(['GET'], $routes->get('redundant_method')->getMethods());
        $this->assertSame(['https'], $routes->get('redundant_scheme')->getSchemes());
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
        $this->expectExceptionMessage(sprintf('Route to "action" with locale "en" is missing a corresponding prefix in class "%s\LocalizedPrefixMissingLocaleActionController".', $this->getNamespace()));
        $this->loader->load($this->getNamespace().'\LocalizedPrefixMissingLocaleActionController');
    }

    public function testMissingRouteLocale()
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage(sprintf('Route to "%s\LocalizedPrefixMissingRouteLocaleActionController::action" is missing paths for locale(s) "en".', $this->getNamespace()));
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

    public function testLoadingExtendedRouteOnClass()
    {
        $routes = $this->loader->load(ExtendedRouteOnClassController::class);
        $this->assertCount(1, $routes);
        $this->assertSame('/{section}/class-level/method-level', $routes->get('action')->getPath());
        $this->assertSame(['section' => 'foo'], $routes->get('action')->getDefaults());
    }

    public function testLoadingExtendedRouteOnMethod()
    {
        $routes = $this->loader->load(ExtendedRouteOnMethodController::class);
        $this->assertCount(1, $routes);
        $this->assertSame('/{section}/method-level', $routes->get('action')->getPath());
        $this->assertSame(['section' => 'foo'], $routes->get('action')->getDefaults());
    }

    abstract protected function getNamespace(): string;
}
