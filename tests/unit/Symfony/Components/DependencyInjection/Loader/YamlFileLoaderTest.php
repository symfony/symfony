<?php

/*
 * This file is part of the symfony package.
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 * 
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

require_once __DIR__.'/../../../../bootstrap.php';

use Symfony\Components\DependencyInjection\Builder;
use Symfony\Components\DependencyInjection\Reference;
use Symfony\Components\DependencyInjection\Definition;
use Symfony\Components\DependencyInjection\Loader\Loader;
use Symfony\Components\DependencyInjection\Loader\YamlFileLoader;

$t = new LimeTest(29);

$fixturesPath = realpath(__DIR__.'/../../../../../fixtures/Symfony/Components/DependencyInjection/');

require_once $fixturesPath.'/includes/ProjectExtension.php';

class ProjectLoader extends YamlFileLoader
{
  public function getFilesAsArray(array $files)
  {
    return parent::getFilesAsArray($files);
  }
}

// ->getFilesAsArray()
$t->diag('->getFilesAsArray()');

$loader = new ProjectLoader($fixturesPath.'/ini');

try
{
  $loader->getFilesAsArray(array('foo.yml'));
  $t->fail('->load() throws an InvalidArgumentException if the loaded file does not exist');
}
catch (InvalidArgumentException $e)
{
  $t->pass('->load() throws an InvalidArgumentException if the loaded file does not exist');
}

try
{
  $loader->getFilesAsArray(array('parameters.ini'));
  $t->fail('->load() throws an InvalidArgumentException if the loaded file is not a valid YAML file');
}
catch (InvalidArgumentException $e)
{
  $t->pass('->load() throws an InvalidArgumentException if the loaded file is not a valid YAML file');
}

$loader = new ProjectLoader($fixturesPath.'/yaml');

foreach (array('nonvalid1', 'nonvalid2') as $fixture)
{
  try
  {
    $loader->getFilesAsArray(array($fixture.'.yml'));
    $t->fail('->load() throws an InvalidArgumentException if the loaded file does not validate');
  }
  catch (InvalidArgumentException $e)
  {
    $t->pass('->load() throws an InvalidArgumentException if the loaded file does not validate');
  }
}

$yamls = $loader->getFilesAsArray(array('services1.yml'));
$t->ok(is_array($yamls), '->getFilesAsArray() returns an array');
$t->is(key($yamls), realpath($fixturesPath.'/yaml/services1.yml'), '->getFilesAsArray() returns an array where the keys are absolutes paths to the original YAML file');

// ->load() # parameters
$t->diag('->load() # parameters');
$loader = new ProjectLoader($fixturesPath.'/yaml');
$config = $loader->load(array('services2.yml'));
$t->is($config->getParameters(), array('foo' => 'bar', 'values' => array(true, false, 0, 1000.3), 'bar' => 'foo', 'foo_bar' => new Reference('foo_bar')), '->load() converts YAML keys to lowercase');

$loader = new ProjectLoader($fixturesPath.'/yaml');
$config = $loader->load(array('services2.yml', 'services3.yml'));
$t->is($config->getParameters(), array('foo' => 'foo', 'values' => array(true, false), 'bar' => 'foo', 'foo_bar' => new Reference('foo_bar')), '->load() merges the first level of arguments when multiple files are loaded');

// ->load() # imports
$t->diag('->load() # imports');
$config = $loader->load(array('services4.yml'));
$t->is($config->getParameters(), array('foo' => 'bar', 'bar' => '%foo%', 'values' => array(true, false), 'foo_bar' => new Reference('foo_bar')), '->load() imports and merges imported files');

// ->load() # services
$t->diag('->load() # services');
$config = $loader->load(array('services6.yml'));
$services = $config->getDefinitions();
$t->ok(isset($services['foo']), '->load() parses service elements');
$t->is(get_class($services['foo']), 'Symfony\\Components\\DependencyInjection\\Definition', '->load() converts service element to Definition instances');
$t->is($services['foo']->getClass(), 'FooClass', '->load() parses the class attribute');
$t->ok($services['shared']->isShared(), '->load() parses the shared attribute');
$t->ok(!$services['non_shared']->isShared(), '->load() parses the shared attribute');
$t->is($services['constructor']->getConstructor(), 'getInstance', '->load() parses the constructor attribute');
$t->is($services['file']->getFile(), '%path%/foo.php', '->load() parses the file tag');
$t->is($services['arguments']->getArguments(), array('foo', new Reference('foo'), array(true, false)), '->load() parses the argument tags');
$t->is($services['configurator1']->getConfigurator(), 'sc_configure', '->load() parses the configurator tag');
$t->is($services['configurator2']->getConfigurator(), array(new Reference('baz'), 'configure'), '->load() parses the configurator tag');
$t->is($services['configurator3']->getConfigurator(), array('BazClass', 'configureStatic'), '->load() parses the configurator tag');
$t->is($services['method_call1']->getMethodCalls(), array(array('setBar', array())), '->load() parses the method_call tag');
$t->is($services['method_call2']->getMethodCalls(), array(array('setBar', array('foo', new Reference('foo'), array(true, false)))), '->load() parses the method_call tag');
$aliases = $config->getAliases();
$t->ok(isset($aliases['alias_for_foo']), '->load() parses aliases');
$t->is($aliases['alias_for_foo'], 'foo', '->load() parses aliases');

$config = $loader->load(array('services6.yml', 'services7.yml'));
$services = $config->getDefinitions();
$t->is($services['foo']->getClass(), 'BarClass', '->load() merges the services when multiple files are loaded');

// extensions
$t->diag('extensions');
Loader::registerExtension(new ProjectExtension());
$loader = new ProjectLoader($fixturesPath.'/yaml');

$config = $loader->load('services10.yml');
$services = $config->getDefinitions();
$parameters = $config->getParameters();
$t->ok(isset($services['project.service.bar']), '->load() parses extension elements');
$t->ok(isset($parameters['project.parameter.bar']), '->load() parses extension elements');

try
{
  $config = $loader->load('services11.yml');
  $t->fail('->load() throws an InvalidArgumentException if the tag is not valid');
}
catch (InvalidArgumentException $e)
{
  $t->pass('->load() throws an InvalidArgumentException if the tag is not valid');
}

try
{
  $config = $loader->load('services12.yml');
  $t->fail('->load() throws an InvalidArgumentException if an extension is not loaded');
}
catch (InvalidArgumentException $e)
{
  $t->pass('->load() throws an InvalidArgumentException if an extension is not loaded');
}
