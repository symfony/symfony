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
use Symfony\Component\DependencyInjection\Alias;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\FrozenParameterBag;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;

class ContainerTest extends TestCase
{
    public function testConstructor()
    {
        $sc = new Container();
        $this->assertSame($sc, $sc->get('service_container'), '__construct() automatically registers itself as a service');

        $sc = new Container(new ParameterBag(['foo' => 'bar']));
        $this->assertEquals(['foo' => 'bar'], $sc->getParameterBag()->all(), '__construct() takes an array of parameters as its first argument');
    }

    /**
     * @dataProvider dataForTestCamelize
     */
    public function testCamelize($id, $expected)
    {
        $this->assertEquals($expected, Container::camelize($id), sprintf('Container::camelize("%s")', $id));
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
        $this->assertEquals($expected, Container::underscore($id), sprintf('Container::underscore("%s")', $id));
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
        $this->assertFalse($sc->getParameterBag()->isResolved(), '->compile() resolves the parameter bag');
        $sc->compile();
        $this->assertTrue($sc->getParameterBag()->isResolved(), '->compile() resolves the parameter bag');
        $this->assertInstanceOf('Symfony\Component\DependencyInjection\ParameterBag\FrozenParameterBag', $sc->getParameterBag(), '->compile() changes the parameter bag to a FrozenParameterBag instance');
        $this->assertEquals(['foo' => 'bar'], $sc->getParameterBag()->all(), '->compile() copies the current parameters to the new parameter bag');
    }

    /**
     * @group legacy
     * @expectedDeprecation The Symfony\Component\DependencyInjection\Container::isFrozen() method is deprecated since Symfony 3.3 and will be removed in 4.0. Use the isCompiled() method instead.
     * @expectedDeprecation The Symfony\Component\DependencyInjection\Container::isFrozen() method is deprecated since Symfony 3.3 and will be removed in 4.0. Use the isCompiled() method instead.
     */
    public function testIsFrozen()
    {
        $sc = new Container(new ParameterBag(['foo' => 'bar']));
        $this->assertFalse($sc->isFrozen(), '->isFrozen() returns false if the parameters are not frozen');
        $sc->compile();
        $this->assertTrue($sc->isFrozen(), '->isFrozen() returns true if the parameters are frozen');
    }

    public function testIsCompiled()
    {
        $sc = new Container(new ParameterBag(['foo' => 'bar']));
        $this->assertFalse($sc->isCompiled(), '->isCompiled() returns false if the container is not compiled');
        $sc->compile();
        $this->assertTrue($sc->isCompiled(), '->isCompiled() returns true if the container is compiled');
    }

    public function testIsCompiledWithFrozenParameters()
    {
        $sc = new Container(new FrozenParameterBag(['foo' => 'bar']));
        $this->assertFalse($sc->isCompiled(), '->isCompiled() returns false if the container is not compiled but the parameter bag is already frozen');
    }

    public function testGetParameterBag()
    {
        $sc = new Container();
        $this->assertEquals([], $sc->getParameterBag()->all(), '->getParameterBag() returns an empty array if no parameter has been defined');
    }

    public function testGetSetParameter()
    {
        $sc = new Container(new ParameterBag(['foo' => 'bar']));
        $sc->setParameter('bar', 'foo');
        $this->assertEquals('foo', $sc->getParameter('bar'), '->setParameter() sets the value of a new parameter');

        $sc->setParameter('foo', 'baz');
        $this->assertEquals('baz', $sc->getParameter('foo'), '->setParameter() overrides previously set parameter');

        try {
            $sc->getParameter('baba');
            $this->fail('->getParameter() thrown an \InvalidArgumentException if the key does not exist');
        } catch (\Exception $e) {
            $this->assertInstanceOf('\InvalidArgumentException', $e, '->getParameter() thrown an \InvalidArgumentException if the key does not exist');
            $this->assertEquals('You have requested a non-existent parameter "baba".', $e->getMessage(), '->getParameter() thrown an \InvalidArgumentException if the key does not exist');
        }
    }

    /**
     * @group legacy
     * @expectedDeprecation Parameter names will be made case sensitive in Symfony 4.0. Using "Foo" instead of "foo" is deprecated since Symfony 3.4.
     * @expectedDeprecation Parameter names will be made case sensitive in Symfony 4.0. Using "FOO" instead of "foo" is deprecated since Symfony 3.4.
     */
    public function testGetSetParameterWithMixedCase()
    {
        $sc = new Container(new ParameterBag(['foo' => 'bar']));

        $sc->setParameter('Foo', 'baz1');
        $this->assertEquals('baz1', $sc->getParameter('foo'), '->setParameter() converts the key to lowercase');
        $this->assertEquals('baz1', $sc->getParameter('FOO'), '->getParameter() converts the key to lowercase');
    }

    public function testGetServiceIds()
    {
        $sc = new Container();
        $sc->set('foo', $obj = new \stdClass());
        $sc->set('bar', $obj = new \stdClass());
        $this->assertEquals(['service_container', 'foo', 'bar'], $sc->getServiceIds(), '->getServiceIds() returns all defined service ids');

        $sc = new ProjectServiceContainer();
        $sc->set('foo', $obj = new \stdClass());
        $this->assertEquals(['service_container', 'internal', 'bar', 'foo_bar', 'foo.baz', 'circular', 'throw_exception', 'throws_exception_on_service_configuration', 'internal_dependency', 'foo'], $sc->getServiceIds(), '->getServiceIds() returns defined service ids by factory methods in the method map, followed by service ids defined by set()');
    }

    /**
     * @group legacy
     * @expectedDeprecation Generating a dumped container without populating the method map is deprecated since Symfony 3.2 and will be unsupported in 4.0. Update your dumper to generate the method map.
     */
    public function testGetLegacyServiceIds()
    {
        $sc = new LegacyProjectServiceContainer();
        $sc->set('foo', $obj = new \stdClass());

        $this->assertEquals(['internal', 'bar', 'foo_bar', 'foo.baz', 'circular', 'throw_exception', 'throws_exception_on_service_configuration', 'service_container', 'foo'], $sc->getServiceIds(), '->getServiceIds() returns defined service ids by getXXXService() methods, followed by service ids defined by set()');
    }

    public function testSet()
    {
        $sc = new Container();
        $sc->set('._. \\o/', $foo = new \stdClass());
        $this->assertSame($foo, $sc->get('._. \\o/'), '->set() sets a service');
    }

    public function testSetWithNullResetTheService()
    {
        $sc = new Container();
        $sc->set('foo', null);
        $this->assertFalse($sc->has('foo'), '->set() with null service resets the service');
    }

    public function testSetReplacesAlias()
    {
        $c = new ProjectServiceContainer();

        $c->set('alias', $foo = new \stdClass());
        $this->assertSame($foo, $c->get('alias'), '->set() replaces an existing alias');
    }

    /**
     * @group legacy
     * @expectedDeprecation The "bar" service is already initialized, unsetting it is deprecated since Symfony 3.3 and will fail in 4.0.
     */
    public function testSetWithNullOnInitializedPredefinedService()
    {
        $sc = new Container();
        $sc->set('foo', new \stdClass());
        $sc->set('foo', null);
        $this->assertFalse($sc->has('foo'), '->set() with null service resets the service');

        $sc = new ProjectServiceContainer();
        $sc->get('bar');
        $sc->set('bar', null);
        $this->assertTrue($sc->has('bar'), '->set() with null service resets the pre-defined service');
    }

    public function testSetWithNullOnUninitializedPredefinedService()
    {
        $sc = new Container();
        $sc->set('foo', new \stdClass());
        $sc->get('foo', null);
        $sc->set('foo', null);
        $this->assertFalse($sc->has('foo'), '->set() with null service resets the service');

        $sc = new ProjectServiceContainer();
        $sc->set('bar', null);
        $this->assertTrue($sc->has('bar'), '->set() with null service resets the pre-defined service');
    }

    public function testGet()
    {
        $sc = new ProjectServiceContainer();
        $sc->set('foo', $foo = new \stdClass());
        $this->assertSame($foo, $sc->get('foo'), '->get() returns the service for the given id');
        $this->assertSame($sc->__bar, $sc->get('bar'), '->get() returns the service for the given id');
        $this->assertSame($sc->__foo_bar, $sc->get('foo_bar'), '->get() returns the service if a get*Method() is defined');
        $this->assertSame($sc->__foo_baz, $sc->get('foo.baz'), '->get() returns the service if a get*Method() is defined');

        try {
            $sc->get('');
            $this->fail('->get() throws a \InvalidArgumentException exception if the service is empty');
        } catch (\Exception $e) {
            $this->assertInstanceOf('Symfony\Component\DependencyInjection\Exception\ServiceNotFoundException', $e, '->get() throws a ServiceNotFoundException exception if the service is empty');
        }
        $this->assertNull($sc->get('', ContainerInterface::NULL_ON_INVALID_REFERENCE), '->get() returns null if the service is empty');
    }

    /**
     * @group legacy
     * @expectedDeprecation Service identifiers will be made case sensitive in Symfony 4.0. Using "Foo" instead of "foo" is deprecated since Symfony 3.3.
     */
    public function testGetInsensitivity()
    {
        $sc = new ProjectServiceContainer();
        $sc->set('foo', $foo = new \stdClass());
        $this->assertSame($foo, $sc->get('Foo'), '->get() returns the service for the given id, and converts id to lowercase');
    }

    /**
     * @group legacy
     * @expectedDeprecation Service identifiers will be made case sensitive in Symfony 4.0. Using "foo" instead of "Foo" is deprecated since Symfony 3.3.
     */
    public function testNormalizeIdKeepsCase()
    {
        $sc = new ProjectServiceContainer();
        $sc->normalizeId('Foo', true);
        $this->assertSame('Foo', $sc->normalizeId('foo'));
    }

    /**
     * @group legacy
     * @expectedDeprecation Service identifiers will be made case sensitive in Symfony 4.0. Using "Foo" instead of "foo" is deprecated since Symfony 3.3.
     * @expectedDeprecation Generating a dumped container without populating the method map is deprecated since Symfony 3.2 and will be unsupported in 4.0. Update your dumper to generate the method map.
     * @expectedDeprecation Generating a dumped container without populating the method map is deprecated since Symfony 3.2 and will be unsupported in 4.0. Update your dumper to generate the method map.
     * @expectedDeprecation Generating a dumped container without populating the method map is deprecated since Symfony 3.2 and will be unsupported in 4.0. Update your dumper to generate the method map.
     * @expectedDeprecation Generating a dumped container without populating the method map is deprecated since Symfony 3.2 and will be unsupported in 4.0. Update your dumper to generate the method map.
     */
    public function testLegacyGet()
    {
        $sc = new LegacyProjectServiceContainer();
        $sc->set('foo', $foo = new \stdClass());

        $this->assertSame($foo, $sc->get('foo'), '->get() returns the service for the given id');
        $this->assertSame($foo, $sc->get('Foo'), '->get() returns the service for the given id, and converts id to lowercase');
        $this->assertSame($sc->__bar, $sc->get('bar'), '->get() returns the service for the given id');
        $this->assertSame($sc->__foo_bar, $sc->get('foo_bar'), '->get() returns the service if a get*Method() is defined');
        $this->assertSame($sc->__foo_baz, $sc->get('foo.baz'), '->get() returns the service if a get*Method() is defined');
        $this->assertSame($sc->__foo_baz, $sc->get('foo\\baz'), '->get() returns the service if a get*Method() is defined');

        $sc->set('bar', $bar = new \stdClass());
        $this->assertSame($bar, $sc->get('bar'), '->get() prefers to return a service defined with set() than one defined with a getXXXMethod()');

        try {
            $sc->get('');
            $this->fail('->get() throws a \InvalidArgumentException exception if the service is empty');
        } catch (\Exception $e) {
            $this->assertInstanceOf('Symfony\Component\DependencyInjection\Exception\ServiceNotFoundException', $e, '->get() throws a ServiceNotFoundException exception if the service is empty');
        }
        $this->assertNull($sc->get('', ContainerInterface::NULL_ON_INVALID_REFERENCE), '->get() returns null if the service is empty');
    }

    public function testGetThrowServiceNotFoundException()
    {
        $sc = new ProjectServiceContainer();
        $sc->set('foo', $foo = new \stdClass());
        $sc->set('baz', $foo = new \stdClass());

        try {
            $sc->get('foo1');
            $this->fail('->get() throws an Symfony\Component\DependencyInjection\Exception\ServiceNotFoundException if the key does not exist');
        } catch (\Exception $e) {
            $this->assertInstanceOf('Symfony\Component\DependencyInjection\Exception\ServiceNotFoundException', $e, '->get() throws an Symfony\Component\DependencyInjection\Exception\ServiceNotFoundException if the key does not exist');
            $this->assertEquals('You have requested a non-existent service "foo1". Did you mean this: "foo"?', $e->getMessage(), '->get() throws an Symfony\Component\DependencyInjection\Exception\ServiceNotFoundException with some advices');
        }

        try {
            $sc->get('bag');
            $this->fail('->get() throws an Symfony\Component\DependencyInjection\Exception\ServiceNotFoundException if the key does not exist');
        } catch (\Exception $e) {
            $this->assertInstanceOf('Symfony\Component\DependencyInjection\Exception\ServiceNotFoundException', $e, '->get() throws an Symfony\Component\DependencyInjection\Exception\ServiceNotFoundException if the key does not exist');
            $this->assertEquals('You have requested a non-existent service "bag". Did you mean one of these: "bar", "baz"?', $e->getMessage(), '->get() throws an Symfony\Component\DependencyInjection\Exception\ServiceNotFoundException with some advices');
        }
    }

    public function testGetCircularReference()
    {
        $sc = new ProjectServiceContainer();
        try {
            $sc->get('circular');
            $this->fail('->get() throws a ServiceCircularReferenceException if it contains circular reference');
        } catch (\Exception $e) {
            $this->assertInstanceOf('\Symfony\Component\DependencyInjection\Exception\ServiceCircularReferenceException', $e, '->get() throws a ServiceCircularReferenceException if it contains circular reference');
            $this->assertStringStartsWith('Circular reference detected for service "circular"', $e->getMessage(), '->get() throws a \LogicException if it contains circular reference');
        }
    }

    /**
     * @expectedException \Symfony\Component\DependencyInjection\Exception\ServiceNotFoundException
     * @expectedExceptionMessage The "request" service is synthetic, it needs to be set at boot time before it can be used.
     */
    public function testGetSyntheticServiceThrows()
    {
        require_once __DIR__.'/Fixtures/php/services9_compiled.php';

        $container = new \ProjectServiceContainer();
        $container->get('request');
    }

    /**
     * @expectedException \Symfony\Component\DependencyInjection\Exception\ServiceNotFoundException
     * @expectedExceptionMessage The "inlined" service or alias has been removed or inlined when the container was compiled. You should either make it public, or stop using the container directly and use dependency injection instead.
     */
    public function testGetRemovedServiceThrows()
    {
        require_once __DIR__.'/Fixtures/php/services9_compiled.php';

        $container = new \ProjectServiceContainer();
        $container->get('inlined');
    }

    public function testHas()
    {
        $sc = new ProjectServiceContainer();
        $sc->set('foo', new \stdClass());
        $this->assertFalse($sc->has('foo1'), '->has() returns false if the service does not exist');
        $this->assertTrue($sc->has('foo'), '->has() returns true if the service exists');
        $this->assertTrue($sc->has('bar'), '->has() returns true if a get*Method() is defined');
        $this->assertTrue($sc->has('foo_bar'), '->has() returns true if a get*Method() is defined');
        $this->assertTrue($sc->has('foo.baz'), '->has() returns true if a get*Method() is defined');
    }

    /**
     * @group legacy
     * @expectedDeprecation Generating a dumped container without populating the method map is deprecated since Symfony 3.2 and will be unsupported in 4.0. Update your dumper to generate the method map.
     * @expectedDeprecation Generating a dumped container without populating the method map is deprecated since Symfony 3.2 and will be unsupported in 4.0. Update your dumper to generate the method map.
     * @expectedDeprecation Generating a dumped container without populating the method map is deprecated since Symfony 3.2 and will be unsupported in 4.0. Update your dumper to generate the method map.
     * @expectedDeprecation Generating a dumped container without populating the method map is deprecated since Symfony 3.2 and will be unsupported in 4.0. Update your dumper to generate the method map.
     */
    public function testLegacyHas()
    {
        $sc = new LegacyProjectServiceContainer();
        $sc->set('foo', new \stdClass());

        $this->assertFalse($sc->has('foo1'), '->has() returns false if the service does not exist');
        $this->assertTrue($sc->has('foo'), '->has() returns true if the service exists');
        $this->assertTrue($sc->has('bar'), '->has() returns true if a get*Method() is defined');
        $this->assertTrue($sc->has('foo_bar'), '->has() returns true if a get*Method() is defined');
        $this->assertTrue($sc->has('foo.baz'), '->has() returns true if a get*Method() is defined');
        $this->assertTrue($sc->has('foo\\baz'), '->has() returns true if a get*Method() is defined');
    }

    public function testScalarService()
    {
        $c = new Container();

        $c->set('foo', 'some value');

        $this->assertTrue($c->has('foo'));
        $this->assertSame('some value', $c->get('foo'));
    }

    public function testInitialized()
    {
        $sc = new ProjectServiceContainer();
        $sc->set('foo', new \stdClass());
        $this->assertTrue($sc->initialized('foo'), '->initialized() returns true if service is loaded');
        $this->assertFalse($sc->initialized('foo1'), '->initialized() returns false if service is not loaded');
        $this->assertFalse($sc->initialized('bar'), '->initialized() returns false if a service is defined, but not currently loaded');
        $this->assertFalse($sc->initialized('alias'), '->initialized() returns false if an aliased service is not initialized');

        $sc->get('bar');
        $this->assertTrue($sc->initialized('alias'), '->initialized() returns true for alias if aliased service is initialized');
    }

    /**
     * @group legacy
     * @expectedDeprecation Checking for the initialization of the "internal" private service is deprecated since Symfony 3.4 and won't be supported anymore in Symfony 4.0.
     */
    public function testInitializedWithPrivateService()
    {
        $sc = new ProjectServiceContainer();
        $sc->get('internal_dependency');
        $this->assertTrue($sc->initialized('internal'));
    }

    public function testReset()
    {
        $c = new Container();
        $c->set('bar', new \stdClass());

        $c->reset();

        $this->assertNull($c->get('bar', ContainerInterface::NULL_ON_INVALID_REFERENCE));
    }

    /**
     * @expectedException \Exception
     * @expectedExceptionMessage Something went terribly wrong!
     */
    public function testGetThrowsException()
    {
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

        $this->assertFalse($c->initialized('throws_exception_on_service_configuration'));

        // Retry, to make sure that get*Service() will be called.
        try {
            $c->get('throws_exception_on_service_configuration');
        } catch (\Exception $e) {
            // Do nothing.
        }
        $this->assertFalse($c->initialized('throws_exception_on_service_configuration'));
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

        $this->assertTrue($c->has('alias'));
        $this->assertSame($c->get('alias'), $c->get('bar'));
    }

    public function testThatCloningIsNotSupported()
    {
        $class = new \ReflectionClass('Symfony\Component\DependencyInjection\Container');
        $clone = $class->getMethod('__clone');
        $this->assertFalse($class->isCloneable());
        $this->assertTrue($clone->isPrivate());
    }

    /**
     * @group legacy
     * @expectedDeprecation The "internal" service is private, unsetting it is deprecated since Symfony 3.2 and will fail in 4.0.
     */
    public function testUnsetInternalPrivateServiceIsDeprecated()
    {
        $c = new ProjectServiceContainer();
        $c->set('internal', null);
    }

    /**
     * @group legacy
     * @expectedDeprecation The "internal" service is private, replacing it is deprecated since Symfony 3.2 and will fail in 4.0.
     */
    public function testChangeInternalPrivateServiceIsDeprecated()
    {
        $c = new ProjectServiceContainer();
        $c->set('internal', $internal = new \stdClass());
        $this->assertSame($c->get('internal'), $internal);
    }

    /**
     * @group legacy
     * @expectedDeprecation The "internal" service is private, checking for its existence is deprecated since Symfony 3.2 and will fail in 4.0.
     */
    public function testCheckExistenceOfAnInternalPrivateServiceIsDeprecated()
    {
        $c = new ProjectServiceContainer();
        $c->get('internal_dependency');
        $this->assertTrue($c->has('internal'));
    }

    /**
     * @group legacy
     * @expectedDeprecation The "internal" service is private, getting it from the container is deprecated since Symfony 3.2 and will fail in 4.0. You should either make the service public, or stop using the container directly and use dependency injection instead.
     */
    public function testRequestAnInternalSharedPrivateServiceIsDeprecated()
    {
        $c = new ProjectServiceContainer();
        $c->get('internal_dependency');
        $c->get('internal');
    }

    /**
     * @group legacy
     * @expectedDeprecation The "bar" service is already initialized, replacing it is deprecated since Symfony 3.3 and will fail in 4.0.
     */
    public function testReplacingAPreDefinedServiceIsDeprecated()
    {
        $c = new ProjectServiceContainer();
        $c->set('bar', new \stdClass());
        $c->set('bar', $bar = new \stdClass());

        $this->assertSame($bar, $c->get('bar'), '->set() replaces a pre-defined service');
    }

    /**
     * @group legacy
     * @expectedDeprecation The "synthetic" service is private, replacing it is deprecated since Symfony 3.2 and will fail in 4.0.
     */
    public function testSetWithPrivateSyntheticServiceThrowsDeprecation()
    {
        $c = new ProjectServiceContainer();
        $c->set('synthetic', new \stdClass());
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
        'internal' => 'getInternalService',
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
        $this->privates = [
            'internal' => true,
            'synthetic' => true,
        ];
        $this->aliases = ['alias' => 'bar'];
        $this->syntheticIds['synthetic'] = true;
    }

    protected function getInternalService()
    {
        return $this->services['internal'] = $this->__internal;
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

        $instance->internal = isset($this->services['internal']) ? $this->services['internal'] : $this->getInternalService();

        return $instance;
    }
}

class LegacyProjectServiceContainer extends Container
{
    public $__bar;
    public $__foo_bar;
    public $__foo_baz;
    public $__internal;

    public function __construct()
    {
        parent::__construct();

        $this->__bar = new \stdClass();
        $this->__foo_bar = new \stdClass();
        $this->__foo_baz = new \stdClass();
        $this->__internal = new \stdClass();
        $this->privates = ['internal' => true];
        $this->aliases = ['alias' => 'bar'];
    }

    protected function getInternalService()
    {
        return $this->__internal;
    }

    protected function getBarService()
    {
        return $this->__bar;
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
}
