<?php

/*
 * This file is part of the symfony package.
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 * 
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

require_once __DIR__.'/../../../bootstrap.php';

use Symfony\Components\DependencyInjection\Container;

$fixturesPath = __DIR__.'/../../../../fixtures/Symfony/Components/DependencyInjection/';

$t = new LimeTest(41);

// __construct()
$t->diag('__construct()');
$sc = new Container();
$t->is(spl_object_hash($sc->getService('service_container')), spl_object_hash($sc), '__construct() automatically registers itself as a service');

$sc = new Container(array('foo' => 'bar'));
$t->is($sc->getParameters(), array('foo' => 'bar'), '__construct() takes an array of parameters as its first argument');

// ->setParameters() ->getParameters()
$t->diag('->setParameters() ->getParameters()');

$sc = new Container();
$t->is($sc->getParameters(), array(), '->getParameters() returns an empty array if no parameter has been defined');

$sc->setParameters(array('foo' => 'bar'));
$t->is($sc->getParameters(), array('foo' => 'bar'), '->setParameters() sets the parameters');

$sc->setParameters(array('bar' => 'foo'));
$t->is($sc->getParameters(), array('bar' => 'foo'), '->setParameters() overrides the previous defined parameters');

$sc->setParameters(array('Bar' => 'foo'));
$t->is($sc->getParameters(), array('bar' => 'foo'), '->setParameters() converts the key to lowercase');

// ->setParameter() ->getParameter()
$t->diag('->setParameter() ->getParameter() ');

$sc = new Container(array('foo' => 'bar'));
$sc->setParameter('bar', 'foo');
$t->is($sc->getParameter('bar'), 'foo', '->setParameter() sets the value of a new parameter');
$t->is($sc['bar'], 'foo', '->offsetGet() gets the value of a parameter');

$sc['bar1'] = 'foo1';
$t->is($sc['bar1'], 'foo1', '->offsetset() sets the value of a parameter');

unset($sc['bar1']);
$t->ok(!isset($sc['bar1']), '->offsetUnset() removes a parameter');

$sc->setParameter('foo', 'baz');
$t->is($sc->getParameter('foo'), 'baz', '->setParameter() overrides previously set parameter');

$sc->setParameter('Foo', 'baz1');
$t->is($sc->getParameter('foo'), 'baz1', '->setParameter() converts the key to lowercase');
$t->is($sc->getParameter('FOO'), 'baz1', '->getParameter() converts the key to lowercase');
$t->is($sc['FOO'], 'baz1', '->offsetGet() converts the key to lowercase');

try
{
  $sc->getParameter('baba');
  $t->fail('->getParameter() thrown an \InvalidArgumentException if the key does not exist');
}
catch (\InvalidArgumentException $e)
{
  $t->pass('->getParameter() thrown an \InvalidArgumentException if the key does not exist');
}

try
{
  $sc['baba'];
  $t->fail('->offsetGet() thrown an \InvalidArgumentException if the key does not exist');
}
catch (\InvalidArgumentException $e)
{
  $t->pass('->offsetGet() thrown an \InvalidArgumentException if the key does not exist');
}

// ->hasParameter()
$t->diag('->hasParameter()');
$sc = new Container(array('foo' => 'bar'));
$t->ok($sc->hasParameter('foo'), '->hasParameter() returns true if a parameter is defined');
$t->ok($sc->hasParameter('Foo'), '->hasParameter() converts the key to lowercase');
$t->ok(isset($sc['Foo']), '->offsetExists() converts the key to lowercase');
$t->ok(!$sc->hasParameter('bar'), '->hasParameter() returns false if a parameter is not defined');
$t->ok(isset($sc['foo']), '->offsetExists() returns true if a parameter is defined');
$t->ok(!isset($sc['bar']), '->offsetExists() returns false if a parameter is not defined');

// ->addParameters()
$t->diag('->addParameters()');
$sc = new Container(array('foo' => 'bar'));
$sc->addParameters(array('bar' => 'foo'));
$t->is($sc->getParameters(), array('foo' => 'bar', 'bar' => 'foo'), '->addParameters() adds parameters to the existing ones');
$sc->addParameters(array('Bar' => 'fooz'));
$t->is($sc->getParameters(), array('foo' => 'bar', 'bar' => 'fooz'), '->addParameters() converts keys to lowercase');

// ->setService() ->hasService() ->getService()
$t->diag('->setService() ->hasService() ->getService()');
$sc = new Container();
$sc->setService('foo', $obj = new stdClass());
$t->is(spl_object_hash($sc->getService('foo')), spl_object_hash($obj), '->setService() registers a service under a key name');

$sc->foo1 = $obj1 = new stdClass();
$t->is(spl_object_hash($sc->foo1), spl_object_hash($obj1), '->__set() sets a service');

$t->is(spl_object_hash($sc->foo), spl_object_hash($obj), '->__get() gets a service by name');
$t->ok($sc->hasService('foo'), '->hasService() returns true if the service is defined');
$t->ok(isset($sc->foo), '->__isset() returns true if the service is defined');
$t->ok(!$sc->hasService('bar'), '->hasService() returns false if the service is not defined');
$t->ok(!isset($sc->bar), '->__isset() returns false if the service is not defined');

// ->getServiceIds()
$t->diag('->getServiceIds()');
$sc = new Container();
$sc->setService('foo', $obj = new stdClass());
$sc->setService('bar', $obj = new stdClass());
$t->is($sc->getServiceIds(), array('service_container', 'foo', 'bar'), '->getServiceIds() returns all defined service ids');

class ProjectServiceContainer extends Container
{
  public $__bar, $__foo_bar, $__foo_baz;

  public function __construct()
  {
    parent::__construct();

    $this->__bar = new stdClass();
    $this->__foo_bar = new stdClass();
    $this->__foo_baz = new stdClass();
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

$sc = new ProjectServiceContainer();
$t->is(spl_object_hash($sc->getService('bar')), spl_object_hash($sc->__bar), '->getService() looks for a getXXXService() method');
$t->ok($sc->hasService('bar'), '->hasService() returns true if the service has been defined as a getXXXService() method');

$sc->setService('bar', $bar = new stdClass());
$t->is(spl_object_hash($sc->getService('bar')), spl_object_hash($bar), '->getService() prefers to return a service defined with setService() than one defined with a getXXXService() method');

try
{
  $sc->getService('baba');
  $t->fail('->getService() thrown an \InvalidArgumentException if the service does not exist');
}
catch (\InvalidArgumentException $e)
{
  $t->pass('->getService() thrown an \InvalidArgumentException if the service does not exist');
}

try
{
  $sc->baba;
  $t->fail('->__get() thrown an \InvalidArgumentException if the service does not exist');
}
catch (\InvalidArgumentException $e)
{
  $t->pass('->__get() thrown an \InvalidArgumentException if the service does not exist');
}

try
{
  unset($sc->baba);
  $t->fail('->__unset() thrown an LogicException if you try to remove a service');
}
catch (LogicException $e)
{
  $t->pass('->__unset() thrown an LogicException if you try to remove a service');
}

$t->is(spl_object_hash($sc->getService('foo_bar')), spl_object_hash($sc->__foo_bar), '->getService() camelizes the service id when looking for a method');
$t->is(spl_object_hash($sc->getService('foo.baz')), spl_object_hash($sc->__foo_baz), '->getService() camelizes the service id when looking for a method');

// Iterator
$t->diag('implements Iterator');
$sc = new ProjectServiceContainer();
$sc->setService('foo', $foo = new stdClass());
$services = array();
foreach ($sc as $id => $service)
{
  $services[$id] = spl_object_hash($service);
}
$t->is($services, array(
  'service_container' => spl_object_hash($sc),
  'bar' => spl_object_hash($sc->__bar),
  'foo_bar' => spl_object_hash($sc->__foo_bar),
  'foo.baz' => spl_object_hash($sc->__foo_baz),
  'foo' => spl_object_hash($foo)),
'Container implements the Iterator interface');
