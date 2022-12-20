<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DependencyInjection\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;
use Symfony\Component\DependencyInjection\Exception\ServiceCircularReferenceException;
use Symfony\Component\DependencyInjection\Exception\ServiceNotFoundException;
use Symfony\Component\DependencyInjection\ParameterBag\FrozenParameterBag;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;
use Symfony\Contracts\Service\ResetInterface;

class ContainerTest extends TestCase
{
    public function testConstructor()
    {
        $sc = new Container();
        self::assertSame($sc, $sc->get('service_container'), '__construct() automatically registers itself as a service');

        $sc = new Container(new ParameterBag(['foo' => 'bar']));
        self::assertEquals(['foo' => 'bar'], $sc->getParameterBag()->all(), '__construct() takes an array of parameters as its first argument');
    }

    /**
     * @dataProvider dataForTestCamelize
     */
    public function testCamelize($id, $expected)
    {
        self::assertEquals($expected, Container::camelize($id), sprintf('Container::camelize("%s")', $id));
    }

    public function dataForTestCamelize()
    {
        return [
            ['foo_bar', 'FooBar'],
            ['foo.bar', 'Foo_Bar'],
            ['foo.bar_baz', 'Foo_BarBaz'],
            ['foo._bar', 'Foo_Bar'],
            ['foo_.bar', 'Foo_Bar'],
            ['_foo', 'Foo'],
            ['.foo', '_Foo'],
            ['foo_', 'Foo'],
            ['foo.', 'Foo_'],
            ['foo\bar', 'Foo_Bar'],
        ];
    }

    /**
     * @dataProvider dataForTestUnderscore
     */
    public function testUnderscore($id, $expected)
    {
        self::assertEquals($expected, Container::underscore($id), sprintf('Container::underscore("%s")', $id));
    }

    public function dataForTestUnderscore()
    {
        return [
            ['FooBar', 'foo_bar'],
            ['Foo_Bar', 'foo.bar'],
            ['Foo_BarBaz', 'foo.bar_baz'],
            ['FooBar_BazQux', 'foo_bar.baz_qux'],
            ['_Foo', '.foo'],
            ['Foo_', 'foo.'],
        ];
    }

    public function testCompile()
    {
        $sc = new Container(new ParameterBag(['foo' => 'bar']));
        self::assertFalse($sc->getParameterBag()->isResolved(), '->compile() resolves the parameter bag');
        $sc->compile();
        self::assertTrue($sc->getParameterBag()->isResolved(), '->compile() resolves the parameter bag');
        self::assertInstanceOf(FrozenParameterBag::class, $sc->getParameterBag(), '->compile() changes the parameter bag to a FrozenParameterBag instance');
        self::assertEquals(['foo' => 'bar'], $sc->getParameterBag()->all(), '->compile() copies the current parameters to the new parameter bag');
    }

    public function testIsCompiled()
    {
        $sc = new Container(new ParameterBag(['foo' => 'bar']));
        self::assertFalse($sc->isCompiled(), '->isCompiled() returns false if the container is not compiled');
        $sc->compile();
        self::assertTrue($sc->isCompiled(), '->isCompiled() returns true if the container is compiled');
    }

    public function testIsCompiledWithFrozenParameters()
    {
        $sc = new Container(new FrozenParameterBag(['foo' => 'bar']));
        self::assertFalse($sc->isCompiled(), '->isCompiled() returns false if the container is not compiled but the parameter bag is already frozen');
    }

    public function testGetParameterBag()
    {
        $sc = new Container();
        self::assertEquals([], $sc->getParameterBag()->all(), '->getParameterBag() returns an empty array if no parameter has been defined');
    }

    public function testGetSetParameter()
    {
        $sc = new Container(new ParameterBag(['foo' => 'bar']));
        $sc->setParameter('bar', 'foo');
        self::assertEquals('foo', $sc->getParameter('bar'), '->setParameter() sets the value of a new parameter');

        $sc->setParameter('foo', 'baz');
        self::assertEquals('baz', $sc->getParameter('foo'), '->setParameter() overrides previously set parameter');

        try {
            $sc->getParameter('baba');
            self::fail('->getParameter() thrown an \InvalidArgumentException if the key does not exist');
        } catch (\Exception $e) {
            self::assertInstanceOf(\InvalidArgumentException::class, $e, '->getParameter() thrown an \InvalidArgumentException if the key does not exist');
            self::assertEquals('You have requested a non-existent parameter "baba".', $e->getMessage(), '->getParameter() thrown an \InvalidArgumentException if the key does not exist');
        }
    }

    public function testGetSetParameterWithMixedCase()
    {
        $sc = new Container(new ParameterBag(['foo' => 'bar']));

        $sc->setParameter('Foo', 'baz1');
        self::assertEquals('bar', $sc->getParameter('foo'));
        self::assertEquals('baz1', $sc->getParameter('Foo'));
    }

    public function testGetServiceIds()
    {
        $sc = new Container();
        $sc->set('foo', $obj = new \stdClass());
        $sc->set('bar', $obj = new \stdClass());
        self::assertEquals(['service_container', 'foo', 'bar'], $sc->getServiceIds(), '->getServiceIds() returns all defined service ids');

        $sc = new ProjectServiceContainer();
        $sc->set('foo', $obj = new \stdClass());
        self::assertEquals(['service_container', 'bar', 'foo_bar', 'foo.baz', 'circular', 'throw_exception', 'throws_exception_on_service_configuration', 'internal_dependency', 'alias', 'foo'], $sc->getServiceIds(), '->getServiceIds() returns defined service ids by factory methods in the method map, followed by service ids defined by set()');
    }

    public function testSet()
    {
        $sc = new Container();
        $sc->set('._. \\o/', $foo = new \stdClass());
        self::assertSame($foo, $sc->get('._. \\o/'), '->set() sets a service');
    }

    public function testSetWithNullResetTheService()
    {
        $sc = new Container();
        $sc->set('foo', new \stdClass());
        $sc->set('foo', null);
        self::assertFalse($sc->has('foo'), '->set() with null service resets the service');
    }

    public function testSetReplacesAlias()
    {
        $c = new ProjectServiceContainer();

        $c->set('alias', $foo = new \stdClass());
        self::assertSame($foo, $c->get('alias'), '->set() replaces an existing alias');
    }

    public function testSetWithNullOnInitializedPredefinedService()
    {
        self::expectException(InvalidArgumentException::class);
        self::expectExceptionMessage('The "bar" service is already initialized, you cannot replace it.');
        $sc = new Container();
        $sc->set('foo', new \stdClass());
        $sc->set('foo', null);
        self::assertFalse($sc->has('foo'), '->set() with null service resets the service');

        $sc = new ProjectServiceContainer();
        $sc->get('bar');
        $sc->set('bar', null);
        self::assertTrue($sc->has('bar'), '->set() with null service resets the pre-defined service');
    }

    public function testSetWithNullOnUninitializedPredefinedService()
    {
        $sc = new Container();
        $sc->set('foo', new \stdClass());
        $sc->get('foo');
        $sc->set('foo', null);
        self::assertFalse($sc->has('foo'), '->set() with null service resets the service');

        $sc = new ProjectServiceContainer();
        $sc->set('bar', null);
        self::assertTrue($sc->has('bar'), '->set() with null service resets the pre-defined service');
    }

    public function testGet()
    {
        $sc = new ProjectServiceContainer();
        $sc->set('foo', $foo = new \stdClass());
        self::assertSame($foo, $sc->get('foo'), '->get() returns the service for the given id');
        self::assertSame($sc->__bar, $sc->get('bar'), '->get() returns the service for the given id');
        self::assertSame($sc->__foo_bar, $sc->get('foo_bar'), '->get() returns the service if a get*Method() is defined');
        self::assertSame($sc->__foo_baz, $sc->get('foo.baz'), '->get() returns the service if a get*Method() is defined');

        try {
            $sc->get('');
            self::fail('->get() throws a \InvalidArgumentException exception if the service is empty');
        } catch (\Exception $e) {
            self::assertInstanceOf(ServiceNotFoundException::class, $e, '->get() throws a ServiceNotFoundException exception if the service is empty');
        }
        self::assertNull($sc->get('', ContainerInterface::NULL_ON_INVALID_REFERENCE), '->get() returns null if the service is empty');
    }

    public function testCaseSensitivity()
    {
        $sc = new Container();
        $sc->set('foo', $foo1 = new \stdClass());
        $sc->set('Foo', $foo2 = new \stdClass());

        self::assertSame(['service_container', 'foo', 'Foo'], $sc->getServiceIds());
        self::assertSame($foo1, $sc->get('foo'), '->get() returns the service for the given id, case sensitively');
        self::assertSame($foo2, $sc->get('Foo'), '->get() returns the service for the given id, case sensitively');
    }

    public function testGetThrowServiceNotFoundException()
    {
        $sc = new ProjectServiceContainer();
        $sc->set('foo', $foo = new \stdClass());
        $sc->set('baz', $foo = new \stdClass());

        try {
            $sc->get('foo1');
            self::fail('->get() throws an Symfony\Component\DependencyInjection\Exception\ServiceNotFoundException if the key does not exist');
        } catch (\Exception $e) {
            self::assertInstanceOf(ServiceNotFoundException::class, $e, '->get() throws an Symfony\Component\DependencyInjection\Exception\ServiceNotFoundException if the key does not exist');
            self::assertEquals('You have requested a non-existent service "foo1". Did you mean this: "foo"?', $e->getMessage(), '->get() throws an Symfony\Component\DependencyInjection\Exception\ServiceNotFoundException with some advices');
        }

        try {
            $sc->get('bag');
            self::fail('->get() throws an Symfony\Component\DependencyInjection\Exception\ServiceNotFoundException if the key does not exist');
        } catch (\Exception $e) {
            self::assertInstanceOf(ServiceNotFoundException::class, $e, '->get() throws an Symfony\Component\DependencyInjection\Exception\ServiceNotFoundException if the key does not exist');
            self::assertEquals('You have requested a non-existent service "bag". Did you mean one of these: "bar", "baz"?', $e->getMessage(), '->get() throws an Symfony\Component\DependencyInjection\Exception\ServiceNotFoundException with some advices');
        }
    }

    public function testGetCircularReference()
    {
        $sc = new ProjectServiceContainer();
        try {
            $sc->get('circular');
            self::fail('->get() throws a ServiceCircularReferenceException if it contains circular reference');
        } catch (\Exception $e) {
            self::assertInstanceOf(ServiceCircularReferenceException::class, $e, '->get() throws a ServiceCircularReferenceException if it contains circular reference');
            self::assertStringStartsWith('Circular reference detected for service "circular"', $e->getMessage(), '->get() throws a \LogicException if it contains circular reference');
        }
    }

    public function testGetSyntheticServiceThrows()
    {
        self::expectException(ServiceNotFoundException::class);
        self::expectExceptionMessage('The "request" service is synthetic, it needs to be set at boot time before it can be used.');
        require_once __DIR__.'/Fixtures/php/services9_compiled.php';

        $container = new \ProjectServiceContainer();
        $container->get('request');
    }

    public function testGetRemovedServiceThrows()
    {
        self::expectException(ServiceNotFoundException::class);
        self::expectExceptionMessage('The "inlined" service or alias has been removed or inlined when the container was compiled. You should either make it public, or stop using the container directly and use dependency injection instead.');
        require_once __DIR__.'/Fixtures/php/services9_compiled.php';

        $container = new \ProjectServiceContainer();
        $container->get('inlined');
    }

    public function testHas()
    {
        $sc = new ProjectServiceContainer();
        $sc->set('foo', new \stdClass());
        self::assertFalse($sc->has('foo1'), '->has() returns false if the service does not exist');
        self::assertTrue($sc->has('foo'), '->has() returns true if the service exists');
        self::assertTrue($sc->has('bar'), '->has() returns true if a get*Method() is defined');
        self::assertTrue($sc->has('foo_bar'), '->has() returns true if a get*Method() is defined');
        self::assertTrue($sc->has('foo.baz'), '->has() returns true if a get*Method() is defined');
    }

    public function testInitialized()
    {
        $sc = new ProjectServiceContainer();
        $sc->set('foo', new \stdClass());
        self::assertTrue($sc->initialized('foo'), '->initialized() returns true if service is loaded');
        self::assertFalse($sc->initialized('foo1'), '->initialized() returns false if service is not loaded');
        self::assertFalse($sc->initialized('bar'), '->initialized() returns false if a service is defined, but not currently loaded');
        self::assertFalse($sc->initialized('alias'), '->initialized() returns false if an aliased service is not initialized');

        $sc->get('bar');
        self::assertTrue($sc->initialized('alias'), '->initialized() returns true for alias if aliased service is initialized');
    }

    public function testInitializedWithPrivateService()
    {
        $sc = new ProjectServiceContainer();
        $sc->get('internal_dependency');
        self::assertFalse($sc->initialized('internal'));
    }

    public function testReset()
    {
        $c = new Container();
        $c->set('bar', $bar = new class() implements ResetInterface {
            public $resetCounter = 0;

            public function reset(): void
            {
                ++$this->resetCounter;
            }
        });

        $c->reset();

        self::assertNull($c->get('bar', ContainerInterface::NULL_ON_INVALID_REFERENCE));
        self::assertSame(1, $bar->resetCounter);
    }

    public function testGetThrowsException()
    {
        self::expectException(\Exception::class);
        self::expectExceptionMessage('Something went terribly wrong!');
        $c = new ProjectServiceContainer();

        try {
            $c->get('throw_exception');
        } catch (\Exception $e) {
            // Do nothing.
        }

        // Retry, to make sure that get*Service() will be called.
        $c->get('throw_exception');
    }

    public function testGetThrowsExceptionOnServiceConfiguration()
    {
        $c = new ProjectServiceContainer();

        try {
            $c->get('throws_exception_on_service_configuration');
        } catch (\Exception $e) {
            // Do nothing.
        }

        self::assertFalse($c->initialized('throws_exception_on_service_configuration'));

        // Retry, to make sure that get*Service() will be called.
        try {
            $c->get('throws_exception_on_service_configuration');
        } catch (\Exception $e) {
            // Do nothing.
        }
        self::assertFalse($c->initialized('throws_exception_on_service_configuration'));
    }

    protected function getField($obj, $field)
    {
        $reflection = new \ReflectionProperty($obj, $field);
        $reflection->setAccessible(true);

        return $reflection->getValue($obj);
    }

    public function testAlias()
    {
        $c = new ProjectServiceContainer();

        self::assertTrue($c->has('alias'));
        self::assertSame($c->get('alias'), $c->get('bar'));
    }

    public function testThatCloningIsNotSupported()
    {
        $class = new \ReflectionClass(Container::class);
        $clone = $class->getMethod('__clone');
        self::assertFalse($class->isCloneable());
        self::assertTrue($clone->isPrivate());
    }

    public function testCheckExistenceOfAnInternalPrivateService()
    {
        $c = new ProjectServiceContainer();
        $c->get('internal_dependency');
        self::assertFalse($c->has('internal'));
    }

    public function testRequestAnInternalSharedPrivateService()
    {
        self::expectException(ServiceNotFoundException::class);
        self::expectExceptionMessage('You have requested a non-existent service "internal".');
        $c = new ProjectServiceContainer();
        $c->get('internal_dependency');
        $c->get('internal');
    }
}

class ProjectServiceContainer extends Container
{
    public $__bar;
    public $__foo_bar;
    public $__foo_baz;
    public $__internal;
    protected $privates;
    protected $methodMap = [
        'bar' => 'getBarService',
        'foo_bar' => 'getFooBarService',
        'foo.baz' => 'getFoo_BazService',
        'circular' => 'getCircularService',
        'throw_exception' => 'getThrowExceptionService',
        'throws_exception_on_service_configuration' => 'getThrowsExceptionOnServiceConfigurationService',
        'internal_dependency' => 'getInternalDependencyService',
    ];

    public function __construct()
    {
        parent::__construct();

        $this->__bar = new \stdClass();
        $this->__foo_bar = new \stdClass();
        $this->__foo_baz = new \stdClass();
        $this->__internal = new \stdClass();
        $this->privates = [];
        $this->aliases = ['alias' => 'bar'];
    }

    protected function getInternalService()
    {
        return $this->privates['internal'] = $this->__internal;
    }

    protected function getBarService()
    {
        return $this->services['bar'] = $this->__bar;
    }

    protected function getFooBarService()
    {
        return $this->__foo_bar;
    }

    protected function getFoo_BazService()
    {
        return $this->__foo_baz;
    }

    protected function getCircularService()
    {
        return $this->get('circular');
    }

    protected function getThrowExceptionService()
    {
        throw new \Exception('Something went terribly wrong!');
    }

    protected function getThrowsExceptionOnServiceConfigurationService()
    {
        $this->services['throws_exception_on_service_configuration'] = $instance = new \stdClass();

        throw new \Exception('Something was terribly wrong while trying to configure the service!');
    }

    protected function getInternalDependencyService()
    {
        $this->services['internal_dependency'] = $instance = new \stdClass();

        $instance->internal = $this->privates['internal'] ?? $this->getInternalService();

        return $instance;
    }
}
