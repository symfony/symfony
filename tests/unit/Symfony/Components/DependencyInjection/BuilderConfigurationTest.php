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

$t = new LimeTest(1);

// __construct()
$t->diag('__construct()');
$definitions = array(
  'foo' => new Definition('FooClass'),
  'bar' => new Definition('BarClass'),
);
$parameters = array(
  'foo' => 'foo',
  'bar' => 'bar',
);
$configuration = new BuilderConfiguration($definitions, $parameters);
$t->is($configuration->getDefinitions(), $definitions, '__construct() takes an array of definitions as its first argument');
$t->is($configuration->getParameters(), $parameters, '__construct() takes an array of parameters as its second argument');

// ->merge()
$t->diag('->merge()');
$configuration = new BuilderConfiguration();
$configuration->merge(null);
$t->is($configuration->getParameters(), array(), '->merge() accepts null as an argument');
$t->is($configuration->getDefinitions(), array(), '->merge() accepts null as an argument');

$configuration = new BuilderConfiguration(array(), array('bar' => 'foo'));
$configuration1 = new BuilderConfiguration(array(), array('foo' => 'bar'));
$configuration->merge($configuration1);
$t->is($configuration->getParameters(), array('bar' => 'foo', 'foo' => 'bar'), '->merge() merges current parameters with the loaded ones');

$configuration = new BuilderConfiguration(array(), array('bar' => 'foo', 'foo' => 'baz'));
$config = new BuilderConfiguration(array(), array('foo' => 'bar'));
$configuration->merge($config);
$t->is($configuration->getParameters(), array('bar' => 'foo', 'foo' => 'bar'), '->merge() overrides existing parameters');

$configuration = new BuilderConfiguration(array('foo' => new Definition('FooClass'), 'bar' => new Definition('BarClass')));
$config = new BuilderConfiguration(array('baz' => new Definition('BazClass')));
$config->setAlias('alias_for_foo', 'foo');
$configuration->merge($config);
$t->is(array_keys($configuration->getDefinitions()), array('foo', 'bar', 'baz'), '->merge() merges definitions already defined ones');
$t->is($configuration->getAliases(), array('alias_for_foo' => 'foo'), '->merge() registers defined aliases');

$configuration = new BuilderConfiguration(array('foo' => new Definition('FooClass')));
$config->setDefinition('foo', new Definition('BazClass'));
$configuration->merge($config);
$t->is($configuration->getDefinition('foo')->getClass(), 'BazClass', '->merge() overrides already defined services');

// ->setParameters() ->getParameters()
$t->diag('->setParameters() ->getParameters()');

$configuration = new BuilderConfiguration();
$t->is($configuration->getParameters(), array(), '->getParameters() returns an empty array if no parameter has been defined');

$configuration->setParameters(array('foo' => 'bar'));
$t->is($configuration->getParameters(), array('foo' => 'bar'), '->setParameters() sets the parameters');

$configuration->setParameters(array('bar' => 'foo'));
$t->is($configuration->getParameters(), array('bar' => 'foo'), '->setParameters() overrides the previous defined parameters');

$configuration->setParameters(array('Bar' => 'foo'));
$t->is($configuration->getParameters(), array('bar' => 'foo'), '->setParameters() converts the key to lowercase');

// ->setParameter() ->getParameter()
$t->diag('->setParameter() ->getParameter() ');

$configuration = new BuilderConfiguration(array(), array('foo' => 'bar'));
$configuration->setParameter('bar', 'foo');
$t->is($configuration->getParameter('bar'), 'foo', '->setParameter() sets the value of a new parameter');

$configuration->setParameter('foo', 'baz');
$t->is($configuration->getParameter('foo'), 'baz', '->setParameter() overrides previously set parameter');

$configuration->setParameter('Foo', 'baz1');
$t->is($configuration->getParameter('foo'), 'baz1', '->setParameter() converts the key to lowercase');
$t->is($configuration->getParameter('FOO'), 'baz1', '->getParameter() converts the key to lowercase');

try
{
  $configuration->getParameter('baba');
  $t->fail('->getParameter() throws an \InvalidArgumentException if the key does not exist');
}
catch (\InvalidArgumentException $e)
{
  $t->pass('->getParameter() throws an \InvalidArgumentException if the key does not exist');
}

// ->hasParameter()
$t->diag('->hasParameter()');
$configuration = new BuilderConfiguration(array(), array('foo' => 'bar'));
$t->ok($configuration->hasParameter('foo'), '->hasParameter() returns true if a parameter is defined');
$t->ok($configuration->hasParameter('Foo'), '->hasParameter() converts the key to lowercase');
$t->ok(!$configuration->hasParameter('bar'), '->hasParameter() returns false if a parameter is not defined');

// ->addParameters()
$t->diag('->addParameters()');
$configuration = new BuilderConfiguration(array(), array('foo' => 'bar'));
$configuration->addParameters(array('bar' => 'foo'));
$t->is($configuration->getParameters(), array('foo' => 'bar', 'bar' => 'foo'), '->addParameters() adds parameters to the existing ones');
$configuration->addParameters(array('Bar' => 'fooz'));
$t->is($configuration->getParameters(), array('foo' => 'bar', 'bar' => 'fooz'), '->addParameters() converts keys to lowercase');

// ->setAlias() ->getAlias() ->hasAlias() ->getAliases() ->addAliases()
$t->diag('->setAlias() ->getAlias() ->hasAlias()');
$configuration = new BuilderConfiguration();
$configuration->setAlias('bar', 'foo');
$t->is($configuration->getAlias('bar'), 'foo', '->setAlias() defines a new alias');
$t->ok($configuration->hasAlias('bar'), '->hasAlias() returns true if the alias is defined');
$t->ok(!$configuration->hasAlias('baba'), '->hasAlias() returns false if the alias is not defined');

try
{
  $configuration->getAlias('baba');
  $t->fail('->getAlias() throws an \InvalidArgumentException if the alias does not exist');
}
catch (\InvalidArgumentException $e)
{
  $t->pass('->getAlias() throws an \InvalidArgumentException if the alias does not exist');
}

$configuration->setAlias('barbar', 'foofoo');
$t->is($configuration->getAliases(), array('bar' => 'foo', 'barbar' => 'foofoo'), '->getAliases() returns an array of all defined aliases');

$configuration->addAliases(array('foo' => 'bar'));
$t->is($configuration->getAliases(), array('bar' => 'foo', 'barbar' => 'foofoo', 'foo' => 'bar'), '->addAliases() adds some aliases');

// ->setDefinitions() ->addDefinitions() ->getDefinitions() ->setDefinition() ->getDefinition() ->hasDefinition()
$t->diag('->setDefinitions() ->addDefinitions() ->getDefinitions() ->setDefinition() ->getDefinition() ->hasDefinition()');
$configuration = new BuilderConfiguration();
$definitions = array(
  'foo' => new Definition('FooClass'),
  'bar' => new Definition('BarClass'),
);
$configuration->setDefinitions($definitions);
$t->is($configuration->getDefinitions(), $definitions, '->setDefinitions() sets the service definitions');
$t->ok($configuration->hasDefinition('foo'), '->hasDefinition() returns true if a service definition exists');
$t->ok(!$configuration->hasDefinition('foobar'), '->hasDefinition() returns false if a service definition does not exist');

$configuration->setDefinition('foobar', $foo = new Definition('FooBarClass'));
$t->is($configuration->getDefinition('foobar'), $foo, '->getDefinition() returns a service definition if defined');
$t->ok($configuration->setDefinition('foobar', $foo = new Definition('FooBarClass')) === $foo, '->setDefinition() implements a fuild interface by returning the service reference');

$configuration->addDefinitions($defs = array('foobar' => new Definition('FooBarClass')));
$t->is($configuration->getDefinitions(), array_merge($definitions, $defs), '->addDefinitions() adds the service definitions');

try
{
  $configuration->getDefinition('baz');
  $t->fail('->getDefinition() throws an InvalidArgumentException if the service definition does not exist');
}
catch (InvalidArgumentException $e)
{
  $t->pass('->getDefinition() throws an InvalidArgumentException if the service definition does not exist');
}

// ->findDefinition()
$t->diag('->findDefinition()');
$configuration = new BuilderConfiguration(array('foo' => $definition = new Definition('FooClass')));
$configuration->setAlias('bar', 'foo');
$configuration->setAlias('foobar', 'bar');
$t->is($configuration->findDefinition('foobar'), $definition, '->findDefinition() returns a Definition');
