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
use Symfony\Components\DependencyInjection\BuilderConfiguration;
use Symfony\Components\DependencyInjection\Definition;
use Symfony\Components\DependencyInjection\Reference;
use Symfony\Components\DependencyInjection\FileResource;

class BuilderConfigurationTest extends \PHPUnit_Framework_TestCase
{
    static protected $fixturesPath;

    static public function setUpBeforeClass()
    {
        self::$fixturesPath = __DIR__.'/../../../../fixtures/Symfony/Components/DependencyInjection/';
    }

    public function testConstructor()
    {
        $definitions = array(
            'foo' => new Definition('FooClass'),
            'bar' => new Definition('BarClass'),
        );
        $parameters = array(
            'foo' => 'foo',
            'bar' => 'bar',
        );
        $configuration = new BuilderConfiguration($definitions, $parameters);
        $this->assertEquals($definitions, $configuration->getDefinitions(), '__construct() takes an array of definitions as its first argument');
        $this->assertEquals($parameters, $configuration->getParameters(), '__construct() takes an array of parameters as its second argument');
    }

    public function testMerge()
    {
        $configuration = new BuilderConfiguration();
        $configuration->merge(null);
        $this->assertEquals(array(), $configuration->getParameters(), '->merge() accepts null as an argument');
        $this->assertEquals(array(), $configuration->getDefinitions(), '->merge() accepts null as an argument');

        $configuration = new BuilderConfiguration(array(), array('bar' => 'foo'));
        $configuration1 = new BuilderConfiguration(array(), array('foo' => 'bar'));
        $configuration->merge($configuration1);
        $this->assertEquals(array('bar' => 'foo', 'foo' => 'bar'), $configuration->getParameters(), '->merge() merges current parameters with the loaded ones');

        $configuration = new BuilderConfiguration(array(), array('bar' => 'foo', 'foo' => 'baz'));
        $config = new BuilderConfiguration(array(), array('foo' => 'bar'));
        $configuration->merge($config);
        $this->assertEquals(array('bar' => 'foo', 'foo' => 'bar'), $configuration->getParameters(), '->merge() overrides existing parameters');

        $configuration = new BuilderConfiguration(array('foo' => new Definition('FooClass'), 'bar' => new Definition('BarClass')));
        $config = new BuilderConfiguration(array('baz' => new Definition('BazClass')));
        $config->setAlias('alias_for_foo', 'foo');
        $configuration->merge($config);
        $this->assertEquals(array('foo', 'bar', 'baz'), array_keys($configuration->getDefinitions()), '->merge() merges definitions already defined ones');
        $this->assertEquals(array('alias_for_foo' => 'foo'), $configuration->getAliases(), '->merge() registers defined aliases');

        $configuration = new BuilderConfiguration(array('foo' => new Definition('FooClass')));
        $config->setDefinition('foo', new Definition('BazClass'));
        $configuration->merge($config);
        $this->assertEquals('BazClass', $configuration->getDefinition('foo')->getClass(), '->merge() overrides already defined services');

        $configuration = new BuilderConfiguration();
        $configuration->addResource($a = new FileResource('foo.xml'));
        $config = new BuilderConfiguration();
        $config->addResource($b = new FileResource('foo.yml'));
        $configuration->merge($config);
        $this->assertEquals(array($a, $b), $configuration->getResources(), '->merge() merges resources');
    }

    public function testSetGetParameters()
    {
        $configuration = new BuilderConfiguration();
        $this->assertEquals(array(), $configuration->getParameters(), '->getParameters() returns an empty array if no parameter has been defined');

        $configuration->setParameters(array('foo' => 'bar'));
        $this->assertEquals(array('foo' => 'bar'), $configuration->getParameters(), '->setParameters() sets the parameters');

        $configuration->setParameters(array('bar' => 'foo'));
        $this->assertEquals(array('bar' => 'foo'), $configuration->getParameters(), '->setParameters() overrides the previous defined parameters');

        $configuration->setParameters(array('Bar' => 'foo'));
        $this->assertEquals(array('bar' => 'foo'), $configuration->getParameters(), '->setParameters() converts the key to lowercase');
    }

    public function testSetGetParameter()
    {
        $configuration = new BuilderConfiguration(array(), array('foo' => 'bar'));
        $configuration->setParameter('bar', 'foo');
        $this->assertEquals('foo', $configuration->getParameter('bar'), '->setParameter() sets the value of a new parameter');

        $configuration->setParameter('foo', 'baz');
        $this->assertEquals('baz', $configuration->getParameter('foo'), '->setParameter() overrides previously set parameter');

        $configuration->setParameter('Foo', 'baz1');
        $this->assertEquals('baz1', $configuration->getParameter('foo'), '->setParameter() converts the key to lowercase');
        $this->assertEquals('baz1', $configuration->getParameter('FOO'), '->getParameter() converts the key to lowercase');

        try
        {
            $configuration->getParameter('baba');
            $this->fail('->getParameter() throws an \InvalidArgumentException if the key does not exist');
        }
        catch (\Exception $e)
        {
            $this->assertInstanceOf('\InvalidArgumentException', $e, '->getParameter() throws an \InvalidArgumentException if the key does not exist');
            $this->assertEquals('The parameter "baba" must be defined.', $e->getMessage(), '->getParameter() throws an \InvalidArgumentException if the key does not exist');
        }
    }

    public function testHasParameter()
    {
        $configuration = new BuilderConfiguration(array(), array('foo' => 'bar'));
        $this->assertTrue($configuration->hasParameter('foo'), '->hasParameter() returns true if a parameter is defined');
        $this->assertTrue($configuration->hasParameter('Foo'), '->hasParameter() converts the key to lowercase');
        $this->assertFalse($configuration->hasParameter('bar'), '->hasParameter() returns false if a parameter is not defined');
    }

    public function testAddParameters()
    {
        $configuration = new BuilderConfiguration(array(), array('foo' => 'bar'));
        $configuration->addParameters(array('bar' => 'foo'));
        $this->assertEquals(array('foo' => 'bar', 'bar' => 'foo'), $configuration->getParameters(), '->addParameters() adds parameters to the existing ones');
        $configuration->addParameters(array('Bar' => 'fooz'));
        $this->assertEquals(array('foo' => 'bar', 'bar' => 'fooz'), $configuration->getParameters(), '->addParameters() converts keys to lowercase');
    }

    public function testAliases()
    {
        $configuration = new BuilderConfiguration();
        $configuration->setAlias('bar', 'foo');
        $this->assertEquals('foo', $configuration->getAlias('bar'), '->setAlias() defines a new alias');
        $this->assertTrue($configuration->hasAlias('bar'), '->hasAlias() returns true if the alias is defined');
        $this->assertFalse($configuration->hasAlias('baba'), '->hasAlias() returns false if the alias is not defined');

        try
        {
            $configuration->getAlias('baba');
            $this->fail('->getAlias() throws an \InvalidArgumentException if the alias does not exist');
        }
        catch (\Exception $e)
        {
            $this->assertInstanceOf('\InvalidArgumentException', $e, '->getAlias() throws an \InvalidArgumentException if the alias does not exist');
            $this->assertEquals('The service alias "baba" does not exist.', $e->getMessage(), '->getAlias() throws an \InvalidArgumentException if the alias does not exist');
        }

        $configuration->setAlias('barbar', 'foofoo');
        $this->assertEquals(array('bar' => 'foo', 'barbar' => 'foofoo'), $configuration->getAliases(), '->getAliases() returns an array of all defined aliases');

        $configuration->addAliases(array('foo' => 'bar'));
        $this->assertEquals(array('bar' => 'foo', 'barbar' => 'foofoo', 'foo' => 'bar'), $configuration->getAliases(), '->addAliases() adds some aliases');
    }

    public function testDefinitions()
    {
        $configuration = new BuilderConfiguration();
        $definitions = array(
            'foo' => new Definition('FooClass'),
            'bar' => new Definition('BarClass'),
        );
        $configuration->setDefinitions($definitions);
        $this->assertEquals($definitions, $configuration->getDefinitions(), '->setDefinitions() sets the service definitions');
        $this->assertTrue($configuration->hasDefinition('foo'), '->hasDefinition() returns true if a service definition exists');
        $this->assertFalse($configuration->hasDefinition('foobar'), '->hasDefinition() returns false if a service definition does not exist');

        $configuration->setDefinition('foobar', $foo = new Definition('FooBarClass'));
        $this->assertEquals($foo, $configuration->getDefinition('foobar'), '->getDefinition() returns a service definition if defined');
        $this->assertTrue($configuration->setDefinition('foobar', new Definition('FooBarClass')) === $configuration, '->setDefinition() implements a fuild interface');

        $configuration->addDefinitions($defs = array('foobar' => new Definition('FooBarClass')));
        $this->assertEquals(array_merge($definitions, $defs), $configuration->getDefinitions(), '->addDefinitions() adds the service definitions');

        try
        {
            $configuration->getDefinition('baz');
            $this->fail('->getDefinition() throws an InvalidArgumentException if the service definition does not exist');
        }
        catch (\Exception $e)
        {
            $this->assertInstanceOf('\InvalidArgumentException', $e, '->getDefinition() throws an InvalidArgumentException if the service definition does not exist');
            $this->assertEquals('The service definition "baz" does not exist.', $e->getMessage(), '->getDefinition() throws an InvalidArgumentException if the service definition does not exist');
        }
    }

    public function testFindDefinition()
    {
        $configuration = new BuilderConfiguration(array('foo' => $definition = new Definition('FooClass')));
        $configuration->setAlias('bar', 'foo');
        $configuration->setAlias('foobar', 'bar');
        $this->assertEquals($definition, $configuration->findDefinition('foobar'), '->findDefinition() returns a Definition');
    }

    public function testResources()
    {
        $configuration = new BuilderConfiguration();
        $configuration->addResource($a = new FileResource('foo.xml'));
        $configuration->addResource($b = new FileResource('foo.yml'));
        $this->assertEquals(array($a, $b), $configuration->getResources(), '->getResources() returns an array of resources read for the current configuration');
    }
}
