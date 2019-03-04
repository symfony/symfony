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

use Doctrine\Common\Annotations\AnnotationReader;
use Doctrine\Common\Annotations\AnnotationRegistry;
use Symfony\Component\Routing\Annotation\Route as RouteAnnotation;
use Symfony\Component\Routing\Loader\AnnotationClassLoader;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\Tests\Fixtures\AnnotationFixtures\AbstractClassController;
use Symfony\Component\Routing\Tests\Fixtures\AnnotationFixtures\ActionPathController;
use Symfony\Component\Routing\Tests\Fixtures\AnnotationFixtures\DefaultValueController;
use Symfony\Component\Routing\Tests\Fixtures\AnnotationFixtures\ExplicitLocalizedActionPathController;
use Symfony\Component\Routing\Tests\Fixtures\AnnotationFixtures\InvokableController;
use Symfony\Component\Routing\Tests\Fixtures\AnnotationFixtures\InvokableLocalizedController;
use Symfony\Component\Routing\Tests\Fixtures\AnnotationFixtures\LocalizedActionPathController;
use Symfony\Component\Routing\Tests\Fixtures\AnnotationFixtures\LocalizedMethodActionControllers;
use Symfony\Component\Routing\Tests\Fixtures\AnnotationFixtures\LocalizedPrefixLocalizedActionController;
use Symfony\Component\Routing\Tests\Fixtures\AnnotationFixtures\LocalizedPrefixMissingLocaleActionController;
use Symfony\Component\Routing\Tests\Fixtures\AnnotationFixtures\LocalizedPrefixMissingRouteLocaleActionController;
use Symfony\Component\Routing\Tests\Fixtures\AnnotationFixtures\LocalizedPrefixWithRouteWithoutLocale;
use Symfony\Component\Routing\Tests\Fixtures\AnnotationFixtures\MethodActionControllers;
use Symfony\Component\Routing\Tests\Fixtures\AnnotationFixtures\MissingRouteNameController;
use Symfony\Component\Routing\Tests\Fixtures\AnnotationFixtures\NothingButNameController;
use Symfony\Component\Routing\Tests\Fixtures\AnnotationFixtures\PrefixedActionLocalizedRouteController;
use Symfony\Component\Routing\Tests\Fixtures\AnnotationFixtures\PrefixedActionPathController;
use Symfony\Component\Routing\Tests\Fixtures\AnnotationFixtures\RequirementsWithoutPlaceholderNameController;
use Symfony\Component\Routing\Tests\Fixtures\AnnotationFixtures\RouteWithPrefixController;

class AnnotationClassLoaderTest extends AbstractAnnotationLoaderTest
{
    /**
     * @var AnnotationClassLoader
     */
    private $loader;

    protected function setUp()
    {
        $reader = new AnnotationReader();
        $this->loader = new class($reader) extends AnnotationClassLoader {
            protected function configureRoute(Route $route, \ReflectionClass $class, \ReflectionMethod $method, $annot)
            {
            }
        };
        AnnotationRegistry::registerLoader('class_exists');
    }

    /**
     * @dataProvider provideTestSupportsChecksResource
     */
    public function testSupportsChecksResource($resource, $expectedSupports)
    {
        $this->assertSame($expectedSupports, $this->loader->supports($resource), '->supports() returns true if the resource is loadable');
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
        $this->assertTrue($this->loader->supports('class', 'annotation'), '->supports() checks the resource type if specified');
        $this->assertFalse($this->loader->supports('class', 'foo'), '->supports() checks the resource type if specified');
    }

    public function testSimplePathRoute()
    {
        $routes = $this->loader->load(ActionPathController::class);
        $this->assertCount(1, $routes);
        $this->assertEquals('/path', $routes->get('action')->getPath());
    }

    /**
     * @group legacy
     * @expectedDeprecation A placeholder name must be a string (0 given). Did you forget to specify the placeholder key for the requirement "foo" in "Symfony\Component\Routing\Tests\Fixtures\AnnotationFixtures\RequirementsWithoutPlaceholderNameController"?
     * @expectedDeprecation A placeholder name must be a string (1 given). Did you forget to specify the placeholder key for the requirement "\d+" in "Symfony\Component\Routing\Tests\Fixtures\AnnotationFixtures\RequirementsWithoutPlaceholderNameController"?
     * @expectedDeprecation A placeholder name must be a string (0 given). Did you forget to specify the placeholder key for the requirement "foo" of route "foo" in "Symfony\Component\Routing\Tests\Fixtures\AnnotationFixtures\RequirementsWithoutPlaceholderNameController::foo()"?
     * @expectedDeprecation A placeholder name must be a string (1 given). Did you forget to specify the placeholder key for the requirement "\d+" of route "foo" in "Symfony\Component\Routing\Tests\Fixtures\AnnotationFixtures\RequirementsWithoutPlaceholderNameController::foo()"?
     */
    public function testRequirementsWithoutPlaceholderName()
    {
        $this->loader->load(RequirementsWithoutPlaceholderNameController::class);
    }

    public function testInvokableControllerLoader()
    {
        $routes = $this->loader->load(InvokableController::class);
        $this->assertCount(1, $routes);
        $this->assertEquals('/here', $routes->get('lol')->getPath());
        $this->assertEquals(['GET', 'POST'], $routes->get('lol')->getMethods());
        $this->assertEquals(['https'], $routes->get('lol')->getSchemes());
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
        $this->assertCount(1, $routes);
        $this->assertEquals('/{default}/path', $routes->get('action')->getPath());
        $this->assertEquals('value', $routes->get('action')->getDefault('default'));
    }

    public function testMethodActionControllers()
    {
        $routes = $this->loader->load(MethodActionControllers::class);
        $this->assertCount(2, $routes);
        $this->assertEquals('/the/path', $routes->get('put')->getPath());
        $this->assertEquals('/the/path', $routes->get('post')->getPath());
    }

    public function testInvokableClassRouteLoadWithMethodAnnotation()
    {
        $routes = $this->loader->load(LocalizedMethodActionControllers::class);
        $this->assertCount(4, $routes);
        $this->assertEquals('/the/path', $routes->get('put.en')->getPath());
        $this->assertEquals('/the/path', $routes->get('post.en')->getPath());
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
        $classRouteData1 = [
            'name' => 'route1',
            'path' => '/1',
            'schemes' => ['https'],
            'methods' => ['GET'],
        ];

        $classRouteData2 = [
            'name' => 'route2',
            'path' => '/2',
            'schemes' => ['https'],
            'methods' => ['GET'],
        ];

        $reader = $this->getReader();
        $reader
            ->expects($this->exactly(1))
            ->method('getClassAnnotations')
            ->will($this->returnValue([new RouteAnnotation($classRouteData1), new RouteAnnotation($classRouteData2)]))
        ;
        $reader
            ->expects($this->once())
            ->method('getMethodAnnotations')
            ->will($this->returnValue([]))
        ;
        $loader = new class($reader) extends AnnotationClassLoader {
            protected function configureRoute(Route $route, \ReflectionClass $class, \ReflectionMethod $method, $annot)
            {
            }
        };

        $routeCollection = $loader->load('Symfony\Component\Routing\Tests\Fixtures\AnnotatedClasses\BazClass');
        $route = $routeCollection->get($classRouteData1['name']);

        $this->assertSame($classRouteData1['path'], $route->getPath(), '->load preserves class route path');
        $this->assertEquals($classRouteData1['schemes'], $route->getSchemes(), '->load preserves class route schemes');
        $this->assertEquals($classRouteData1['methods'], $route->getMethods(), '->load preserves class route methods');

        $route = $routeCollection->get($classRouteData2['name']);

        $this->assertSame($classRouteData2['path'], $route->getPath(), '->load preserves class route path');
        $this->assertEquals($classRouteData2['schemes'], $route->getSchemes(), '->load preserves class route schemes');
        $this->assertEquals($classRouteData2['methods'], $route->getMethods(), '->load preserves class route methods');
    }

    public function testMissingPrefixLocale()
    {
        $this->expectException(\LogicException::class);
        $this->loader->load(LocalizedPrefixMissingLocaleActionController::class);
    }

    public function testMissingRouteLocale()
    {
        $this->expectException(\LogicException::class);
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
}
