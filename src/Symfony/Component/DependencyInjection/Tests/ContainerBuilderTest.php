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

require_once __DIR__.'/Fixtures/includes/classes.php';
require_once __DIR__.'/Fixtures/includes/ProjectExtension.php';

use Symfony\Component\Config\Resource\ResourceInterface;
use Symfony\Component\DependencyInjection\Alias;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Exception\RuntimeException;
use Symfony\Component\DependencyInjection\Exception\InactiveScopeException;
use Symfony\Component\DependencyInjection\Loader\ClosureLoader;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;
use Symfony\Component\DependencyInjection\Scope;
use Symfony\Component\Config\Resource\FileResource;
use Symfony\Component\ExpressionLanguage\Expression;

class ContainerBuilderTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @covers Symfony\Component\DependencyInjection\ContainerBuilder::setDefinitions
     * @covers Symfony\Component\DependencyInjection\ContainerBuilder::getDefinitions
     * @covers Symfony\Component\DependencyInjection\ContainerBuilder::setDefinition
     * @covers Symfony\Component\DependencyInjection\ContainerBuilder::getDefinition
     */
    public function testDefinitions()
    {
        $builder = new ContainerBuilder();
        $definitions = array(
            'foo' => new Definition('Bar\FooClass'),
            'bar' => new Definition('BarClass'),
        );
        $builder->setDefinitions($definitions);
        $this->assertEquals($definitions, $builder->getDefinitions(), '->setDefinitions() sets the service definitions');
        $this->assertTrue($builder->hasDefinition('foo'), '->hasDefinition() returns true if a service definition exists');
        $this->assertFalse($builder->hasDefinition('foobar'), '->hasDefinition() returns false if a service definition does not exist');

        $builder->setDefinition('foobar', $foo = new Definition('FooBarClass'));
        $this->assertEquals($foo, $builder->getDefinition('foobar'), '->getDefinition() returns a service definition if defined');
        $this->assertTrue($builder->setDefinition('foobar', $foo = new Definition('FooBarClass')) === $foo, '->setDefinition() implements a fluid interface by returning the service reference');

        $builder->addDefinitions($defs = array('foobar' => new Definition('FooBarClass')));
        $this->assertEquals(array_merge($definitions, $defs), $builder->getDefinitions(), '->addDefinitions() adds the service definitions');

        try {
            $builder->getDefinition('baz');
            $this->fail('->getDefinition() throws an InvalidArgumentException if the service definition does not exist');
        } catch (\InvalidArgumentException $e) {
            $this->assertEquals('The service definition "baz" does not exist.', $e->getMessage(), '->getDefinition() throws an InvalidArgumentException if the service definition does not exist');
        }
    }

    /**
     * @covers Symfony\Component\DependencyInjection\ContainerBuilder::register
     */
    public function testRegister()
    {
        $builder = new ContainerBuilder();
        $builder->register('foo', 'Bar\FooClass');
        $this->assertTrue($builder->hasDefinition('foo'), '->register() registers a new service definition');
        $this->assertInstanceOf('Symfony\Component\DependencyInjection\Definition', $builder->getDefinition('foo'), '->register() returns the newly created Definition instance');
    }

    /**
     * @covers Symfony\Component\DependencyInjection\ContainerBuilder::has
     */
    public function testHas()
    {
        $builder = new ContainerBuilder();
        $this->assertFalse($builder->has('foo'), '->has() returns false if the service does not exist');
        $builder->register('foo', 'Bar\FooClass');
        $this->assertTrue($builder->has('foo'), '->has() returns true if a service definition exists');
        $builder->set('bar', new \stdClass());
        $this->assertTrue($builder->has('bar'), '->has() returns true if a service exists');
    }

    /**
     * @covers Symfony\Component\DependencyInjection\ContainerBuilder::get
     */
    public function testGet()
    {
        $builder = new ContainerBuilder();
        try {
            $builder->get('foo');
            $this->fail('->get() throws an InvalidArgumentException if the service does not exist');
        } catch (\InvalidArgumentException $e) {
            $this->assertEquals('The service definition "foo" does not exist.', $e->getMessage(), '->get() throws an InvalidArgumentException if the service does not exist');
        }

        $this->assertNull($builder->get('foo', ContainerInterface::NULL_ON_INVALID_REFERENCE), '->get() returns null if the service does not exist and NULL_ON_INVALID_REFERENCE is passed as a second argument');

        $builder->register('foo', 'stdClass');
        $this->assertInternalType('object', $builder->get('foo'), '->get() returns the service definition associated with the id');
        $builder->set('bar', $bar = new \stdClass());
        $this->assertEquals($bar, $builder->get('bar'), '->get() returns the service associated with the id');
        $builder->register('bar', 'stdClass');
        $this->assertEquals($bar, $builder->get('bar'), '->get() returns the service associated with the id even if a definition has been defined');

        $builder->register('baz', 'stdClass')->setArguments(array(new Reference('baz')));
        try {
            @$builder->get('baz');
            $this->fail('->get() throws a ServiceCircularReferenceException if the service has a circular reference to itself');
        } catch (\Symfony\Component\DependencyInjection\Exception\ServiceCircularReferenceException $e) {
            $this->assertEquals('Circular reference detected for service "baz", path: "baz".', $e->getMessage(), '->get() throws a LogicException if the service has a circular reference to itself');
        }

        $builder->register('foobar', 'stdClass')->setScope('container');
        $this->assertTrue($builder->get('bar') === $builder->get('bar'), '->get() always returns the same instance if the service is shared');
    }

    /**
     * @covers                   \Symfony\Component\DependencyInjection\ContainerBuilder::get
     * @expectedException        \Symfony\Component\DependencyInjection\Exception\RuntimeException
     * @expectedExceptionMessage You have requested a synthetic service ("foo"). The DIC does not know how to construct this service.
     */
    public function testGetUnsetLoadingServiceWhenCreateServiceThrowsAnException()
    {
        $builder = new ContainerBuilder();
        $builder->register('foo', 'stdClass')->setSynthetic(true);

        // we expect a RuntimeException here as foo is synthetic
        try {
            $builder->get('foo');
        } catch (RuntimeException $e) {
        }

        // we must also have the same RuntimeException here
        $builder->get('foo');
    }

    /**
     * @covers Symfony\Component\DependencyInjection\ContainerBuilder::get
     */
    public function testGetReturnsNullOnInactiveScope()
    {
        $builder = new ContainerBuilder();
        $builder->register('foo', 'stdClass')->setScope('request');

        $this->assertNull($builder->get('foo', ContainerInterface::NULL_ON_INVALID_REFERENCE));
    }

    /**
     * @covers Symfony\Component\DependencyInjection\ContainerBuilder::get
     */
    public function testGetReturnsNullOnInactiveScopeWhenServiceIsCreatedByAMethod()
    {
        $builder = new ProjectContainer();

        $this->assertNull($builder->get('foobaz', ContainerInterface::NULL_ON_INVALID_REFERENCE));
    }

    /**
     * @covers Symfony\Component\DependencyInjection\ContainerBuilder::getServiceIds
     */
    public function testGetServiceIds()
    {
        $builder = new ContainerBuilder();
        $builder->register('foo', 'stdClass');
        $builder->bar = $bar = new \stdClass();
        $builder->register('bar', 'stdClass');
        $this->assertEquals(array('foo', 'bar', 'service_container'), $builder->getServiceIds(), '->getServiceIds() returns all defined service ids');
    }

    /**
     * @covers Symfony\Component\DependencyInjection\ContainerBuilder::setAlias
     * @covers Symfony\Component\DependencyInjection\ContainerBuilder::hasAlias
     * @covers Symfony\Component\DependencyInjection\ContainerBuilder::getAlias
     */
    public function testAliases()
    {
        $builder = new ContainerBuilder();
        $builder->register('foo', 'stdClass');
        $builder->setAlias('bar', 'foo');
        $this->assertTrue($builder->hasAlias('bar'), '->hasAlias() returns true if the alias exists');
        $this->assertFalse($builder->hasAlias('foobar'), '->hasAlias() returns false if the alias does not exist');
        $this->assertEquals('foo', (string) $builder->getAlias('bar'), '->getAlias() returns the aliased service');
        $this->assertTrue($builder->has('bar'), '->setAlias() defines a new service');
        $this->assertTrue($builder->get('bar') === $builder->get('foo'), '->setAlias() creates a service that is an alias to another one');

        try {
            $builder->getAlias('foobar');
            $this->fail('->getAlias() throws an InvalidArgumentException if the alias does not exist');
        } catch (\InvalidArgumentException $e) {
            $this->assertEquals('The service alias "foobar" does not exist.', $e->getMessage(), '->getAlias() throws an InvalidArgumentException if the alias does not exist');
        }
    }

    /**
     * @covers Symfony\Component\DependencyInjection\ContainerBuilder::getAliases
     */
    public function testGetAliases()
    {
        $builder = new ContainerBuilder();
        $builder->setAlias('bar', 'foo');
        $builder->setAlias('foobar', 'foo');
        $builder->setAlias('moo', new Alias('foo', false));

        $aliases = $builder->getAliases();
        $this->assertEquals('foo', (string) $aliases['bar']);
        $this->assertTrue($aliases['bar']->isPublic());
        $this->assertEquals('foo', (string) $aliases['foobar']);
        $this->assertEquals('foo', (string) $aliases['moo']);
        $this->assertFalse($aliases['moo']->isPublic());

        $builder->register('bar', 'stdClass');
        $this->assertFalse($builder->hasAlias('bar'));

        $builder->set('foobar', 'stdClass');
        $builder->set('moo', 'stdClass');
        $this->assertCount(0, $builder->getAliases(), '->getAliases() does not return aliased services that have been overridden');
    }

    /**
     * @covers Symfony\Component\DependencyInjection\ContainerBuilder::setAliases
     */
    public function testSetAliases()
    {
        $builder = new ContainerBuilder();
        $builder->setAliases(array('bar' => 'foo', 'foobar' => 'foo'));

        $aliases = $builder->getAliases();
        $this->assertTrue(isset($aliases['bar']));
        $this->assertTrue(isset($aliases['foobar']));
    }

    /**
     * @covers Symfony\Component\DependencyInjection\ContainerBuilder::addAliases
     */
    public function testAddAliases()
    {
        $builder = new ContainerBuilder();
        $builder->setAliases(array('bar' => 'foo'));
        $builder->addAliases(array('foobar' => 'foo'));

        $aliases = $builder->getAliases();
        $this->assertTrue(isset($aliases['bar']));
        $this->assertTrue(isset($aliases['foobar']));
    }

    /**
     * @covers Symfony\Component\DependencyInjection\ContainerBuilder::addCompilerPass
     * @covers Symfony\Component\DependencyInjection\ContainerBuilder::getCompilerPassConfig
     */
    public function testAddGetCompilerPass()
    {
        $builder = new ContainerBuilder();
        $builder->setResourceTracking(false);
        $builderCompilerPasses = $builder->getCompiler()->getPassConfig()->getPasses();
        $builder->addCompilerPass($this->getMock('Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface'));
        $this->assertEquals(sizeof($builderCompilerPasses) + 1, sizeof($builder->getCompiler()->getPassConfig()->getPasses()));
    }

    /**
     * @covers Symfony\Component\DependencyInjection\ContainerBuilder::createService
     */
    public function testCreateService()
    {
        $builder = new ContainerBuilder();
        $builder->register('foo1', 'Bar\FooClass')->setFile(__DIR__.'/Fixtures/includes/foo.php');
        $this->assertInstanceOf('\Bar\FooClass', $builder->get('foo1'), '->createService() requires the file defined by the service definition');
        $builder->register('foo2', 'Bar\FooClass')->setFile(__DIR__.'/Fixtures/includes/%file%.php');
        $builder->setParameter('file', 'foo');
        $this->assertInstanceOf('\Bar\FooClass', $builder->get('foo2'), '->createService() replaces parameters in the file provided by the service definition');
    }

    /**
     * @covers Symfony\Component\DependencyInjection\ContainerBuilder::createService
     */
    public function testCreateProxyWithRealServiceInstantiator()
    {
        $builder = new ContainerBuilder();

        $builder->register('foo1', 'Bar\FooClass')->setFile(__DIR__.'/Fixtures/includes/foo.php');
        $builder->getDefinition('foo1')->setLazy(true);

        $foo1 = $builder->get('foo1');

        $this->assertSame($foo1, $builder->get('foo1'), 'The same proxy is retrieved on multiple subsequent calls');
        $this->assertSame('Bar\FooClass', get_class($foo1));
    }

    /**
     * @covers Symfony\Component\DependencyInjection\ContainerBuilder::createService
     */
    public function testCreateServiceClass()
    {
        $builder = new ContainerBuilder();
        $builder->register('foo1', '%class%');
        $builder->setParameter('class', 'stdClass');
        $this->assertInstanceOf('\stdClass', $builder->get('foo1'), '->createService() replaces parameters in the class provided by the service definition');
    }

    /**
     * @covers Symfony\Component\DependencyInjection\ContainerBuilder::createService
     */
    public function testCreateServiceArguments()
    {
        $builder = new ContainerBuilder();
        $builder->register('bar', 'stdClass');
        $builder->register('foo1', 'Bar\FooClass')->addArgument(array('foo' => '%value%', '%value%' => 'foo', new Reference('bar'), '%%unescape_it%%'));
        $builder->setParameter('value', 'bar');
        $this->assertEquals(array('foo' => 'bar', 'bar' => 'foo', $builder->get('bar'), '%unescape_it%'), $builder->get('foo1')->arguments, '->createService() replaces parameters and service references in the arguments provided by the service definition');
    }

    /**
     * @covers Symfony\Component\DependencyInjection\ContainerBuilder::createService
     */
    public function testCreateServiceFactoryMethod()
    {
        $builder = new ContainerBuilder();
        $builder->register('bar', 'stdClass');
        $builder->register('foo1', 'Bar\FooClass')->setFactoryClass('Bar\FooClass')->setFactoryMethod('getInstance')->addArgument(array('foo' => '%value%', '%value%' => 'foo', new Reference('bar')));
        $builder->setParameter('value', 'bar');
        $this->assertTrue($builder->get('foo1')->called, '->createService() calls the factory method to create the service instance');
        $this->assertEquals(array('foo' => 'bar', 'bar' => 'foo', $builder->get('bar')), $builder->get('foo1')->arguments, '->createService() passes the arguments to the factory method');
    }

    /**
     * @covers Symfony\Component\DependencyInjection\ContainerBuilder::createService
     */
    public function testCreateServiceFactoryService()
    {
        $builder = new ContainerBuilder();
        $builder->register('baz_service')->setFactoryService('baz_factory')->setFactoryMethod('getInstance');
        $builder->register('baz_factory', 'BazClass');

        $this->assertInstanceOf('BazClass', $builder->get('baz_service'));
    }

    /**
     * @covers Symfony\Component\DependencyInjection\ContainerBuilder::createService
     */
    public function testCreateServiceMethodCalls()
    {
        $builder = new ContainerBuilder();
        $builder->register('bar', 'stdClass');
        $builder->register('foo1', 'Bar\FooClass')->addMethodCall('setBar', array(array('%value%', new Reference('bar'))));
        $builder->setParameter('value', 'bar');
        $this->assertEquals(array('bar', $builder->get('bar')), $builder->get('foo1')->bar, '->createService() replaces the values in the method calls arguments');
    }

    /**
     * @covers Symfony\Component\DependencyInjection\ContainerBuilder::createService
     */
    public function testCreateServiceConfigurator()
    {
        $builder = new ContainerBuilder();
        $builder->register('foo1', 'Bar\FooClass')->setConfigurator('sc_configure');
        $this->assertTrue($builder->get('foo1')->configured, '->createService() calls the configurator');

        $builder->register('foo2', 'Bar\FooClass')->setConfigurator(array('%class%', 'configureStatic'));
        $builder->setParameter('class', 'BazClass');
        $this->assertTrue($builder->get('foo2')->configured, '->createService() calls the configurator');

        $builder->register('baz', 'BazClass');
        $builder->register('foo3', 'Bar\FooClass')->setConfigurator(array(new Reference('baz'), 'configure'));
        $this->assertTrue($builder->get('foo3')->configured, '->createService() calls the configurator');

        $builder->register('foo4', 'Bar\FooClass')->setConfigurator('foo');
        try {
            $builder->get('foo4');
            $this->fail('->createService() throws an InvalidArgumentException if the configure callable is not a valid callable');
        } catch (\InvalidArgumentException $e) {
            $this->assertEquals('The configure callable for class "Bar\FooClass" is not a callable.', $e->getMessage(), '->createService() throws an InvalidArgumentException if the configure callable is not a valid callable');
        }
    }

    /**
     * @covers Symfony\Component\DependencyInjection\ContainerBuilder::createService
     * @expectedException \RuntimeException
     */
    public function testCreateSyntheticService()
    {
        $builder = new ContainerBuilder();
        $builder->register('foo', 'Bar\FooClass')->setSynthetic(true);
        $builder->get('foo');
    }

    public function testCreateServiceWithExpression()
    {
        $builder = new ContainerBuilder();
        $builder->setParameter('bar', 'bar');
        $builder->register('bar', 'BarClass');
        $builder->register('foo', 'Bar\FooClass')->addArgument(array('foo' => new Expression('service("bar").foo ~ parameter("bar")')));
        $this->assertEquals('foobar', $builder->get('foo')->arguments['foo']);
    }

    /**
     * @covers Symfony\Component\DependencyInjection\ContainerBuilder::resolveServices
     */
    public function testResolveServices()
    {
        $builder = new ContainerBuilder();
        $builder->register('foo', 'Bar\FooClass');
        $this->assertEquals($builder->get('foo'), $builder->resolveServices(new Reference('foo')), '->resolveServices() resolves service references to service instances');
        $this->assertEquals(array('foo' => array('foo', $builder->get('foo'))), $builder->resolveServices(array('foo' => array('foo', new Reference('foo')))), '->resolveServices() resolves service references to service instances in nested arrays');
        $this->assertEquals($builder->get('foo'), $builder->resolveServices(new Expression('service("foo")')), '->resolveServices() resolves expressions');
    }

    /**
     * @covers Symfony\Component\DependencyInjection\ContainerBuilder::merge
     */
    public function testMerge()
    {
        $container = new ContainerBuilder(new ParameterBag(array('bar' => 'foo')));
        $container->setResourceTracking(false);
        $config = new ContainerBuilder(new ParameterBag(array('foo' => 'bar')));
        $container->merge($config);
        $this->assertEquals(array('bar' => 'foo', 'foo' => 'bar'), $container->getParameterBag()->all(), '->merge() merges current parameters with the loaded ones');

        $container = new ContainerBuilder(new ParameterBag(array('bar' => 'foo')));
        $container->setResourceTracking(false);
        $config = new ContainerBuilder(new ParameterBag(array('foo' => '%bar%')));
        $container->merge($config);
        $container->compile();
        $this->assertEquals(array('bar' => 'foo', 'foo' => 'foo'), $container->getParameterBag()->all(), '->merge() evaluates the values of the parameters towards already defined ones');

        $container = new ContainerBuilder(new ParameterBag(array('bar' => 'foo')));
        $container->setResourceTracking(false);
        $config = new ContainerBuilder(new ParameterBag(array('foo' => '%bar%', 'baz' => '%foo%')));
        $container->merge($config);
        $container->compile();
        $this->assertEquals(array('bar' => 'foo', 'foo' => 'foo', 'baz' => 'foo'), $container->getParameterBag()->all(), '->merge() evaluates the values of the parameters towards already defined ones');

        $container = new ContainerBuilder();
        $container->setResourceTracking(false);
        $container->register('foo', 'Bar\FooClass');
        $container->register('bar', 'BarClass');
        $config = new ContainerBuilder();
        $config->setDefinition('baz', new Definition('BazClass'));
        $config->setAlias('alias_for_foo', 'foo');
        $container->merge($config);
        $this->assertEquals(array('foo', 'bar', 'baz'), array_keys($container->getDefinitions()), '->merge() merges definitions already defined ones');

        $aliases = $container->getAliases();
        $this->assertTrue(isset($aliases['alias_for_foo']));
        $this->assertEquals('foo', (string) $aliases['alias_for_foo']);

        $container = new ContainerBuilder();
        $container->setResourceTracking(false);
        $container->register('foo', 'Bar\FooClass');
        $config->setDefinition('foo', new Definition('BazClass'));
        $container->merge($config);
        $this->assertEquals('BazClass', $container->getDefinition('foo')->getClass(), '->merge() overrides already defined services');
    }

    /**
     * @covers Symfony\Component\DependencyInjection\ContainerBuilder::merge
     * @expectedException \LogicException
     */
    public function testMergeLogicException()
    {
        $container = new ContainerBuilder();
        $container->setResourceTracking(false);
        $container->compile();
        $container->merge(new ContainerBuilder());
    }

    /**
     * @covers Symfony\Component\DependencyInjection\ContainerBuilder::findTaggedServiceIds
     */
    public function testfindTaggedServiceIds()
    {
        $builder = new ContainerBuilder();
        $builder
            ->register('foo', 'Bar\FooClass')
            ->addTag('foo', array('foo' => 'foo'))
            ->addTag('bar', array('bar' => 'bar'))
            ->addTag('foo', array('foofoo' => 'foofoo'))
        ;
        $this->assertEquals($builder->findTaggedServiceIds('foo'), array(
            'foo' => array(
                array('foo' => 'foo'),
                array('foofoo' => 'foofoo'),
            ),
        ), '->findTaggedServiceIds() returns an array of service ids and its tag attributes');
        $this->assertEquals(array(), $builder->findTaggedServiceIds('foobar'), '->findTaggedServiceIds() returns an empty array if there is annotated services');
    }

    /**
     * @covers Symfony\Component\DependencyInjection\ContainerBuilder::findDefinition
     */
    public function testFindDefinition()
    {
        $container = new ContainerBuilder();
        $container->setDefinition('foo', $definition = new Definition('Bar\FooClass'));
        $container->setAlias('bar', 'foo');
        $container->setAlias('foobar', 'bar');
        $this->assertEquals($definition, $container->findDefinition('foobar'), '->findDefinition() returns a Definition');
    }

    /**
     * @covers Symfony\Component\DependencyInjection\ContainerBuilder::addObjectResource
     */
    public function testAddObjectResource()
    {
        $container = new ContainerBuilder();

        $container->setResourceTracking(false);
        $container->addObjectResource(new \BarClass());

        $this->assertEmpty($container->getResources(), 'No resources get registered without resource tracking');

        $container->setResourceTracking(true);
        $container->addObjectResource(new \BarClass());

        $resources = $container->getResources();

        $this->assertCount(1, $resources, '1 resource was registered');

        /* @var $resource \Symfony\Component\Config\Resource\FileResource */
        $resource = end($resources);

        $this->assertInstanceOf('Symfony\Component\Config\Resource\FileResource', $resource);
        $this->assertSame(realpath(__DIR__.'/Fixtures/includes/classes.php'), realpath($resource->getResource()));
    }

    /**
     * @covers Symfony\Component\DependencyInjection\ContainerBuilder::addClassResource
     */
    public function testAddClassResource()
    {
        $container = new ContainerBuilder();

        $container->setResourceTracking(false);
        $container->addClassResource(new \ReflectionClass('BarClass'));

        $this->assertEmpty($container->getResources(), 'No resources get registered without resource tracking');

        $container->setResourceTracking(true);
        $container->addClassResource(new \ReflectionClass('BarClass'));

        $resources = $container->getResources();

        $this->assertCount(1, $resources, '1 resource was registered');

        /* @var $resource \Symfony\Component\Config\Resource\FileResource */
        $resource = end($resources);

        $this->assertInstanceOf('Symfony\Component\Config\Resource\FileResource', $resource);
        $this->assertSame(realpath(__DIR__.'/Fixtures/includes/classes.php'), realpath($resource->getResource()));
    }

    /**
     * @covers Symfony\Component\DependencyInjection\ContainerBuilder::compile
     */
    public function testCompilesClassDefinitionsOfLazyServices()
    {
        $container = new ContainerBuilder();

        $this->assertEmpty($container->getResources(), 'No resources get registered without resource tracking');

        $container->register('foo', 'BarClass');
        $container->getDefinition('foo')->setLazy(true);

        $container->compile();

        $classesPath       = realpath(__DIR__.'/Fixtures/includes/classes.php');
        $matchingResources = array_filter(
            $container->getResources(),
            function (ResourceInterface $resource) use ($classesPath) {
                return $resource instanceof FileResource && $classesPath === realpath($resource->getResource());
            }
        );

        $this->assertNotEmpty($matchingResources);
    }

    /**
     * @covers Symfony\Component\DependencyInjection\ContainerBuilder::getResources
     * @covers Symfony\Component\DependencyInjection\ContainerBuilder::addResource
     */
    public function testResources()
    {
        $container = new ContainerBuilder();
        $container->addResource($a = new FileResource(__DIR__.'/Fixtures/xml/services1.xml'));
        $container->addResource($b = new FileResource(__DIR__.'/Fixtures/xml/services2.xml'));
        $resources = array();
        foreach ($container->getResources() as $resource) {
            if (false === strpos($resource, '.php')) {
                $resources[] = $resource;
            }
        }
        $this->assertEquals(array($a, $b), $resources, '->getResources() returns an array of resources read for the current configuration');
        $this->assertSame($container, $container->setResources(array()));
        $this->assertEquals(array(), $container->getResources());
    }

    /**
     * @covers Symfony\Component\DependencyInjection\ContainerBuilder::registerExtension
     * @covers Symfony\Component\DependencyInjection\ContainerBuilder::getExtension
     */
    public function testExtension()
    {
        $container = new ContainerBuilder();
        $container->setResourceTracking(false);

        $container->registerExtension($extension = new \ProjectExtension());
        $this->assertTrue($container->getExtension('project') === $extension, '->registerExtension() registers an extension');

        $this->setExpectedException('LogicException');
        $container->getExtension('no_registered');
    }

    public function testRegisteredButNotLoadedExtension()
    {
        $extension = $this->getMock('Symfony\\Component\\DependencyInjection\\Extension\\ExtensionInterface');
        $extension->expects($this->once())->method('getAlias')->will($this->returnValue('project'));
        $extension->expects($this->never())->method('load');

        $container = new ContainerBuilder();
        $container->setResourceTracking(false);
        $container->registerExtension($extension);
        $container->compile();
    }

    public function testRegisteredAndLoadedExtension()
    {
        $extension = $this->getMock('Symfony\\Component\\DependencyInjection\\Extension\\ExtensionInterface');
        $extension->expects($this->exactly(2))->method('getAlias')->will($this->returnValue('project'));
        $extension->expects($this->once())->method('load')->with(array(array('foo' => 'bar')));

        $container = new ContainerBuilder();
        $container->setResourceTracking(false);
        $container->registerExtension($extension);
        $container->loadFromExtension('project', array('foo' => 'bar'));
        $container->compile();
    }

    public function testPrivateServiceUser()
    {
        $fooDefinition     = new Definition('BarClass');
        $fooUserDefinition = new Definition('BarUserClass', array(new Reference('bar')));
        $container         = new ContainerBuilder();
        $container->setResourceTracking(false);

        $fooDefinition->setPublic(false);

        $container->addDefinitions(array(
            'bar'       => $fooDefinition,
            'bar_user'  => $fooUserDefinition,
        ));

        $container->compile();
        $this->assertInstanceOf('BarClass', $container->get('bar_user')->bar);
    }

    /**
     * @expectedException \BadMethodCallException
     */
    public function testThrowsExceptionWhenSetServiceOnAFrozenContainer()
    {
        $container = new ContainerBuilder();
        $container->setResourceTracking(false);
        $container->setDefinition('a', new Definition('stdClass'));
        $container->compile();
        $container->set('a', new \stdClass());
    }

    /**
     * @expectedException \BadMethodCallException
     */
    public function testThrowsExceptionWhenAddServiceOnAFrozenContainer()
    {
        $container = new ContainerBuilder();
        $container->compile();
        $container->set('a', new \stdClass());
    }

    public function testNoExceptionWhenSetSyntheticServiceOnAFrozenContainer()
    {
        $container = new ContainerBuilder();
        $def = new Definition('stdClass');
        $def->setSynthetic(true);
        $container->setDefinition('a', $def);
        $container->compile();
        $container->set('a', $a = new \stdClass());
        $this->assertEquals($a, $container->get('a'));
    }

    public function testSetOnSynchronizedService()
    {
        $container = new ContainerBuilder();
        $container->register('baz', 'BazClass')
            ->setSynchronized(true)
        ;
        $container->register('bar', 'BarClass')
            ->addMethodCall('setBaz', array(new Reference('baz')))
        ;

        $container->set('baz', $baz = new \BazClass());
        $this->assertSame($baz, $container->get('bar')->getBaz());

        $container->set('baz', $baz = new \BazClass());
        $this->assertSame($baz, $container->get('bar')->getBaz());
    }

    public function testSynchronizedServiceWithScopes()
    {
        $container = new ContainerBuilder();
        $container->addScope(new Scope('foo'));
        $container->register('baz', 'BazClass')
            ->setSynthetic(true)
            ->setSynchronized(true)
            ->setScope('foo')
        ;
        $container->register('bar', 'BarClass')
            ->addMethodCall('setBaz', array(new Reference('baz', ContainerInterface::NULL_ON_INVALID_REFERENCE, false)))
        ;
        $container->compile();

        $container->enterScope('foo');
        $container->set('baz', $outerBaz = new \BazClass(), 'foo');
        $this->assertSame($outerBaz, $container->get('bar')->getBaz());

        $container->enterScope('foo');
        $container->set('baz', $innerBaz = new \BazClass(), 'foo');
        $this->assertSame($innerBaz, $container->get('bar')->getBaz());
        $container->leaveScope('foo');

        $this->assertNotSame($innerBaz, $container->get('bar')->getBaz());
        $this->assertSame($outerBaz, $container->get('bar')->getBaz());

        $container->leaveScope('foo');
    }

    /**
     * @expectedException \BadMethodCallException
     */
    public function testThrowsExceptionWhenSetDefinitionOnAFrozenContainer()
    {
        $container = new ContainerBuilder();
        $container->setResourceTracking(false);
        $container->compile();
        $container->setDefinition('a', new Definition());
    }

    /**
     * @covers Symfony\Component\DependencyInjection\ContainerBuilder::getExtensionConfig
     * @covers Symfony\Component\DependencyInjection\ContainerBuilder::prependExtensionConfig
     */
    public function testExtensionConfig()
    {
        $container = new ContainerBuilder();

        $configs = $container->getExtensionConfig('foo');
        $this->assertEmpty($configs);

        $first = array('foo' => 'bar');
        $container->prependExtensionConfig('foo', $first);
        $configs = $container->getExtensionConfig('foo');
        $this->assertEquals(array($first), $configs);

        $second = array('ding' => 'dong');
        $container->prependExtensionConfig('foo', $second);
        $configs = $container->getExtensionConfig('foo');
        $this->assertEquals(array($second, $first), $configs);
    }

    public function testLazyLoadedService()
    {
        $loader = new ClosureLoader($container = new ContainerBuilder());
        $loader->load(function (ContainerBuilder $container) {
                $container->set('a', new \BazClass());
                $definition = new Definition('BazClass');
                $definition->setLazy(true);
                $container->setDefinition('a', $definition);
            }
        );

        $container->setResourceTracking(true);

        $container->compile();

        $class = new \BazClass();
        $reflectionClass = new \ReflectionClass($class);

        $r = new \ReflectionProperty($container, 'resources');
        $r->setAccessible(true);
        $resources = $r->getValue($container);

        $classInList = false;
        foreach ($resources as $resource) {
            if ($resource->getResource() === $reflectionClass->getFileName()) {
                $classInList = true;
                break;
            }
        }

        $this->assertTrue($classInList);
    }
}

class FooClass
{
}

class ProjectContainer extends ContainerBuilder
{
    public function getFoobazService()
    {
        throw new InactiveScopeException('foo', 'request');
    }
}
