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
use Symfony\Component\Config\Loader\DelegatingLoader;
use Symfony\Component\Config\Loader\LoaderResolver;
use Symfony\Component\Routing\Exception\InvalidArgumentException;
use Symfony\Component\Routing\Loader\AttributeClassLoader;
use Symfony\Component\Routing\Loader\Psr4DirectoryLoader;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;
use Symfony\Component\Routing\Tests\Fixtures\Psr4Controllers\MyController;
use Symfony\Component\Routing\Tests\Fixtures\Psr4Controllers\SubNamespace\EvenDeeperNamespace\MyOtherController;
use Symfony\Component\Routing\Tests\Fixtures\Psr4Controllers\SubNamespace\MyChildController;
use Symfony\Component\Routing\Tests\Fixtures\Psr4Controllers\SubNamespace\MyControllerWithATrait;

class Psr4DirectoryLoaderTest extends TestCase
{
    public function testTopLevelController()
    {
        $route = $this->loadPsr4Controllers()->get('my_route');

        $this->assertSame('/my/route', $route->getPath());
        $this->assertSame(MyController::class.'::__invoke', $route->getDefault('_controller'));
    }

    public function testNestedController()
    {
        $collection = $this->loadPsr4Controllers();

        $route = $collection->get('my_other_controller_one');
        $this->assertSame('/my/other/route/first', $route->getPath());
        $this->assertSame(['PUT'], $route->getMethods());
        $this->assertSame(MyOtherController::class.'::firstAction', $route->getDefault('_controller'));

        $route = $collection->get('my_other_controller_two');
        $this->assertSame('/my/other/route/second', $route->getPath());
        $this->assertSame(['PUT'], $route->getMethods());
        $this->assertSame(MyOtherController::class.'::secondAction', $route->getDefault('_controller'));
    }

    public function testTraitController()
    {
        $route = $this->loadPsr4Controllers()->get('my_controller_with_a_trait');

        $this->assertSame('/my/controller/with/a/trait/a/route/from/a/trait', $route->getPath());
        $this->assertSame(MyControllerWithATrait::class.'::someAction', $route->getDefault('_controller'));
    }

    public function testAbstractController()
    {
        $route = $this->loadPsr4Controllers()->get('my_child_controller_from_abstract');

        $this->assertSame('/my/child/controller/a/route/from/an/abstract/controller', $route->getPath());
        $this->assertSame(MyChildController::class.'::someAction', $route->getDefault('_controller'));
    }

    /**
     * @dataProvider provideNamespacesThatNeedTrimming
     */
    public function testPsr4NamespaceTrim(string $namespace)
    {
        $route = $this->getLoader()
            ->load(
                ['path' => 'Psr4Controllers', 'namespace' => $namespace],
                'attribute',
            )
            ->get('my_route');

        $this->assertSame('/my/route', $route->getPath());
        $this->assertSame(MyController::class.'::__invoke', $route->getDefault('_controller'));
    }

    public static function provideNamespacesThatNeedTrimming(): array
    {
        return [
            ['\\Symfony\Component\Routing\Tests\Fixtures\Psr4Controllers'],
            ['Symfony\Component\Routing\Tests\Fixtures\Psr4Controllers\\'],
            ['\\Symfony\Component\Routing\Tests\Fixtures\Psr4Controllers\\'],
        ];
    }

    /**
     * @dataProvider provideInvalidPsr4Namespaces
     */
    public function testInvalidPsr4Namespace(string $namespace, string $expectedExceptionMessage)
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage($expectedExceptionMessage);

        $this->getLoader()->load(
            ['path' => 'Psr4Controllers', 'namespace' => $namespace],
            'attribute'
        );
    }

    public static function provideInvalidPsr4Namespaces(): array
    {
        return [
            'slash instead of back-slash' => [
                'namespace' => 'App\Application/Controllers',
                'expectedExceptionMessage' => 'Namespace "App\Application/Controllers" is not a valid PSR-4 prefix.',
            ],
            'invalid namespace' => [
                'namespace' => 'App\Contro llers',
                'expectedExceptionMessage' => 'Namespace "App\Contro llers" is not a valid PSR-4 prefix.',
            ],
        ];
    }

    private function loadPsr4Controllers(): RouteCollection
    {
        return $this->getLoader()->load(
            ['path' => 'Psr4Controllers', 'namespace' => 'Symfony\Component\Routing\Tests\Fixtures\Psr4Controllers'],
            'attribute'
        );
    }

    private function getLoader(): DelegatingLoader
    {
        $locator = new FileLocator(\dirname(__DIR__).'/Fixtures');

        return new DelegatingLoader(
            new LoaderResolver([
                new Psr4DirectoryLoader($locator),
                new class extends AttributeClassLoader {
                    protected function configureRoute(Route $route, \ReflectionClass $class, \ReflectionMethod $method, object $attr): void
                    {
                        $route->setDefault('_controller', $class->getName().'::'.$method->getName());
                    }
                },
            ])
        );
    }
}
