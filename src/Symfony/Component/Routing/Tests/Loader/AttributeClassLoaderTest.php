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
use Symfony\Component\Routing\Alias;
use Symfony\Component\Routing\Exception\LogicException;
use Symfony\Component\Routing\Tests\Fixtures\AttributeFixtures\AbstractClassController;
use Symfony\Component\Routing\Tests\Fixtures\AttributeFixtures\ActionPathController;
use Symfony\Component\Routing\Tests\Fixtures\AttributeFixtures\BazClass;
use Symfony\Component\Routing\Tests\Fixtures\AttributeFixtures\DefaultValueController;
use Symfony\Component\Routing\Tests\Fixtures\AttributeFixtures\EncodingClass;
use Symfony\Component\Routing\Tests\Fixtures\AttributeFixtures\ExplicitLocalizedActionPathController;
use Symfony\Component\Routing\Tests\Fixtures\AttributeFixtures\ExtendedRouteOnClassController;
use Symfony\Component\Routing\Tests\Fixtures\AttributeFixtures\ExtendedRouteOnMethodController;
use Symfony\Component\Routing\Tests\Fixtures\AttributeFixtures\GlobalDefaultsClass;
use Symfony\Component\Routing\Tests\Fixtures\AttributeFixtures\InvokableController;
use Symfony\Component\Routing\Tests\Fixtures\AttributeFixtures\InvokableLocalizedController;
use Symfony\Component\Routing\Tests\Fixtures\AttributeFixtures\InvokableMethodController;
use Symfony\Component\Routing\Tests\Fixtures\AttributeFixtures\LocalizedActionPathController;
use Symfony\Component\Routing\Tests\Fixtures\AttributeFixtures\LocalizedMethodActionControllers;
use Symfony\Component\Routing\Tests\Fixtures\AttributeFixtures\LocalizedPrefixLocalizedActionController;
use Symfony\Component\Routing\Tests\Fixtures\AttributeFixtures\LocalizedPrefixMissingLocaleActionController;
use Symfony\Component\Routing\Tests\Fixtures\AttributeFixtures\LocalizedPrefixMissingRouteLocaleActionController;
use Symfony\Component\Routing\Tests\Fixtures\AttributeFixtures\LocalizedPrefixWithRouteWithoutLocale;
use Symfony\Component\Routing\Tests\Fixtures\AttributeFixtures\MethodActionControllers;
use Symfony\Component\Routing\Tests\Fixtures\AttributeFixtures\MethodsAndSchemes;
use Symfony\Component\Routing\Tests\Fixtures\AttributeFixtures\MissingRouteNameController;
use Symfony\Component\Routing\Tests\Fixtures\AttributeFixtures\NothingButNameController;
use Symfony\Component\Routing\Tests\Fixtures\AttributeFixtures\PrefixedActionLocalizedRouteController;
use Symfony\Component\Routing\Tests\Fixtures\AttributeFixtures\PrefixedActionPathController;
use Symfony\Component\Routing\Tests\Fixtures\AttributeFixtures\RequirementsWithoutPlaceholderNameController;
use Symfony\Component\Routing\Tests\Fixtures\AttributeFixtures\RouteWithEnv;
use Symfony\Component\Routing\Tests\Fixtures\AttributeFixtures\RouteWithPrefixController;
use Symfony\Component\Routing\Tests\Fixtures\AttributeFixtures\Utf8ActionControllers;
use Symfony\Component\Routing\Tests\Fixtures\TraceableAttributeClassLoader;

class AttributeClassLoaderTest extends TestCase
{
    protected TraceableAttributeClassLoader $loader;

    protected function setUp(?string $env = null): void
    {
        $this->loader = new TraceableAttributeClassLoader($env);
    }

    public function testGetResolver()
    {
        $this->expectException(LogicException::class);

        $loader = new TraceableAttributeClassLoader();
        $loader->getResolver();
    }

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

    public function testSimplePathRoute()
    {
        $routes = $this->loader->load(ActionPathController::class);
        $this->assertCount(1, $routes);
        $this->assertEquals('/path', $routes->get('action')->getPath());
        $this->assertEquals(new Alias('action'), $routes->getAlias('Symfony\Component\Routing\Tests\Fixtures\AttributeFixtures\ActionPathController::action'));
    }

    public function testRequirementsWithoutPlaceholderName()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('A placeholder name must be a string (0 given). Did you forget to specify the placeholder key for the requirement "foo"');

        $this->loader->load(RequirementsWithoutPlaceholderNameController::class);
    }

    public function testInvokableControllerLoader()
    {
        $routes = $this->loader->load(InvokableController::class);
        $this->assertCount(1, $routes);
        $this->assertEquals('/here', $routes->get('lol')->getPath());
        $this->assertEquals(['GET', 'POST'], $routes->get('lol')->getMethods());
        $this->assertEquals(['https'], $routes->get('lol')->getSchemes());
        $this->assertEquals(new Alias('lol'), $routes->getAlias(InvokableController::class));
        $this->assertEquals(new Alias('lol'), $routes->getAlias('Symfony\Component\Routing\Tests\Fixtures\AttributeFixtures\InvokableController::__invoke'));
    }

    public function testInvokableFQCNAliasConflictController()
    {
        $routes = $this->loader->load('Symfony\Component\Routing\Tests\Fixtures\AttributeFixtures\InvokableFQCNAliasConflictController');
        $this->assertCount(1, $routes);
        $this->assertEquals('/foobarccc', $routes->get('Symfony\Component\Routing\Tests\Fixtures\AttributeFixtures\InvokableFQCNAliasConflictController')->getPath());
        $this->assertNull($routes->getAlias('Symfony\Component\Routing\Tests\Fixtures\AttributeFixtures\InvokableFQCNAliasConflictController'));
        $this->assertEquals(new Alias('Symfony\Component\Routing\Tests\Fixtures\AttributeFixtures\InvokableFQCNAliasConflictController'), $routes->getAlias('Symfony\Component\Routing\Tests\Fixtures\AttributeFixtures\InvokableFQCNAliasConflictController::__invoke'));
    }

    public function testInvokableMethodControllerLoader()
    {
        $routes = $this->loader->load(InvokableMethodController::class);
        $this->assertCount(1, $routes);
        $this->assertEquals('/here', $routes->get('lol')->getPath());
        $this->assertEquals(['GET', 'POST'], $routes->get('lol')->getMethods());
        $this->assertEquals(['https'], $routes->get('lol')->getSchemes());
        $this->assertEquals(new Alias('lol'), $routes->getAlias(InvokableMethodController::class));
        $this->assertEquals(new Alias('lol'), $routes->getAlias('Symfony\Component\Routing\Tests\Fixtures\AttributeFixtures\InvokableMethodController::__invoke'));
    }

    public function testInvokableLocalizedControllerLoading()
    {
        $routes = $this->loader->load(InvokableLocalizedController::class);
        $this->assertCount(2, $routes);
        $this->assertEquals('/here', $routes->get('action.en')->getPath());
        $this->assertEquals('/hier', $routes->get('action.nl')->getPath());
    }

    public function testLocalizedPathRoutes()
    {
        $routes = $this->loader->load(LocalizedActionPathController::class);
        $this->assertCount(2, $routes);
        $this->assertEquals('/path', $routes->get('action.en')->getPath());
        $this->assertEquals('/pad', $routes->get('action.nl')->getPath());

        $this->assertEquals('nl', $routes->get('action.nl')->getRequirement('_locale'));
        $this->assertEquals('en', $routes->get('action.en')->getRequirement('_locale'));
    }

    public function testLocalizedPathRoutesWithExplicitPathPropety()
    {
        $routes = $this->loader->load(ExplicitLocalizedActionPathController::class);
        $this->assertCount(2, $routes);
        $this->assertEquals('/path', $routes->get('action.en')->getPath());
        $this->assertEquals('/pad', $routes->get('action.nl')->getPath());
    }

    public function testDefaultValuesForMethods()
    {
        $routes = $this->loader->load(DefaultValueController::class);
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
        $routes = $this->loader->load(MethodActionControllers::class);
        $this->assertSame(['put', 'post'], array_keys($routes->all()));
        $this->assertEquals('/the/path', $routes->get('put')->getPath());
        $this->assertEquals('/the/path', $routes->get('post')->getPath());
        $this->assertEquals(new Alias('post'), $routes->getAlias('Symfony\Component\Routing\Tests\Fixtures\AttributeFixtures\MethodActionControllers::post'));
        $this->assertEquals(new Alias('put'), $routes->getAlias('Symfony\Component\Routing\Tests\Fixtures\AttributeFixtures\MethodActionControllers::put'));
    }

    public function testInvokableClassRouteLoadWithMethodAttribute()
    {
        $routes = $this->loader->load(LocalizedMethodActionControllers::class);
        $this->assertCount(4, $routes);
        $this->assertEquals('/the/path', $routes->get('put.en')->getPath());
        $this->assertEquals('/the/path', $routes->get('post.en')->getPath());
    }

    public function testGlobalDefaultsRoutesLoadWithAttribute()
    {
        $routes = $this->loader->load(GlobalDefaultsClass::class);
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

    public function testUtf8RoutesLoadWithAttribute()
    {
        $routes = $this->loader->load(Utf8ActionControllers::class);
        $this->assertSame(['one', 'two'], array_keys($routes->all()));
        $this->assertTrue($routes->get('one')->getOption('utf8'), 'The route must accept utf8');
        $this->assertFalse($routes->get('two')->getOption('utf8'), 'The route must not accept utf8');
    }

    public function testRouteWithPathWithPrefix()
    {
        $routes = $this->loader->load(PrefixedActionPathController::class);
        $this->assertCount(1, $routes);
        $route = $routes->get('action');
        $this->assertEquals('/prefix/path', $route->getPath());
        $this->assertEquals('lol=fun', $route->getCondition());
        $this->assertEquals('frankdejonge.nl', $route->getHost());
    }

    public function testLocalizedRouteWithPathWithPrefix()
    {
        $routes = $this->loader->load(PrefixedActionLocalizedRouteController::class);
        $this->assertCount(2, $routes);
        $this->assertEquals('/prefix/path', $routes->get('action.en')->getPath());
        $this->assertEquals('/prefix/pad', $routes->get('action.nl')->getPath());
    }

    public function testLocalizedPrefixLocalizedRoute()
    {
        $routes = $this->loader->load(LocalizedPrefixLocalizedActionController::class);
        $this->assertCount(2, $routes);
        $this->assertEquals('/nl/actie', $routes->get('action.nl')->getPath());
        $this->assertEquals('/en/action', $routes->get('action.en')->getPath());
    }

    public function testInvokableClassMultipleRouteLoad()
    {
        $routeCollection = $this->loader->load(BazClass::class);
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
        $this->expectExceptionMessage('Route to "action" with locale "en" is missing a corresponding prefix in class "Symfony\Component\Routing\Tests\Fixtures\AttributeFixtures\LocalizedPrefixMissingLocaleActionController".');
        $this->loader->load(LocalizedPrefixMissingLocaleActionController::class);
    }

    public function testMissingRouteLocale()
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Route to "Symfony\Component\Routing\Tests\Fixtures\AttributeFixtures\LocalizedPrefixMissingRouteLocaleActionController::action" is missing paths for locale(s) "en".');
        $this->loader->load(LocalizedPrefixMissingRouteLocaleActionController::class);
    }

    public function testRouteWithoutName()
    {
        $routes = $this->loader->load(MissingRouteNameController::class)->all();
        $this->assertCount(1, $routes);
        $this->assertEquals('/path', reset($routes)->getPath());
    }

    public function testNothingButName()
    {
        $routes = $this->loader->load(NothingButNameController::class)->all();
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
        $routes = $this->loader->load(LocalizedPrefixWithRouteWithoutLocale::class);
        $this->assertCount(2, $routes);
        $this->assertEquals('/en/suffix', $routes->get('action.en')->getPath());
        $this->assertEquals('/nl/suffix', $routes->get('action.nl')->getPath());
    }

    public function testLoadingRouteWithPrefix()
    {
        $routes = $this->loader->load(RouteWithPrefixController::class);
        $this->assertCount(1, $routes);
        $this->assertEquals('/prefix/path', $routes->get('action')->getPath());
    }

    public function testWhenEnv()
    {
        $routes = $this->loader->load(RouteWithEnv::class);
        $this->assertCount(0, $routes);

        $this->setUp('some-env');
        $routes = $this->loader->load(RouteWithEnv::class);
        $this->assertCount(1, $routes);
        $this->assertSame('/path', $routes->get('action')->getPath());
    }

    public function testMethodsAndSchemes()
    {
        $routes = $this->loader->load(MethodsAndSchemes::class);

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

    public function testDefaultRouteName()
    {
        $routeCollection = $this->loader->load(EncodingClass::class);
        $defaultName = array_keys($routeCollection->all())[0];

        $this->assertSame('symfony_component_routing_tests_fixtures_attributefixtures_encodingclass_routeàction', $defaultName);
    }
}
