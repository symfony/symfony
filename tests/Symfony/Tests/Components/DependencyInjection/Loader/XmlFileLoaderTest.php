<?php

/*
 * This file is part of the Symfony package.
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Tests\Components\DependencyInjection\Loader;

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
        self::$fixturesPath = realpath(__DIR__.'/../Fixtures/');
        require_once self::$fixturesPath.'/includes/ProjectExtension.php';
    }

    public function testLoad()
    {
        $loader = new ProjectLoader2(self::$fixturesPath.'/ini');

        try {
            $loader->load('foo.xml');
            $this->fail('->load() throws an InvalidArgumentException if the loaded file does not exist');
        } catch (\Exception $e) {
            $this->assertInstanceOf('\InvalidArgumentException', $e, '->load() throws an InvalidArgumentException if the loaded file does not exist');
            $this->assertStringStartsWith('The file "foo.xml" does not exist (in:', $e->getMessage(), '->load() throws an InvalidArgumentException if the loaded file does not exist');
        }
    }

    public function testParseFile()
    {
        $loader = new ProjectLoader2(self::$fixturesPath.'/ini');

        try {
            $loader->parseFile(self::$fixturesPath.'/ini/parameters.ini');
            $this->fail('->parseFile() throws an InvalidArgumentException if the loaded file is not a valid XML file');
        } catch (\Exception $e) {
            $this->assertInstanceOf('\InvalidArgumentException', $e, '->parseFile() throws an InvalidArgumentException if the loaded file is not a valid XML file');
            $this->assertStringStartsWith('[ERROR 4] Start tag expected, \'<\' not found (in', $e->getMessage(), '->parseFile() throws an InvalidArgumentException if the loaded file is not a valid XML file');
        }

        $loader = new ProjectLoader2(self::$fixturesPath.'/xml');

        try {
            $loader->parseFile(self::$fixturesPath.'/xml/nonvalid.xml');
            $this->fail('->parseFile() throws an InvalidArgumentException if the loaded file does not validate the XSD');
        } catch (\Exception $e) {
            $this->assertInstanceOf('\InvalidArgumentException', $e, '->parseFile() throws an InvalidArgumentException if the loaded file does not validate the XSD');
            $this->assertStringStartsWith('[ERROR 1845] Element \'nonvalid\': No matching global declaration available for the validation root. (in', $e->getMessage(), '->parseFile() throws an InvalidArgumentException if the loaded file does not validate the XSD');
        }

        $xml = $loader->parseFile(self::$fixturesPath.'/xml/services1.xml');
        $this->assertEquals('Symfony\\Components\\DependencyInjection\\SimpleXMLElement', get_class($xml), '->parseFile() returns an SimpleXMLElement object');
    }

    public function testLoadParameters()
    {
        $loader = new ProjectLoader2(self::$fixturesPath.'/xml');
        $config = $loader->load('services2.xml');

        $actual = $config->getParameterBag()->all();
        $expected = array('a string', 'foo' => 'bar', 'values' => array(0, 'integer' => 4, 100 => null, 'true', true, false, 'on', 'off', 'float' => 1.3, 1000.3, 'a string', array('foo', 'bar')), 'foo_bar' => new Reference('foo_bar'));

        $this->assertEquals($expected, $actual, '->load() converts XML values to PHP ones');
    }

    public function testLoadImports()
    {
        $loader = new ProjectLoader2(self::$fixturesPath.'/xml');
        $config = $loader->load('services4.xml');

        $actual = $config->getParameterBag()->all();
        $expected = array('a string', 'foo' => 'bar', 'values' => array(true, false), 'foo_bar' => new Reference('foo_bar'), 'bar' => '%foo%', 'imported_from_ini' => true, 'imported_from_yaml' => true);

        $this->assertEquals(array_keys($expected), array_keys($actual), '->load() imports and merges imported files');
    }

    public function testLoadAnonymousServices()
    {
        $loader = new ProjectLoader2(self::$fixturesPath.'/xml');
        $config = $loader->load('services5.xml');
        $services = $config->getDefinitions();
        $this->assertEquals(3, count($services), '->load() attributes unique ids to anonymous services');
        $args = $services['foo']->getArguments();
        $this->assertEquals(1, count($args), '->load() references anonymous services as "normal" ones');
        $this->assertEquals('Symfony\\Components\\DependencyInjection\\Reference', get_class($args[0]), '->load() converts anonymous services to references to "normal" services');
        $this->assertTrue(isset($services[(string) $args[0]]), '->load() makes a reference to the created ones');
        $inner = $services[(string) $args[0]];
        $this->assertEquals('BarClass', $inner->getClass(), '->load() uses the same configuration as for the anonymous ones');

        $args = $inner->getArguments();
        $this->assertEquals(1, count($args), '->load() references anonymous services as "normal" ones');
        $this->assertEquals('Symfony\\Components\\DependencyInjection\\Reference', get_class($args[0]), '->load() converts anonymous services to references to "normal" services');
        $this->assertTrue(isset($services[(string) $args[0]]), '->load() makes a reference to the created ones');
        $inner = $services[(string) $args[0]];
        $this->assertEquals('BazClass', $inner->getClass(), '->load() uses the same configuration as for the anonymous ones');
    }

    public function testLoadServices()
    {
        $loader = new ProjectLoader2(self::$fixturesPath.'/xml');
        $config = $loader->load('services6.xml');
        $services = $config->getDefinitions();
        $this->assertTrue(isset($services['foo']), '->load() parses <service> elements');
        $this->assertEquals('Symfony\\Components\\DependencyInjection\\Definition', get_class($services['foo']), '->load() converts <service> element to Definition instances');
        $this->assertEquals('FooClass', $services['foo']->getClass(), '->load() parses the class attribute');
        $this->assertTrue($services['shared']->isShared(), '->load() parses the shared attribute');
        $this->assertFalse($services['non_shared']->isShared(), '->load() parses the shared attribute');
        $this->assertEquals('getInstance', $services['constructor']->getConstructor(), '->load() parses the constructor attribute');
        $this->assertEquals('%path%/foo.php', $services['file']->getFile(), '->load() parses the file tag');
        $this->assertEquals(array('foo', new Reference('foo'), array(true, false)), $services['arguments']->getArguments(), '->load() parses the argument tags');
        $this->assertEquals('sc_configure', $services['configurator1']->getConfigurator(), '->load() parses the configurator tag');
        $this->assertEquals(array(new Reference('baz'), 'configure'), $services['configurator2']->getConfigurator(), '->load() parses the configurator tag');
        $this->assertEquals(array('BazClass', 'configureStatic'), $services['configurator3']->getConfigurator(), '->load() parses the configurator tag');
        $this->assertEquals(array(array('setBar', array())), $services['method_call1']->getMethodCalls(), '->load() parses the method_call tag');
        $this->assertEquals(array(array('setBar', array('foo', new Reference('foo'), array(true, false)))), $services['method_call2']->getMethodCalls(), '->load() parses the method_call tag');
        $aliases = $config->getAliases();
        $this->assertTrue(isset($aliases['alias_for_foo']), '->load() parses <service> elements');
        $this->assertEquals('foo', $aliases['alias_for_foo'], '->load() parses aliases');
    }

    public function testConvertDomElementToArray()
    {
        $doc = new \DOMDocument("1.0");
        $doc->loadXML('<foo>bar</foo>');
        $this->assertEquals('bar', ProjectLoader2::convertDomElementToArray($doc->documentElement), '::convertDomElementToArray() converts a \DomElement to an array');

        $doc = new \DOMDocument("1.0");
        $doc->loadXML('<foo foo="bar" />');
        $this->assertEquals(array('foo' => 'bar'), ProjectLoader2::convertDomElementToArray($doc->documentElement), '::convertDomElementToArray() converts a \DomElement to an array');

        $doc = new \DOMDocument("1.0");
        $doc->loadXML('<foo><foo>bar</foo></foo>');
        $this->assertEquals(array('foo' => 'bar'), ProjectLoader2::convertDomElementToArray($doc->documentElement), '::convertDomElementToArray() converts a \DomElement to an array');

        $doc = new \DOMDocument("1.0");
        $doc->loadXML('<foo><foo>bar<foo>bar</foo></foo></foo>');
        $this->assertEquals(array('foo' => array('value' => 'bar', 'foo' => 'bar')), ProjectLoader2::convertDomElementToArray($doc->documentElement), '::convertDomElementToArray() converts a \DomElement to an array');

        $doc = new \DOMDocument("1.0");
        $doc->loadXML('<foo><foo></foo></foo>');
        $this->assertEquals(array('foo' => null), ProjectLoader2::convertDomElementToArray($doc->documentElement), '::convertDomElementToArray() converts a \DomElement to an array');

        $doc = new \DOMDocument("1.0");
        $doc->loadXML('<foo><foo><!-- foo --></foo></foo>');
        $this->assertEquals(array('foo' => null), ProjectLoader2::convertDomElementToArray($doc->documentElement), '::convertDomElementToArray() converts a \DomElement to an array');
    }

    public function testExtensions()
    {
        Loader::registerExtension(new \ProjectExtension());
        $loader = new ProjectLoader2(self::$fixturesPath.'/xml');

        $config = $loader->load('services10.xml');
        $services = $config->getDefinitions();
        $parameters = $config->getParameterBag()->all();

        $this->assertTrue(isset($services['project.service.bar']), '->load() parses extension elements');
        $this->assertTrue(isset($parameters['project.parameter.bar']), '->load() parses extension elements');

        $this->assertEquals('BAR', $services['project.service.foo']->getClass(), '->load() parses extension elements');
        $this->assertEquals('BAR', $parameters['project.parameter.foo'], '->load() parses extension elements');

        try {
            $config = $loader->load('services11.xml');
            $this->fail('->load() throws an InvalidArgumentException if the tag is not valid');
        } catch (\Exception $e) {
            $this->assertInstanceOf('\InvalidArgumentException', $e, '->load() throws an InvalidArgumentException if the tag is not valid');
            $this->assertStringStartsWith('There is no extension able to load the configuration for "foobar:foobar" (in', $e->getMessage(), '->load() throws an InvalidArgumentException if the tag is not valid');
        }

        try {
            $config = $loader->load('services12.xml');
            $this->fail('->load() throws an InvalidArgumentException if an extension is not loaded');
        } catch (\Exception $e) {
            $this->assertInstanceOf('\InvalidArgumentException', $e, '->load() throws an InvalidArgumentException if an extension is not loaded');
            $this->assertStringStartsWith('The "foobar" tag is not valid (in', $e->getMessage(), '->load() throws an InvalidArgumentException if an extension is not loaded');
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
