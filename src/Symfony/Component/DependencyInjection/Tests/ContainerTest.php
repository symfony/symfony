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

use Symfony\Component\DependencyInjection\Scope;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;
use Symfony\Component\DependencyInjection\Exception\InactiveScopeException;

class ContainerTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @covers Symfony\Component\DependencyInjection\Container::__construct
     */
    public function testConstructor()
    {
        $sc = new Container();
        $this->assertSame($sc, $sc->get('service_container'), '__construct() automatically registers itself as a service');

        $sc = new Container(new ParameterBag(array('foo' => 'bar')));
        $this->assertEquals(array('foo' => 'bar'), $sc->getParameterBag()->all(), '__construct() takes an array of parameters as its first argument');
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
        return array(
            array('foo_bar', 'FooBar'),
            array('foo.bar', 'Foo_Bar'),
            array('foo.bar_baz', 'Foo_BarBaz'),
            array('foo._bar', 'Foo_Bar'),
            array('foo_.bar', 'Foo_Bar'),
            array('_foo', 'Foo'),
            array('.foo', '_Foo'),
            array('foo_', 'Foo'),
            array('foo.', 'Foo_'),
            array('foo\bar', 'Foo_Bar'),
        );
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
        return array(
            array('FooBar', 'foo_bar'),
            array('Foo_Bar', 'foo.bar'),
            array('Foo_BarBaz', 'foo.bar_baz'),
            array('FooBar_BazQux', 'foo_bar.baz_qux'),
            array('_Foo', '.foo'),
            array('Foo_', 'foo.'),
        );
    }

    /**
     * @covers Symfony\Component\DependencyInjection\Container::compile
     */
    public function testCompile()
    {
        $sc = new Container(new ParameterBag(array('foo' => 'bar')));
        $this->assertFalse($sc->getParameterBag()->isResolved(), '->compile() resolves the parameter bag');
        $sc->compile();
        $this->assertTrue($sc->getParameterBag()->isResolved(), '->compile() resolves the parameter bag');
        $this->assertInstanceOf('Symfony\Component\DependencyInjection\ParameterBag\FrozenParameterBag', $sc->getParameterBag(), '->compile() changes the parameter bag to a FrozenParameterBag instance');
        $this->assertEquals(array('foo' => 'bar'), $sc->getParameterBag()->all(), '->compile() copies the current parameters to the new parameter bag');
    }

    /**
     * @covers Symfony\Component\DependencyInjection\Container::isFrozen
     */
    public function testIsFrozen()
    {
        $sc = new Container(new ParameterBag(array('foo' => 'bar')));
        $this->assertFalse($sc->isFrozen(), '->isFrozen() returns false if the parameters are not frozen');
        $sc->compile();
        $this->assertTrue($sc->isFrozen(), '->isFrozen() returns true if the parameters are frozen');
    }

    /**
     * @covers Symfony\Component\DependencyInjection\Container::getParameterBag
     */
    public function testGetParameterBag()
    {
        $sc = new Container();
        $this->assertEquals(array(), $sc->getParameterBag()->all(), '->getParameterBag() returns an empty array if no parameter has been defined');
    }

    /**
     * @covers Symfony\Component\DependencyInjection\Container::setParameter
     * @covers Symfony\Component\DependencyInjection\Container::getParameter
     */
    public function testGetSetParameter()
    {
        $sc = new Container(new ParameterBag(array('foo' => 'bar')));
        $sc->setParameter('bar', 'foo');
        $this->assertEquals('foo', $sc->getParameter('bar'), '->setParameter() sets the value of a new parameter');

        $sc->setParameter('foo', 'baz');
        $this->assertEquals('baz', $sc->getParameter('foo'), '->setParameter() overrides previously set parameter');

        $sc->setParameter('Foo', 'baz1');
        $this->assertEquals('baz1', $sc->getParameter('foo'), '->setParameter() converts the key to lowercase');
        $this->assertEquals('baz1', $sc->getParameter('FOO'), '->getParameter() converts the key to lowercase');

        try {
            $sc->getParameter('baba');
            $this->fail('->getParameter() thrown an \InvalidArgumentException if the key does not exist');
        } catch (\Exception $e) {
            $this->assertInstanceOf('\InvalidArgumentException', $e, '->getParameter() thrown an \InvalidArgumentException if the key does not exist');
            $this->assertEquals('You have requested a non-existent parameter "baba".', $e->getMessage(), '->getParameter() thrown an \InvalidArgumentException if the key does not exist');
        }
    }

    /**
     * @covers Symfony\Component\DependencyInjection\Container::getServiceIds
     */
    public function testGetServiceIds()
    {
        $sc = new Container();
        $sc->set('foo', $obj = new \stdClass());
        $sc->set('bar', $obj = new \stdClass());
        $this->assertEquals(array('service_container', 'foo', 'bar'), $sc->getServiceIds(), '->getServiceIds() returns all defined service ids');

        $sc = new ProjectServiceContainer();
        $sc->set('foo', $obj = new \stdClass());
        $this->assertEquals(array('scoped', 'scoped_foo', 'scoped_synchronized_foo', 'inactive', 'bar', 'foo_bar', 'foo.baz', 'circular', 'throw_exception', 'throws_exception_on_service_configuration', 'service_container', 'foo'), $sc->getServiceIds(), '->getServiceIds() returns defined service ids by getXXXService() methods, followed by service ids defined by set()');
    }

    /**
     * @covers Symfony\Component\DependencyInjection\Container::set
     */
    public function testSet()
    {
        $sc = new Container();
        $sc->set('foo', $foo = new \stdClass());
        $this->assertEquals($foo, $sc->get('foo'), '->set() sets a service');
    }

    /**
     * @covers Symfony\Component\DependencyInjection\Container::set
     */
    public function testSetWithNullResetTheService()
    {
        $sc = new Container();
        $sc->set('foo', null);
        $this->assertFalse($sc->has('foo'), '->set() with null service resets the service');
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testSetDoesNotAllowPrototypeScope()
    {
        $c = new Container();
        $c->set('foo', new \stdClass(), Container::SCOPE_PROTOTYPE);
    }

    /**
     * @expectedException \RuntimeException
     */
    public function testSetDoesNotAllowInactiveScope()
    {
        $c = new Container();
        $c->addScope(new Scope('foo'));
        $c->set('foo', new \stdClass(), 'foo');
    }

    public function testSetAlsoSetsScopedService()
    {
        $c = new Container();
        $c->addScope(new Scope('foo'));
        $c->enterScope('foo');
        $c->set('foo', $foo = new \stdClass(), 'foo');

        $scoped = $this->getField($c, 'scopedServices');
        $this->assertTrue(isset($scoped['foo']['foo']), '->set() sets a scoped service');
        $this->assertSame($foo, $scoped['foo']['foo'], '->set() sets a scoped service');
    }

    public function testSetAlsoCallsSynchronizeService()
    {
        $c = new ProjectServiceContainer();
        $c->addScope(new Scope('foo'));
        $c->enterScope('foo');
        $c->set('scoped_synchronized_foo', $bar = new \stdClass(), 'foo');
        $this->assertTrue($c->synchronized, '->set() calls synchronize*Service() if it is defined for the service');
    }

    /**
     * @covers Symfony\Component\DependencyInjection\Container::get
     */
    public function testGet()
    {
        $sc = new ProjectServiceContainer();
        $sc->set('foo', $foo = new \stdClass());
        $this->assertEquals($foo, $sc->get('foo'), '->get() returns the service for the given id');
        $this->assertEquals($foo, $sc->get('Foo'), '->get() returns the service for the given id, and converts id to lowercase');
        $this->assertEquals($sc->__bar, $sc->get('bar'), '->get() returns the service for the given id');
        $this->assertEquals($sc->__foo_bar, $sc->get('foo_bar'), '->get() returns the service if a get*Method() is defined');
        $this->assertEquals($sc->__foo_baz, $sc->get('foo.baz'), '->get() returns the service if a get*Method() is defined');
        $this->assertEquals($sc->__foo_baz, $sc->get('foo\\baz'), '->get() returns the service if a get*Method() is defined');

        $sc->set('bar', $bar = new \stdClass());
        $this->assertEquals($bar, $sc->get('bar'), '->get() prefers to return a service defined with set() than one defined with a getXXXMethod()');

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
        $sc->set('bar', $foo = new \stdClass());
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
     * @covers Symfony\Component\DependencyInjection\Container::get
     */
    public function testGetReturnsNullOnInactiveScope()
    {
        $sc = new ProjectServiceContainer();
        $this->assertNull($sc->get('inactive', ContainerInterface::NULL_ON_INVALID_REFERENCE));
    }

    /**
     * @covers Symfony\Component\DependencyInjection\Container::has
     */
    public function testHas()
    {
        $sc = new ProjectServiceContainer();
        $sc->set('foo', new \stdClass());
        $this->assertFalse($sc->has('foo1'), '->has() returns false if the service does not exist');
        $this->assertTrue($sc->has('foo'), '->has() returns true if the service exists');
        $this->assertTrue($sc->has('bar'), '->has() returns true if a get*Method() is defined');
        $this->assertTrue($sc->has('foo_bar'), '->has() returns true if a get*Method() is defined');
        $this->assertTrue($sc->has('foo.baz'), '->has() returns true if a get*Method() is defined');
        $this->assertTrue($sc->has('foo\\baz'), '->has() returns true if a get*Method() is defined');
    }

    /**
     * @covers Symfony\Component\DependencyInjection\Container::initialized
     */
    public function testInitialized()
    {
        $sc = new ProjectServiceContainer();
        $sc->set('foo', new \stdClass());
        $this->assertTrue($sc->initialized('foo'), '->initialized() returns true if service is loaded');
        $this->assertFalse($sc->initialized('foo1'), '->initialized() returns false if service is not loaded');
        $this->assertFalse($sc->initialized('bar'), '->initialized() returns false if a service is defined, but not currently loaded');
    }

    public function testEnterLeaveCurrentScope()
    {
        $container = new ProjectServiceContainer();
        $container->addScope(new Scope('foo'));

        $container->enterScope('foo');
        $scoped1 = $container->get('scoped');
        $scopedFoo1 = $container->get('scoped_foo');

        $container->enterScope('foo');
        $scoped2 = $container->get('scoped');
        $scoped3 = $container->get('scoped');
        $scopedFoo2 = $container->get('scoped_foo');

        $container->leaveScope('foo');
        $scoped4 = $container->get('scoped');
        $scopedFoo3 = $container->get('scoped_foo');

        $this->assertNotSame($scoped1, $scoped2);
        $this->assertSame($scoped2, $scoped3);
        $this->assertSame($scoped1, $scoped4);
        $this->assertNotSame($scopedFoo1, $scopedFoo2);
        $this->assertSame($scopedFoo1, $scopedFoo3);
    }

    public function testEnterLeaveScopeWithChildScopes()
    {
        $container = new Container();
        $container->addScope(new Scope('foo'));
        $container->addScope(new Scope('bar', 'foo'));

        $this->assertFalse($container->isScopeActive('foo'));

        $container->enterScope('foo');
        $container->enterScope('bar');

        $this->assertTrue($container->isScopeActive('foo'));
        $this->assertFalse($container->has('a'));

        $a = new \stdClass();
        $container->set('a', $a, 'bar');

        $scoped = $this->getField($container, 'scopedServices');
        $this->assertTrue(isset($scoped['bar']['a']));
        $this->assertSame($a, $scoped['bar']['a']);
        $this->assertTrue($container->has('a'));

        $container->leaveScope('foo');

        $scoped = $this->getField($container, 'scopedServices');
        $this->assertFalse(isset($scoped['bar']));
        $this->assertFalse($container->isScopeActive('foo'));
        $this->assertFalse($container->has('a'));
    }

    public function testEnterScopeRecursivelyWithInactiveChildScopes()
    {
        $container = new Container();
        $container->addScope(new Scope('foo'));
        $container->addScope(new Scope('bar', 'foo'));

        $this->assertFalse($container->isScopeActive('foo'));

        $container->enterScope('foo');

        $this->assertTrue($container->isScopeActive('foo'));
        $this->assertFalse($container->isScopeActive('bar'));
        $this->assertFalse($container->has('a'));

        $a = new \stdClass();
        $container->set('a', $a, 'foo');

        $scoped = $this->getField($container, 'scopedServices');
        $this->assertTrue(isset($scoped['foo']['a']));
        $this->assertSame($a, $scoped['foo']['a']);
        $this->assertTrue($container->has('a'));

        $container->enterScope('foo');

        $scoped = $this->getField($container, 'scopedServices');
        $this->assertFalse(isset($scoped['a']));
        $this->assertTrue($container->isScopeActive('foo'));
        $this->assertFalse($container->isScopeActive('bar'));
        $this->assertFalse($container->has('a'));

        $container->enterScope('bar');

        $this->assertTrue($container->isScopeActive('bar'));

        $container->leaveScope('foo');

        $this->assertTrue($container->isScopeActive('foo'));
        $this->assertFalse($container->isScopeActive('bar'));
        $this->assertTrue($container->has('a'));
    }

    public function testEnterChildScopeRecursively()
    {
        $container = new Container();
        $container->addScope(new Scope('foo'));
        $container->addScope(new Scope('bar', 'foo'));

        $container->enterScope('foo');
        $container->enterScope('bar');

        $this->assertTrue($container->isScopeActive('bar'));
        $this->assertFalse($container->has('a'));

        $a = new \stdClass();
        $container->set('a', $a, 'bar');

        $scoped = $this->getField($container, 'scopedServices');
        $this->assertTrue(isset($scoped['bar']['a']));
        $this->assertSame($a, $scoped['bar']['a']);
        $this->assertTrue($container->has('a'));

        $container->enterScope('bar');

        $scoped = $this->getField($container, 'scopedServices');
        $this->assertFalse(isset($scoped['a']));
        $this->assertTrue($container->isScopeActive('foo'));
        $this->assertTrue($container->isScopeActive('bar'));
        $this->assertFalse($container->has('a'));

        $container->leaveScope('bar');

        $this->assertTrue($container->isScopeActive('foo'));
        $this->assertTrue($container->isScopeActive('bar'));
        $this->assertTrue($container->has('a'));
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testEnterScopeNotAdded()
    {
      $container = new Container();
      $container->enterScope('foo');
    }

    /**
     * @expectedException \RuntimeException
     */
    public function testEnterScopeDoesNotAllowInactiveParentScope()
    {
        $container = new Container();
        $container->addScope(new Scope('foo'));
        $container->addScope(new Scope('bar', 'foo'));
        $container->enterScope('bar');
    }

    public function testLeaveScopeNotActive()
    {
        $container = new Container();
        $container->addScope(new Scope('foo'));

        try {
            $container->leaveScope('foo');
            $this->fail('->leaveScope() throws a \LogicException if the scope is not active yet');
        } catch (\Exception $e) {
            $this->assertInstanceOf('\LogicException', $e, '->leaveScope() throws a \LogicException if the scope is not active yet');
            $this->assertEquals('The scope "foo" is not active.', $e->getMessage(), '->leaveScope() throws a \LogicException if the scope is not active yet');
        }

        try {
            $container->leaveScope('bar');
            $this->fail('->leaveScope() throws a \LogicException if the scope does not exist');
        } catch (\Exception $e) {
            $this->assertInstanceOf('\LogicException', $e, '->leaveScope() throws a \LogicException if the scope does not exist');
            $this->assertEquals('The scope "bar" is not active.', $e->getMessage(), '->leaveScope() throws a \LogicException if the scope does not exist');
        }
    }

    /**
     * @expectedException \InvalidArgumentException
     * @dataProvider getBuiltInScopes
     */
    public function testAddScopeDoesNotAllowBuiltInScopes($scope)
    {
        $container = new Container();
        $container->addScope(new Scope($scope));
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testAddScopeDoesNotAllowExistingScope()
    {
        $container = new Container();
        $container->addScope(new Scope('foo'));
        $container->addScope(new Scope('foo'));
    }

    /**
     * @expectedException \InvalidArgumentException
     * @dataProvider getInvalidParentScopes
     */
    public function testAddScopeDoesNotAllowInvalidParentScope($scope)
    {
        $c = new Container();
        $c->addScope(new Scope('foo', $scope));
    }

    public function testAddScope()
    {
        $c = new Container();
        $c->addScope(new Scope('foo'));
        $c->addScope(new Scope('bar', 'foo'));

        $this->assertSame(array('foo' => 'container', 'bar' => 'foo'), $this->getField($c, 'scopes'));
        $this->assertSame(array('foo' => array('bar'), 'bar' => array()), $this->getField($c, 'scopeChildren'));

        $c->addScope(new Scope('baz', 'bar'));

        $this->assertSame(array('foo' => 'container', 'bar' => 'foo', 'baz' => 'bar'), $this->getField($c, 'scopes'));
        $this->assertSame(array('foo' => array('bar', 'baz'), 'bar' => array('baz'), 'baz' => array()), $this->getField($c, 'scopeChildren'));
    }

    public function testHasScope()
    {
        $c = new Container();

        $this->assertFalse($c->hasScope('foo'));
        $c->addScope(new Scope('foo'));
        $this->assertTrue($c->hasScope('foo'));
    }

    /**
     * @expectedException Exception
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

    public function testIsScopeActive()
    {
        $c = new Container();

        $this->assertFalse($c->isScopeActive('foo'));
        $c->addScope(new Scope('foo'));

        $this->assertFalse($c->isScopeActive('foo'));
        $c->enterScope('foo');

        $this->assertTrue($c->isScopeActive('foo'));
        $c->leaveScope('foo');

        $this->assertFalse($c->isScopeActive('foo'));
    }

    public function getInvalidParentScopes()
    {
        return array(
            array(ContainerInterface::SCOPE_PROTOTYPE),
            array('bar'),
        );
    }

    public function getBuiltInScopes()
    {
        return array(
            array(ContainerInterface::SCOPE_CONTAINER),
            array(ContainerInterface::SCOPE_PROTOTYPE),
        );
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
}

class ProjectServiceContainer extends Container
{
    public $__bar, $__foo_bar, $__foo_baz;
    public $synchronized;

    public function __construct()
    {
        parent::__construct();

        $this->__bar = new \stdClass();
        $this->__foo_bar = new \stdClass();
        $this->__foo_baz = new \stdClass();
        $this->synchronized = false;
        $this->aliases = array('alias' => 'bar');
    }

    protected function getScopedService()
    {
        if (!$this->isScopeActive('foo')) {
            throw new \RuntimeException('Invalid call');
        }

        return $this->services['scoped'] = $this->scopedServices['foo']['scoped'] = new \stdClass();
    }

    protected function getScopedFooService()
    {
        if (!$this->isScopeActive('foo')) {
            throw new \RuntimeException('invalid call');
        }

        return $this->services['scoped_foo'] = $this->scopedServices['foo']['scoped_foo'] = new \stdClass();
    }

    protected function getScopedSynchronizedFooService()
    {
        if (!$this->isScopeActive('foo')) {
            throw new \RuntimeException('invalid call');
        }

        return $this->services['scoped_bar'] = $this->scopedServices['foo']['scoped_bar'] = new \stdClass();
    }

    protected function synchronizeScopedSynchronizedFooService()
    {
        $this->synchronized = true;
    }

    protected function getInactiveService()
    {
        throw new InactiveScopeException('request', 'request');
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
