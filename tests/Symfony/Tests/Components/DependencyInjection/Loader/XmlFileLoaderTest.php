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
use Symfony\Components\DependencyInjection\Loader\XmlFileLoader;

class XmlFileLoaderTest extends \PHPUnit_Framework_TestCase
{
  static protected $fixturesPath;

  static public function setUpBeforeClass()
  {
    self::$fixturesPath = realpath(__DIR__.'/../../../../../fixtures/Symfony/Components/DependencyInjection/');
    require_once self::$fixturesPath.'/includes/ProjectExtension.php';
  }

  public function testLoad()
  {
    $loader = new ProjectLoader2(self::$fixturesPath.'/ini');

    try
    {
      $loader->load('foo.xml');
      $this->fail('->load() throws an InvalidArgumentException if the loaded file does not exist');
    }
    catch (\InvalidArgumentException $e)
    {
    }
  }

  public function testParseFile()
  {
    $loader = new ProjectLoader2(self::$fixturesPath.'/ini');

    try
    {
      $loader->parseFile(self::$fixturesPath.'/ini/parameters.ini');
      $this->fail('->parseFile() throws an InvalidArgumentException if the loaded file is not a valid XML file');
    }
    catch (\InvalidArgumentException $e)
    {
    }

    $loader = new ProjectLoader2(self::$fixturesPath.'/xml');

    try
    {
      $loader->parseFile(self::$fixturesPath.'/xml/nonvalid.xml');
      $this->fail('->parseFile() throws an InvalidArgumentException if the loaded file does not validate the XSD');
    }
    catch (\InvalidArgumentException $e)
    {
    }

    $xml = $loader->parseFile(self::$fixturesPath.'/xml/services1.xml');
    $this->assertEquals(get_class($xml), 'Symfony\\Components\\DependencyInjection\\SimpleXMLElement', '->parseFile() returns an SimpleXMLElement object');
  }

  public function testLoadParameters()
  {
    $loader = new ProjectLoader2(self::$fixturesPath.'/xml');
    $config = $loader->load('services2.xml');

    $actual = $config->getParameters();
    $expected = array('a string', 'foo' => 'bar', 'values' => array(0, 'integer' => 4, 100 => null, 'true', true, false, 'on', 'off', 'float' => 1.3, 1000.3, 'a string', array('foo', 'bar')), 'foo_bar' => new Reference('foo_bar'));

    $this->assertEquals($actual, $expected, '->load() converts XML values to PHP ones');
  }

  public function testLoadImports()
  {
    $loader = new ProjectLoader2(self::$fixturesPath.'/xml');
    $config = $loader->load('services4.xml');

    $actual = $config->getParameters();
    $expected = array('a string', 'foo' => 'bar', 'values' => array(true, false), 'foo_bar' => new Reference('foo_bar'), 'bar' => '%foo%', 'imported_from_ini' => true, 'imported_from_yaml' => true);

    $this->assertEquals(array_keys($actual), array_keys($expected), '->load() imports and merges imported files');
  }

  public function testLoadAnonymousServices()
  {
    $loader = new ProjectLoader2(self::$fixturesPath.'/xml');
    $config = $loader->load('services5.xml');
    $services = $config->getDefinitions();
    $this->assertEquals(count($services), 3, '->load() attributes unique ids to anonymous services');
    $args = $services['foo']->getArguments();
    $this->assertEquals(count($args), 1, '->load() references anonymous services as "normal" ones');
    $this->assertEquals(get_class($args[0]), 'Symfony\\Components\\DependencyInjection\\Reference', '->load() converts anonymous services to references to "normal" services');
    $this->assertTrue(isset($services[(string) $args[0]]), '->load() makes a reference to the created ones');
    $inner = $services[(string) $args[0]];
    $this->assertEquals($inner->getClass(), 'BarClass', '->load() uses the same configuration as for the anonymous ones');

    $args = $inner->getArguments();
    $this->assertEquals(count($args), 1, '->load() references anonymous services as "normal" ones');
    $this->assertEquals(get_class($args[0]), 'Symfony\\Components\\DependencyInjection\\Reference', '->load() converts anonymous services to references to "normal" services');
    $this->assertTrue(isset($services[(string) $args[0]]), '->load() makes a reference to the created ones');
    $inner = $services[(string) $args[0]];
    $this->assertEquals($inner->getClass(), 'BazClass', '->load() uses the same configuration as for the anonymous ones');
  }

  public function testLoadServices()
  {
    $loader = new ProjectLoader2(self::$fixturesPath.'/xml');
    $config = $loader->load('services6.xml');
    $services = $config->getDefinitions();
    $this->assertTrue(isset($services['foo']), '->load() parses <service> elements');
    $this->assertEquals(get_class($services['foo']), 'Symfony\\Components\\DependencyInjection\\Definition', '->load() converts <service> element to Definition instances');
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
    $this->assertTrue(isset($aliases['alias_for_foo']), '->load() parses <service> elements');
    $this->assertEquals($aliases['alias_for_foo'], 'foo', '->load() parses aliases');
  }

  public function testConvertDomElementToArray()
  {
    $doc = new \DOMDocument("1.0");
    $doc->loadXML('<foo>bar</foo>');
    $this->assertEquals(ProjectLoader2::convertDomElementToArray($doc->documentElement), 'bar', '::convertDomElementToArray() converts a \DomElement to an array');

    $doc = new \DOMDocument("1.0");
    $doc->loadXML('<foo foo="bar" />');
    $this->assertEquals(ProjectLoader2::convertDomElementToArray($doc->documentElement), array('foo' => 'bar'), '::convertDomElementToArray() converts a \DomElement to an array');

    $doc = new \DOMDocument("1.0");
    $doc->loadXML('<foo><foo>bar</foo></foo>');
    $this->assertEquals(ProjectLoader2::convertDomElementToArray($doc->documentElement), array('foo' => 'bar'), '::convertDomElementToArray() converts a \DomElement to an array');

    $doc = new \DOMDocument("1.0");
    $doc->loadXML('<foo><foo>bar<foo>bar</foo></foo></foo>');
    $this->assertEquals(ProjectLoader2::convertDomElementToArray($doc->documentElement), array('foo' => array('value' => 'bar', 'foo' => 'bar')), '::convertDomElementToArray() converts a \DomElement to an array');

    $doc = new \DOMDocument("1.0");
    $doc->loadXML('<foo><foo></foo></foo>');
    $this->assertEquals(ProjectLoader2::convertDomElementToArray($doc->documentElement), array('foo' => null), '::convertDomElementToArray() converts a \DomElement to an array');

    $doc = new \DOMDocument("1.0");
    $doc->loadXML('<foo><foo><!-- foo --></foo></foo>');
    $this->assertEquals(ProjectLoader2::convertDomElementToArray($doc->documentElement), array('foo' => null), '::convertDomElementToArray() converts a \DomElement to an array');
  }

  public function testExtensions()
  {
    Loader::registerExtension(new \ProjectExtension());
    $loader = new ProjectLoader2(self::$fixturesPath.'/xml');

    $config = $loader->load('services10.xml');
    $services = $config->getDefinitions();
    $parameters = $config->getParameters();
    $this->assertTrue(isset($services['project.service.bar']), '->load() parses extension elements');
    $this->assertTrue(isset($parameters['project.parameter.bar']), '->load() parses extension elements');

    try
    {
      $config = $loader->load('services11.xml');
      $this->fail('->load() throws an InvalidArgumentException if the tag is not valid');
    }
    catch (\InvalidArgumentException $e)
    {
    }

    try
    {
      $config = $loader->load('services12.xml');
      $this->fail('->load() throws an InvalidArgumentException if an extension is not loaded');
    }
    catch (\InvalidArgumentException $e)
    {
    }
  }
}

class ProjectLoader2 extends XmlFileLoader
{
  public function parseFile($file)
  {
    return parent::parseFile($file);
  }
}
