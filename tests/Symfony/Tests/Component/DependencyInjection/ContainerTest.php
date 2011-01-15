<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Tests\Component\DependencyInjection;

use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;

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
     * @covers Symfony\Component\DependencyInjection\Container::compile
     */
    public function testcompile()
    {
        $sc = new Container(new ParameterBag(array('foo' => 'bar')));
        $sc->compile();
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
            $this->assertEquals('The parameter "baba" must be defined.', $e->getMessage(), '->getParameter() thrown an \InvalidArgumentException if the key does not exist');
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
        $this->assertEquals(array('bar', 'foo_bar', 'foo.baz', 'service_container'), $sc->getServiceIds(), '->getServiceIds() returns defined service ids by getXXXService() methods');
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
     * @covers Symfony\Component\DependencyInjection\Container::get
     */
    public function testGet()
    {
        $sc = new ProjectServiceContainer();
        $sc->set('foo', $foo = new \stdClass());
        $this->assertEquals($foo, $sc->get('foo'), '->get() returns the service for the given id');
        $this->assertEquals($sc->__bar, $sc->get('bar'), '->get() returns the service for the given id');
        $this->assertEquals($sc->__foo_bar, $sc->get('foo_bar'), '->get() returns the service if a get*Method() is defined');
        $this->assertEquals($sc->__foo_baz, $sc->get('foo.baz'), '->get() returns the service if a get*Method() is defined');

        $sc->set('bar', $bar = new \stdClass());
        $this->assertSame($sc->get('bar'), $bar, '->getServiceIds() prefers to return a service defined with a getXXXService() method than one defined with set()');

        try {
            $sc->get('');
            $this->fail('->get() throws a \InvalidArgumentException exception if the service is empty');
        } catch (\Exception $e) {
            $this->assertInstanceOf('\InvalidArgumentException', $e, '->get() throws a \InvalidArgumentException exception if the service is empty');
            $this->assertEquals('The service "" does not exist.', $e->getMessage(), '->get() throws a \InvalidArgumentException exception if the service is empty');
        }
        $this->assertNull($sc->get('', ContainerInterface::NULL_ON_INVALID_REFERENCE));
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
    }
}

class ProjectServiceContainer extends Container
{
    public $__bar, $__foo_bar, $__foo_baz;

    public function __construct()
    {
        parent::__construct();

        $this->__bar = new \stdClass();
        $this->__foo_bar = new \stdClass();
        $this->__foo_baz = new \stdClass();
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
}
