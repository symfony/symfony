<?php

/*
 * This file is part of the Symfony package.
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Tests\Components\DependencyInjection;

use Symfony\Components\DependencyInjection\Builder;
use Symfony\Components\DependencyInjection\ContainerInterface;
use Symfony\Components\DependencyInjection\BuilderConfiguration;
use Symfony\Components\DependencyInjection\Definition;
use Symfony\Components\DependencyInjection\Reference;
use Symfony\Components\DependencyInjection\ParameterBag\ParameterBag;

class BuilderTest extends \PHPUnit_Framework_TestCase
{
    static protected $fixturesPath;

    static public function setUpBeforeClass()
    {
        self::$fixturesPath = __DIR__.'/Fixtures/';
    }

    /**
     * @covers Symfony\Components\DependencyInjection\Builder::setDefinitions
     * @covers Symfony\Components\DependencyInjection\Builder::getDefinitions
     * @covers Symfony\Components\DependencyInjection\Builder::setDefinition
     * @covers Symfony\Components\DependencyInjection\Builder::getDefinition
     */
    public function testDefinitions()
    {
        $builder = new Builder();
        $definitions = array(
            'foo' => new Definition('FooClass'),
            'bar' => new Definition('BarClass'),
        );
        $builder->setDefinitions($definitions);
        $this->assertEquals($definitions, $builder->getDefinitions(), '->setDefinitions() sets the service definitions');
        $this->assertTrue($builder->hasDefinition('foo'), '->hasDefinition() returns true if a service definition exists');
        $this->assertFalse($builder->hasDefinition('foobar'), '->hasDefinition() returns false if a service definition does not exist');

        $builder->setDefinition('foobar', $foo = new Definition('FooBarClass'));
        $this->assertEquals($foo, $builder->getDefinition('foobar'), '->getDefinition() returns a service definition if defined');
        $this->assertTrue($builder->setDefinition('foobar', $foo = new Definition('FooBarClass')) === $foo, '->setDefinition() implements a fuild interface by returning the service reference');

        $builder->addDefinitions($defs = array('foobar' => new Definition('FooBarClass')));
        $this->assertEquals(array_merge($definitions, $defs), $builder->getDefinitions(), '->addDefinitions() adds the service definitions');

        try {
            $builder->getDefinition('baz');
            $this->fail('->getDefinition() throws an InvalidArgumentException if the service definition does not exist');
        } catch (\Exception $e) {
            $this->assertInstanceOf('\InvalidArgumentException', $e, '->getDefinition() throws an InvalidArgumentException if the service definition does not exist');
            $this->assertEquals('The service definition "baz" does not exist.', $e->getMessage(), '->getDefinition() throws an InvalidArgumentException if the service definition does not exist');
        }
    }

    /**
     * @covers Symfony\Components\DependencyInjection\Builder::register
     */
    public function testRegister()
    {
        $builder = new Builder();
        $builder->register('foo', 'FooClass');
        $this->assertTrue($builder->hasDefinition('foo'), '->register() registers a new service definition');
        $this->assertInstanceOf('Symfony\Components\DependencyInjection\Definition', $builder->getDefinition('foo'), '->register() returns the newly created Definition instance');
    }

    /**
     * @covers Symfony\Components\DependencyInjection\Builder::has
     */
    public function testHas()
    {
        $builder = new Builder();
        $this->assertFalse($builder->has('foo'), '->has() returns false if the service does not exist');
        $builder->register('foo', 'FooClass');
        $this->assertTrue($builder->has('foo'), '->has() returns true if a service definition exists');
        $builder->set('bar', new \stdClass());
        $this->assertTrue($builder->has('bar'), '->has() returns true if a service exists');
    }

    /**
     * @covers Symfony\Components\DependencyInjection\Builder::get
     */
    public function testGet()
    {
        $builder = new Builder();
        try {
            $builder->get('foo');
            $this->fail('->get() throws an InvalidArgumentException if the service does not exist');
        } catch (\Exception $e) {
            $this->assertInstanceOf('\InvalidArgumentException', $e, '->get() throws an InvalidArgumentException if the service does not exist');
            $this->assertEquals('The service definition "foo" does not exist.', $e->getMessage(), '->get() throws an InvalidArgumentException if the service does not exist');
        }

        $this->assertNull($builder->get('foo', ContainerInterface::NULL_ON_INVALID_REFERENCE), '->get() returns null if the service does not exist and NULL_ON_INVALID_REFERENCE is passed as a second argument');

        $builder->register('foo', 'stdClass');
        $this->assertType('object', $builder->get('foo'), '->get() returns the service definition associated with the id');
        $builder->set('bar', $bar = new \stdClass());
        $this->assertEquals($bar, $builder->get('bar'), '->get() returns the service associated with the id');
        $builder->register('bar', 'stdClass');
        $this->assertEquals($bar, $builder->get('bar'), '->get() returns the service associated with the id even if a definition has been defined');

        $builder->register('baz', 'stdClass')->setArguments(array(new Reference('baz')));
        try {
            @$builder->get('baz');
            $this->fail('->get() throws a LogicException if the service has a circular reference to itself');
        } catch (\Exception $e) {
            $this->assertInstanceOf('\LogicException', $e, '->get() throws a LogicException if the service has a circular reference to itself');
            $this->assertEquals('The service "baz" has a circular reference to itself.', $e->getMessage(), '->get() throws a LogicException if the service has a circular reference to itself');
        }

        $builder->register('foobar', 'stdClass')->setShared(true);
        $this->assertTrue($builder->get('bar') === $builder->get('bar'), '->get() always returns the same instance if the service is shared');
    }

    /**
     * @covers Symfony\Components\DependencyInjection\Builder::getServiceIds
     */
    public function testGetServiceIds()
    {
        $builder = new Builder();
        $builder->register('foo', 'stdClass');
        $builder->bar = $bar = new \stdClass();
        $builder->register('bar', 'stdClass');
        $this->assertEquals(array('foo', 'bar', 'service_container'), $builder->getServiceIds(), '->getServiceIds() returns all defined service ids');
    }

    /**
     * @covers Symfony\Components\DependencyInjection\Builder::setAlias
     * @covers Symfony\Components\DependencyInjection\Builder::hasAlias
     * @covers Symfony\Components\DependencyInjection\Builder::getAlias
     */
    public function testAliases()
    {
        $builder = new Builder();
        $builder->register('foo', 'stdClass');
        $builder->setAlias('bar', 'foo');
        $this->assertTrue($builder->hasAlias('bar'), '->hasAlias() returns true if the alias exists');
        $this->assertFalse($builder->hasAlias('foobar'), '->hasAlias() returns false if the alias does not exist');
        $this->assertEquals('foo', $builder->getAlias('bar'), '->getAlias() returns the aliased service');
        $this->assertTrue($builder->has('bar'), '->setAlias() defines a new service');
        $this->assertTrue($builder->get('bar') === $builder->get('foo'), '->setAlias() creates a service that is an alias to another one');

        try {
            $builder->getAlias('foobar');
            $this->fail('->getAlias() throws an InvalidArgumentException if the alias does not exist');
        } catch (\Exception $e) {
            $this->assertInstanceOf('\InvalidArgumentException', $e, '->getAlias() throws an InvalidArgumentException if the alias does not exist');
            $this->assertEquals('The service alias "foobar" does not exist.', $e->getMessage(), '->getAlias() throws an InvalidArgumentException if the alias does not exist');
        }
    }

    /**
     * @covers Symfony\Components\DependencyInjection\Builder::getAliases
     */
    public function testGetAliases()
    {
        $builder = new Builder();
        $builder->setAlias('bar', 'foo');
        $builder->setAlias('foobar', 'foo');
        $this->assertEquals(array('bar' => 'foo', 'foobar' => 'foo'), $builder->getAliases(), '->getAliases() returns all service aliases');
        $builder->register('bar', 'stdClass');
        $this->assertEquals(array('foobar' => 'foo'), $builder->getAliases(), '->getAliases() does not return aliased services that have been overridden');
        $builder->set('foobar', 'stdClass');
        $this->assertEquals(array(), $builder->getAliases(), '->getAliases() does not return aliased services that have been overridden');
    }

    /**
     * @covers Symfony\Components\DependencyInjection\Builder::setAliases
     */
    public function testSetAliases()
    {
        $builder = new Builder();
        $builder->setAliases(array('bar' => 'foo', 'foobar' => 'foo'));
        $this->assertEquals(array('bar' => 'foo', 'foobar' => 'foo'), $builder->getAliases(), '->getAliases() returns all service aliases');
    }

    /**
     * @covers Symfony\Components\DependencyInjection\Builder::addAliases
     */
    public function testAddAliases()
    {
        $builder = new Builder();
        $builder->setAliases(array('bar' => 'foo'));
        $builder->addAliases(array('foobar' => 'foo'));
        $this->assertEquals(array('bar' => 'foo', 'foobar' => 'foo'), $builder->getAliases(), '->getAliases() returns all service aliases');
    }

    /**
     * @covers Symfony\Components\DependencyInjection\Builder::createService
     */
    public function testCreateService()
    {
        $builder = new Builder();
        $builder->register('foo1', 'FooClass')->setFile(self::$fixturesPath.'/includes/foo.php');
        $this->assertInstanceOf('\FooClass', $builder->get('foo1'), '->createService() requires the file defined by the service definition');
        $builder->register('foo2', 'FooClass')->setFile(self::$fixturesPath.'/includes/%file%.php');
        $builder->setParameter('file', 'foo');
        $this->assertInstanceOf('\FooClass', $builder->get('foo2'), '->createService() replaces parameters in the file provided by the service definition');
    }

    /**
     * @covers Symfony\Components\DependencyInjection\Builder::createService
     */
    public function testCreateServiceClass()
    {
        $builder = new Builder();
        $builder->register('foo1', '%class%');
        $builder->setParameter('class', 'stdClass');
        $this->assertInstanceOf('\stdClass', $builder->get('foo1'), '->createService() replaces parameters in the class provided by the service definition');
    }

    /**
     * @covers Symfony\Components\DependencyInjection\Builder::createService
     */
    public function testCreateServiceArguments()
    {
        $builder = new Builder();
        $builder->register('bar', 'stdClass');
        $builder->register('foo1', 'FooClass')->addArgument(array('foo' => '%value%', '%value%' => 'foo', new Reference('bar')));
        $builder->setParameter('value', 'bar');
        $this->assertEquals(array('foo' => 'bar', 'bar' => 'foo', $builder->get('bar')), $builder->get('foo1')->arguments, '->createService() replaces parameters and service references in the arguments provided by the service definition');
    }

    /**
     * @covers Symfony\Components\DependencyInjection\Builder::createService
     */
    public function testCreateServiceConstructor()
    {
        $builder = new Builder();
        $builder->register('bar', 'stdClass');
        $builder->register('foo1', 'FooClass')->setConstructor('getInstance')->addArgument(array('foo' => '%value%', '%value%' => 'foo', new Reference('bar')));
        $builder->setParameter('value', 'bar');
        $this->assertTrue($builder->get('foo1')->called, '->createService() calls the constructor to create the service instance');
        $this->assertEquals(array('foo' => 'bar', 'bar' => 'foo', $builder->get('bar')), $builder->get('foo1')->arguments, '->createService() passes the arguments to the constructor');
    }

    /**
     * @covers Symfony\Components\DependencyInjection\Builder::createService
     */
    public function testCreateServiceMethodCalls()
    {
        $builder = new Builder();
        $builder->register('bar', 'stdClass');
        $builder->register('foo1', 'FooClass')->addMethodCall('setBar', array(array('%value%', new Reference('bar'))));
        $builder->setParameter('value', 'bar');
        $this->assertEquals(array('bar', $builder->get('bar')), $builder->get('foo1')->bar, '->createService() replaces the values in the method calls arguments');
    }

    /**
     * @covers Symfony\Components\DependencyInjection\Builder::createService
     */
    public function testCreateServiceConfigurator()
    {
        require_once self::$fixturesPath.'/includes/classes.php';

        $builder = new Builder();
        $builder->register('foo1', 'FooClass')->setConfigurator('sc_configure');
        $this->assertTrue($builder->get('foo1')->configured, '->createService() calls the configurator');

        $builder->register('foo2', 'FooClass')->setConfigurator(array('%class%', 'configureStatic'));
        $builder->setParameter('class', 'BazClass');
        $this->assertTrue($builder->get('foo2')->configured, '->createService() calls the configurator');

        $builder->register('baz', 'BazClass');
        $builder->register('foo3', 'FooClass')->setConfigurator(array(new Reference('baz'), 'configure'));
        $this->assertTrue($builder->get('foo3')->configured, '->createService() calls the configurator');

        $builder->register('foo4', 'FooClass')->setConfigurator('foo');
        try {
            $builder->get('foo4');
            $this->fail('->createService() throws an InvalidArgumentException if the configure callable is not a valid callable');
        } catch (\Exception $e) {
            $this->assertInstanceOf('\InvalidArgumentException', $e, '->createService() throws an InvalidArgumentException if the configure callable is not a valid callable');
            $this->assertEquals('The configure callable for class "FooClass" is not a callable.', $e->getMessage(), '->createService() throws an InvalidArgumentException if the configure callable is not a valid callable');
        }
    }

    /**
     * @covers Symfony\Components\DependencyInjection\Builder::resolveValue
     */
    public function testResolveValue()
    {
        $this->assertEquals('foo', Builder::resolveValue('foo', array()), '->resolveValue() returns its argument unmodified if no placeholders are found');
        $this->assertEquals('I\'m a bar', Builder::resolveValue('I\'m a %foo%', array('foo' => 'bar')), '->resolveValue() replaces placeholders by their values');
        $this->assertTrue(Builder::resolveValue('%foo%', array('foo' => true)) === true, '->resolveValue() replaces arguments that are just a placeholder by their value without casting them to strings');

        $this->assertEquals(array('bar' => 'bar'), Builder::resolveValue(array('%foo%' => '%foo%'), array('foo' => 'bar')), '->resolveValue() replaces placeholders in keys and values of arrays');

        $this->assertEquals(array('bar' => array('bar' => array('bar' => 'bar'))), Builder::resolveValue(array('%foo%' => array('%foo%' => array('%foo%' => '%foo%'))), array('foo' => 'bar')), '->resolveValue() replaces placeholders in nested arrays');

        $this->assertEquals('I\'m a %foo%', Builder::resolveValue('I\'m a %%foo%%', array('foo' => 'bar')), '->resolveValue() supports % escaping by doubling it');
        $this->assertEquals('I\'m a bar %foo bar', Builder::resolveValue('I\'m a %foo% %%foo %foo%', array('foo' => 'bar')), '->resolveValue() supports % escaping by doubling it');

        try {
            Builder::resolveValue('%foobar%', array());
            $this->fail('->resolveValue() throws a RuntimeException if a placeholder references a non-existant parameter');
        } catch (\Exception $e) {
            $this->assertInstanceOf('\RuntimeException', $e, '->resolveValue() throws a RuntimeException if a placeholder references a non-existant parameter');
            $this->assertEquals('The parameter "foobar" must be defined.', $e->getMessage(), '->resolveValue() throws a RuntimeException if a placeholder references a non-existant parameter');
        }

        try {
            Builder::resolveValue('foo %foobar% bar', array());
            $this->fail('->resolveValue() throws a RuntimeException if a placeholder references a non-existant parameter');
        } catch (\Exception $e) {
            $this->assertInstanceOf('\RuntimeException', $e, '->resolveValue() throws a RuntimeException if a placeholder references a non-existant parameter');
            $this->assertEquals('The parameter "foobar" must be defined (used in the following expression: "foo %foobar% bar").', $e->getMessage(), '->resolveValue() throws a RuntimeException if a placeholder references a non-existant parameter');
        }
    }

    /**
     * @covers Symfony\Components\DependencyInjection\Builder::resolveServices
     */
    public function testResolveServices()
    {
        $builder = new Builder();
        $builder->register('foo', 'FooClass');
        $this->assertEquals($builder->get('foo'), $builder->resolveServices(new Reference('foo')), '->resolveServices() resolves service references to service instances');
        $this->assertEquals(array('foo' => array('foo', $builder->get('foo'))), $builder->resolveServices(array('foo' => array('foo', new Reference('foo')))), '->resolveServices() resolves service references to service instances in nested arrays');
    }

    /**
     * @covers Symfony\Components\DependencyInjection\Builder::merge
     */
    public function testMerge()
    {
        $container = new Builder();
        $container->merge(null);
        $this->assertEquals(array(), $container->getParameterBag()->all(), '->merge() accepts null as an argument');
        $this->assertEquals(array(), $container->getDefinitions(), '->merge() accepts null as an argument');

        $container = new Builder(new ParameterBag(array('bar' => 'foo')));
        $config = new BuilderConfiguration(array(), new ParameterBag(array('foo' => 'bar')));
        $container->merge($config);
        $this->assertEquals(array('bar' => 'foo', 'foo' => 'bar'), $container->getParameterBag()->all(), '->merge() merges current parameters with the loaded ones');

        $container = new Builder(new ParameterBag(array('bar' => 'foo', 'foo' => 'baz')));
        $config = new BuilderConfiguration(array(), new ParameterBag(array('foo' => 'bar')));
        $container->merge($config);
        $this->assertEquals(array('bar' => 'foo', 'foo' => 'baz'), $container->getParameterBag()->all(), '->merge() does not change the already defined parameters');

        $container = new Builder(new ParameterBag(array('bar' => 'foo')));
        $config = new BuilderConfiguration(array(), new ParameterBag(array('foo' => '%bar%')));
        $container->merge($config);
        $this->assertEquals(array('bar' => 'foo', 'foo' => 'foo'), $container->getParameterBag()->all(), '->merge() evaluates the values of the parameters towards already defined ones');

        $container = new Builder(new ParameterBag(array('bar' => 'foo')));
        $config = new BuilderConfiguration(array(), new ParameterBag(array('foo' => '%bar%', 'baz' => '%foo%')));
        $container->merge($config);
        $this->assertEquals(array('bar' => 'foo', 'foo' => 'foo', 'baz' => 'foo'), $container->getParameterBag()->all(), '->merge() evaluates the values of the parameters towards already defined ones');

        $container = new Builder();
        $container->register('foo', 'FooClass');
        $container->register('bar', 'BarClass');
        $config = new BuilderConfiguration();
        $config->setDefinition('baz', new Definition('BazClass'));
        $config->setAlias('alias_for_foo', 'foo');
        $container->merge($config);
        $this->assertEquals(array('foo', 'bar', 'baz'), array_keys($container->getDefinitions()), '->merge() merges definitions already defined ones');
        $this->assertEquals(array('alias_for_foo' => 'foo'), $container->getAliases(), '->merge() registers defined aliases');

        $container = new Builder();
        $container->register('foo', 'FooClass');
        $config->setDefinition('foo', new Definition('BazClass'));
        $container->merge($config);
        $this->assertEquals('BazClass', $container->getDefinition('foo')->getClass(), '->merge() overrides already defined services');
    }

    /**
     * @covers Symfony\Components\DependencyInjection\Builder::findAnnotatedServiceIds
     */
    public function testFindAnnotatedServiceIds()
    {
        $builder = new Builder();
        $builder
            ->register('foo', 'FooClass')
            ->addAnnotation('foo', array('foo' => 'foo'))
            ->addAnnotation('bar', array('bar' => 'bar'))
            ->addAnnotation('foo', array('foofoo' => 'foofoo'))
        ;
        $this->assertEquals($builder->findAnnotatedServiceIds('foo'), array(
            'foo' => array(
                array('foo' => 'foo'),
                array('foofoo' => 'foofoo'),
            )
        ), '->findAnnotatedServiceIds() returns an array of service ids and its annotation attributes');
        $this->assertEquals(array(), $builder->findAnnotatedServiceIds('foobar'), '->findAnnotatedServiceIds() returns an empty array if there is annotated services');
    }
}
