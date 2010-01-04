<?php

/*
 * This file is part of the symfony package.
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 * 
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

require_once __DIR__.'/../../../bootstrap.php';

use Symfony\Components\DependencyInjection\Builder;
use Symfony\Components\DependencyInjection\BuilderConfiguration;
use Symfony\Components\DependencyInjection\Definition;
use Symfony\Components\DependencyInjection\Reference;

$fixturesPath = __DIR__.'/../../../../fixtures/Symfony/Components/DependencyInjection/';

$t = new LimeTest(55);

// ->setDefinitions() ->addDefinitions() ->getDefinitions() ->setDefinition() ->getDefinition() ->hasDefinition()
$t->diag('->setDefinitions() ->addDefinitions() ->getDefinitions() ->setDefinition() ->getDefinition() ->hasDefinition()');
$builder = new Builder();
$definitions = array(
  'foo' => new Definition('FooClass'),
  'bar' => new Definition('BarClass'),
);
$builder->setDefinitions($definitions);
$t->is($builder->getDefinitions(), $definitions, '->setDefinitions() sets the service definitions');
$t->ok($builder->hasDefinition('foo'), '->hasDefinition() returns true if a service definition exists');
$t->ok(!$builder->hasDefinition('foobar'), '->hasDefinition() returns false if a service definition does not exist');

$builder->setDefinition('foobar', $foo = new Definition('FooBarClass'));
$t->is($builder->getDefinition('foobar'), $foo, '->getDefinition() returns a service definition if defined');
$t->ok($builder->setDefinition('foobar', $foo = new Definition('FooBarClass')) === $foo, '->setDefinition() implements a fuild interface by returning the service reference');

$builder->addDefinitions($defs = array('foobar' => new Definition('FooBarClass')));
$t->is($builder->getDefinitions(), array_merge($definitions, $defs), '->addDefinitions() adds the service definitions');

try
{
  $builder->getDefinition('baz');
  $t->fail('->getDefinition() throws an InvalidArgumentException if the service definition does not exist');
}
catch (InvalidArgumentException $e)
{
  $t->pass('->getDefinition() throws an InvalidArgumentException if the service definition does not exist');
}

// ->register()
$t->diag('->register()');
$builder = new Builder();
$builder->register('foo', 'FooClass');
$t->ok($builder->hasDefinition('foo'), '->register() registers a new service definition');
$t->ok($builder->getDefinition('foo') instanceof Definition, '->register() returns the newly created Definition instance');

// ->hasService()
$t->diag('->hasService()');
$builder = new Builder();
$t->ok(!$builder->hasService('foo'), '->hasService() returns false if the service does not exist');
$builder->register('foo', 'FooClass');
$t->ok($builder->hasService('foo'), '->hasService() returns true if a service definition exists');
$builder->bar = new stdClass();
$t->ok($builder->hasService('bar'), '->hasService() returns true if a service exists');

// ->getService()
$t->diag('->getService()');
$builder = new Builder();
try
{
  $builder->getService('foo');
  $t->fail('->getService() throws an InvalidArgumentException if the service does not exist');
}
catch (InvalidArgumentException $e)
{
  $t->pass('->getService() throws an InvalidArgumentException if the service does not exist');
}
$builder->register('foo', 'stdClass');
$t->ok(is_object($builder->getService('foo')), '->getService() returns the service definition associated with the id');
$builder->bar = $bar = new stdClass();
$t->is($builder->getService('bar'), $bar, '->getService() returns the service associated with the id');
$builder->register('bar', 'stdClass');
$t->is($builder->getService('bar'), $bar, '->getService() returns the service associated with the id even if a definition has been defined');

$builder->register('baz', 'stdClass')->setArguments(array(new Reference('baz')));
try
{
  @$builder->getService('baz');
  $t->fail('->getService() throws a LogicException if the service has a circular reference to itself');
}
catch (LogicException $e)
{
  $t->pass('->getService() throws a LogicException if the service has a circular reference to itself');
}

$builder->register('foobar', 'stdClass')->setShared(true);
$t->ok($builder->getService('bar') === $builder->getService('bar'), '->getService() always returns the same instance if the service is shared');

// ->getServiceIds()
$t->diag('->getServiceIds()');
$builder = new Builder();
$builder->register('foo', 'stdClass');
$builder->bar = $bar = new stdClass();
$builder->register('bar', 'stdClass');
$t->is($builder->getServiceIds(), array('foo', 'bar', 'service_container'), '->getServiceIds() returns all defined service ids');

// ->setAlias()
$t->diag('->setAlias()');
$builder = new Builder();
$builder->register('foo', 'stdClass');
$builder->setAlias('bar', 'foo');
$t->ok($builder->hasService('bar'), '->setAlias() defines a new service');
$t->ok($builder->getService('bar') === $builder->getService('foo'), '->setAlias() creates a service that is an alias to another one');

// ->getAliases()
$t->diag('->getAliases()');
$builder = new Builder();
$builder->setAlias('bar', 'foo');
$builder->setAlias('foobar', 'foo');
$t->is($builder->getAliases(), array('bar' => 'foo', 'foobar' => 'foo'), '->getAliases() returns all service aliases');
$builder->register('bar', 'stdClass');
$t->is($builder->getAliases(), array('foobar' => 'foo'), '->getAliases() does not return aliased services that have been overridden');
$builder->setService('foobar', 'stdClass');
$t->is($builder->getAliases(), array(), '->getAliases() does not return aliased services that have been overridden');

// ->createService() # file
$t->diag('->createService() # file');
$builder = new Builder();
$builder->register('foo1', 'FooClass')->setFile($fixturesPath.'/includes/foo.php');
$t->ok($builder->getService('foo1'), '->createService() requires the file defined by the service definition');
$builder->register('foo2', 'FooClass')->setFile($fixturesPath.'/includes/%file%.php');
$builder->setParameter('file', 'foo');
$t->ok($builder->getService('foo2'), '->createService() replaces parameters in the file provided by the service definition');

// ->createService() # class
$t->diag('->createService() # class');
$builder = new Builder();
$builder->register('foo1', '%class%');
$builder->setParameter('class', 'stdClass');
$t->ok($builder->getService('foo1') instanceof stdClass, '->createService() replaces parameters in the class provided by the service definition');

// ->createService() # arguments
$t->diag('->createService() # arguments');
$builder = new Builder();
$builder->register('bar', 'stdClass');
$builder->register('foo1', 'FooClass')->addArgument(array('foo' => '%value%', '%value%' => 'foo', new Reference('bar')));
$builder->setParameter('value', 'bar');
$t->is($builder->getService('foo1')->arguments, array('foo' => 'bar', 'bar' => 'foo', $builder->getService('bar')), '->createService() replaces parameters and service references in the arguments provided by the service definition');

// ->createService() # constructor
$t->diag('->createService() # constructor');
$builder = new Builder();
$builder->register('bar', 'stdClass');
$builder->register('foo1', 'FooClass')->setConstructor('getInstance')->addArgument(array('foo' => '%value%', '%value%' => 'foo', new Reference('bar')));
$builder->setParameter('value', 'bar');
$t->ok($builder->getService('foo1')->called, '->createService() calls the constructor to create the service instance');
$t->is($builder->getService('foo1')->arguments, array('foo' => 'bar', 'bar' => 'foo', $builder->getService('bar')), '->createService() passes the arguments to the constructor');

// ->createService() # method calls
$t->diag('->createService() # method calls');
$builder = new Builder();
$builder->register('bar', 'stdClass');
$builder->register('foo1', 'FooClass')->addMethodCall('setBar', array(array('%value%', new Reference('bar'))));
$builder->setParameter('value', 'bar');
$t->is($builder->getService('foo1')->bar, array('bar', $builder->getService('bar')), '->createService() replaces the values in the method calls arguments');

// ->createService() # configurator
require_once $fixturesPath.'/includes/classes.php';
$t->diag('->createService() # configurator');
$builder = new Builder();
$builder->register('foo1', 'FooClass')->setConfigurator('sc_configure');
$t->ok($builder->getService('foo1')->configured, '->createService() calls the configurator');

$builder->register('foo2', 'FooClass')->setConfigurator(array('%class%', 'configureStatic'));
$builder->setParameter('class', 'BazClass');
$t->ok($builder->getService('foo2')->configured, '->createService() calls the configurator');

$builder->register('baz', 'BazClass');
$builder->register('foo3', 'FooClass')->setConfigurator(array(new Reference('baz'), 'configure'));
$t->ok($builder->getService('foo3')->configured, '->createService() calls the configurator');

$builder->register('foo4', 'FooClass')->setConfigurator('foo');
try
{
  $builder->getService('foo4');
  $t->fail('->createService() throws an InvalidArgumentException if the configure callable is not a valid callable');
}
catch (InvalidArgumentException $e)
{
  $t->pass('->createService() throws an InvalidArgumentException if the configure callable is not a valid callable');
}

// ::resolveValue()
$t->diag('::resolveValue()');
$t->is(Builder::resolveValue('foo', array()), 'foo', '->resolveValue() returns its argument unmodified if no placeholders are found');
$t->is(Builder::resolveValue('I\'m a %foo%', array('foo' => 'bar')), 'I\'m a bar', '->resolveValue() replaces placeholders by their values');
$t->ok(Builder::resolveValue('%foo%', array('foo' => true)) === true, '->resolveValue() replaces arguments that are just a placeholder by their value without casting them to strings');

$t->is(Builder::resolveValue(array('%foo%' => '%foo%'), array('foo' => 'bar')), array('bar' => 'bar'), '->resolveValue() replaces placeholders in keys and values of arrays');

$t->is(Builder::resolveValue(array('%foo%' => array('%foo%' => array('%foo%' => '%foo%'))), array('foo' => 'bar')), array('bar' => array('bar' => array('bar' => 'bar'))), '->resolveValue() replaces placeholders in nested arrays');

$t->is(Builder::resolveValue('I\'m a %%foo%%', array('foo' => 'bar')), 'I\'m a %foo%', '->resolveValue() supports % escaping by doubling it');
$t->is(Builder::resolveValue('I\'m a %foo% %%foo %foo%', array('foo' => 'bar')), 'I\'m a bar %foo bar', '->resolveValue() supports % escaping by doubling it');

try
{
  Builder::resolveValue('%foobar%', array());
  $t->fail('->resolveValue() throws a RuntimeException if a placeholder references a non-existant parameter');
}
catch (RuntimeException $e)
{
  $t->pass('->resolveValue() throws a RuntimeException if a placeholder references a non-existant parameter');
}

try
{
  Builder::resolveValue('foo %foobar% bar', array());
  $t->fail('->resolveValue() throws a RuntimeException if a placeholder references a non-existant parameter');
}
catch (RuntimeException $e)
{
  $t->pass('->resolveValue() throws a RuntimeException if a placeholder references a non-existant parameter');
}

// ->resolveServices()
$t->diag('->resolveServices()');
$builder = new Builder();
$builder->register('foo', 'FooClass');
$t->is($builder->resolveServices(new Reference('foo')), $builder->getService('foo'), '->resolveServices() resolves service references to service instances');
$t->is($builder->resolveServices(array('foo' => array('foo', new Reference('foo')))), array('foo' => array('foo', $builder->getService('foo'))), '->resolveServices() resolves service references to service instances in nested arrays');

// ->merge()
$t->diag('->merge()');
$container = new Builder();
$container->merge(null);
$t->is($container->getParameters(), array(), '->merge() accepts null as an argument');
$t->is($container->getDefinitions(), array(), '->merge() accepts null as an argument');

$container = new Builder(array('bar' => 'foo'));
$config = new BuilderConfiguration();
$config->setParameters(array('foo' => 'bar'));
$container->merge($config);
$t->is($container->getParameters(), array('bar' => 'foo', 'foo' => 'bar'), '->merge() merges current parameters with the loaded ones');

$container = new Builder(array('bar' => 'foo', 'foo' => 'baz'));
$config = new BuilderConfiguration();
$config->setParameters(array('foo' => 'bar'));
$container->merge($config);
$t->is($container->getParameters(), array('bar' => 'foo', 'foo' => 'baz'), '->merge() does not change the already defined parameters');

$container = new Builder(array('bar' => 'foo'));
$config = new BuilderConfiguration();
$config->setParameters(array('foo' => '%bar%'));
$container->merge($config);
$t->is($container->getParameters(), array('bar' => 'foo', 'foo' => 'foo'), '->merge() evaluates the values of the parameters towards already defined ones');

$container = new Builder(array('bar' => 'foo'));
$config = new BuilderConfiguration();
$config->setParameters(array('foo' => '%bar%', 'baz' => '%foo%'));
$container->merge($config);
$t->is($container->getParameters(), array('bar' => 'foo', 'foo' => 'foo', 'baz' => 'foo'), '->merge() evaluates the values of the parameters towards already defined ones');

$container = new Builder();
$container->register('foo', 'FooClass');
$container->register('bar', 'BarClass');
$config = new BuilderConfiguration();
$config->setDefinition('baz', new Definition('BazClass'));
$config->setAlias('alias_for_foo', 'foo');
$container->merge($config);
$t->is(array_keys($container->getDefinitions()), array('foo', 'bar', 'baz'), '->load() merges definitions already defined ones');
$t->is($container->getAliases(), array('alias_for_foo' => 'foo'), '->merge() registers defined aliases');

$container = new Builder();
$container->register('foo', 'FooClass');
$config->setDefinition('foo', new Definition('BazClass'));
$container->merge($config);
$t->is($container->getDefinition('foo')->getClass(), 'BazClass', '->merge() overrides already defined services');
