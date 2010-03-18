<?php

/*
 * This file is part of the symfony package.
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 * 
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Tests\Components\DependencyInjection\Loader;

require_once __DIR__.'/../../../bootstrap.php';

use Symfony\Components\DependencyInjection\Builder;
use Symfony\Components\DependencyInjection\Reference;
use Symfony\Components\DependencyInjection\Definition;
use Symfony\Components\DependencyInjection\Loader\Loader;
use Symfony\Components\DependencyInjection\Loader\YamlFileLoader;

class YamlFileLoaderTest extends \PHPUnit_Framework_TestCase
{
  static protected $fixturesPath;

  static public function setUpBeforeClass()
  {
    self::$fixturesPath = realpath(__DIR__.'/../../../../../fixtures/Symfony/Components/DependencyInjection/');
    require_once self::$fixturesPath.'/includes/ProjectExtension.php';
  }

  public function testLoadFile()
  {
    $loader = new ProjectLoader3(self::$fixturesPath.'/ini');

    try
    {
      $loader->loadFile('foo.yml');
      $this->fail('->load() throws an InvalidArgumentException if the loaded file does not exist');
    }
    catch (\InvalidArgumentException $e)
    {
    }

    try
    {
      $loader->loadFile('parameters.ini');
      $this->fail('->load() throws an InvalidArgumentException if the loaded file is not a valid YAML file');
    }
    catch (\InvalidArgumentException $e)
    {
    }

    $loader = new ProjectLoader3(self::$fixturesPath.'/yaml');

    foreach (array('nonvalid1', 'nonvalid2') as $fixture)
    {
      try
      {
        $loader->loadFile($fixture.'.yml');
        $this->fail('->load() throws an InvalidArgumentException if the loaded file does not validate');
      }
      catch (\InvalidArgumentException $e)
      {
      }
    }
  }

  public function testLoadParameters()
  {
    $loader = new ProjectLoader3(self::$fixturesPath.'/yaml');
    $config = $loader->load('services2.yml');
    $this->assertEquals($config->getParameters(), array('foo' => 'bar', 'values' => array(true, false, 0, 1000.3), 'bar' => 'foo', 'foo_bar' => new Reference('foo_bar')), '->load() converts YAML keys to lowercase');
  }

  public function testLoadImports()
  {
    $loader = new ProjectLoader3(self::$fixturesPath.'/yaml');
    $config = $loader->load('services4.yml');

    $actual = $config->getParameters();
    $expected = array('foo' => 'bar', 'values' => array(true, false), 'bar' => '%foo%', 'foo_bar' => new Reference('foo_bar'), 'imported_from_ini' => true, 'imported_from_xml' => true);
    $this->assertEquals(array_keys($actual), array_keys($expected), '->load() imports and merges imported files');
  }

  public function testLoadServices()
  {
    $loader = new ProjectLoader3(self::$fixturesPath.'/yaml');
    $config = $loader->load('services6.yml');
    $services = $config->getDefinitions();
    $this->assertTrue(isset($services['foo']), '->load() parses service elements');
    $this->assertEquals(get_class($services['foo']), 'Symfony\\Components\\DependencyInjection\\Definition', '->load() converts service element to Definition instances');
    $this->assertEquals($services['foo']->getClass(), 'FooClass', '->load() parses the class attribute');
    $this->assertTrue($services['shared']->isShared(), '->load() parses the shared attribute');
    $this->assertTrue(!$services['non_shared']->isShared(), '->load() parses the shared attribute');
    $this->assertEquals($services['constructor']->getConstructor(), 'getInstance', '->load() parses the constructor attribute');
    $this->assertEquals($services['file']->getFile(), '%path%/foo.php', '->load() parses the file tag');
    $this->assertEquals($services['arguments']->getArguments(), array('foo', new Reference('foo'), array(true, false)), '->load() parses the argument tags');
    $this->assertEquals($services['configurator1']->getConfigurator(), 'sc_configure', '->load() parses the configurator tag');
    $this->assertEquals($services['configurator2']->getConfigurator(), array(new Reference('baz'), 'configure'), '->load() parses the configurator tag');
    $this->assertEquals($services['configurator3']->getConfigurator(), array('BazClass', 'configureStatic'), '->load() parses the configurator tag');
    $this->assertEquals($services['method_call1']->getMethodCalls(), array(array('setBar', array())), '->load() parses the method_call tag');
    $this->assertEquals($services['method_call2']->getMethodCalls(), array(array('setBar', array('foo', new Reference('foo'), array(true, false)))), '->load() parses the method_call tag');
    $aliases = $config->getAliases();
    $this->assertTrue(isset($aliases['alias_for_foo']), '->load() parses aliases');
    $this->assertEquals($aliases['alias_for_foo'], 'foo', '->load() parses aliases');
  }

  public function testExtensions()
  {
    Loader::registerExtension(new \ProjectExtension());
    $loader = new ProjectLoader3(self::$fixturesPath.'/yaml');

    $config = $loader->load('services10.yml');
    $services = $config->getDefinitions();
    $parameters = $config->getParameters();
    $this->assertTrue(isset($services['project.service.bar']), '->load() parses extension elements');
    $this->assertTrue(isset($parameters['project.parameter.bar']), '->load() parses extension elements');

    try
    {
      $config = $loader->load('services11.yml');
      $this->fail('->load() throws an InvalidArgumentException if the tag is not valid');
    }
    catch (\InvalidArgumentException $e)
    {
    }

    try
    {
      $config = $loader->load('services12.yml');
      $this->fail('->load() throws an InvalidArgumentException if an extension is not loaded');
    }
    catch (\InvalidArgumentException $e)
    {
    }
  }
}

class ProjectLoader3 extends YamlFileLoader
{
  public function loadFile($file)
  {
    return parent::loadFile($file);
  }
}
