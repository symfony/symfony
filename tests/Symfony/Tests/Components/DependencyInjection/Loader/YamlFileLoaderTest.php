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
use Symfony\Components\DependencyInjection\Loader\YamlFileLoader;

class YamlFileLoaderTest extends \PHPUnit_Framework_TestCase
{
    static protected $fixturesPath;

    static public function setUpBeforeClass()
    {
        self::$fixturesPath = realpath(__DIR__.'/../Fixtures/');
        require_once self::$fixturesPath.'/includes/ProjectExtension.php';
    }

    public function testLoadFile()
    {
        $loader = new ProjectLoader3(self::$fixturesPath.'/ini');

        try {
            $loader->loadFile('foo.yml');
            $this->fail('->load() throws an InvalidArgumentException if the loaded file does not exist');
        } catch (\Exception $e) {
            $this->assertInstanceOf('\InvalidArgumentException', $e, '->load() throws an InvalidArgumentException if the loaded file does not exist');
            $this->assertEquals('The service file "foo.yml" is not valid.', $e->getMessage(), '->load() throws an InvalidArgumentException if the loaded file does not exist');
        }

        try {
            $loader->loadFile('parameters.ini');
            $this->fail('->load() throws an InvalidArgumentException if the loaded file is not a valid YAML file');
        } catch (\Exception $e) {
            $this->assertInstanceOf('\InvalidArgumentException', $e, '->load() throws an InvalidArgumentException if the loaded file is not a valid YAML file');
            $this->assertEquals('The service file "parameters.ini" is not valid.', $e->getMessage(), '->load() throws an InvalidArgumentException if the loaded file is not a valid YAML file');
        }

        $loader = new ProjectLoader3(self::$fixturesPath.'/yaml');

        foreach (array('nonvalid1', 'nonvalid2') as $fixture) {
            try {
                $loader->loadFile($fixture.'.yml');
                $this->fail('->load() throws an InvalidArgumentException if the loaded file does not validate');
            } catch (\Exception $e) {
                $this->assertInstanceOf('\InvalidArgumentException', $e, '->load() throws an InvalidArgumentException if the loaded file does not validate');
                $this->assertStringMatchesFormat('The service file "nonvalid%d.yml" is not valid.', $e->getMessage(), '->load() throws an InvalidArgumentException if the loaded file does not validate');
            }
        }
    }

    public function testLoadParameters()
    {
        $loader = new ProjectLoader3(self::$fixturesPath.'/yaml');
        $config = $loader->load('services2.yml');
        $this->assertEquals(array('foo' => 'bar', 'values' => array(true, false, 0, 1000.3), 'bar' => 'foo', 'foo_bar' => new Reference('foo_bar')), $config->getParameterBag()->all(), '->load() converts YAML keys to lowercase');
    }

    public function testLoadImports()
    {
        $loader = new ProjectLoader3(self::$fixturesPath.'/yaml');
        $config = $loader->load('services4.yml');

        $actual = $config->getParameterBag()->all();
        $expected = array('foo' => 'bar', 'values' => array(true, false), 'bar' => '%foo%', 'foo_bar' => new Reference('foo_bar'), 'imported_from_ini' => true, 'imported_from_xml' => true);
        $this->assertEquals(array_keys($expected), array_keys($actual), '->load() imports and merges imported files');
    }

    public function testLoadServices()
    {
        $loader = new ProjectLoader3(self::$fixturesPath.'/yaml');
        $config = $loader->load('services6.yml');
        $services = $config->getDefinitions();
        $this->assertTrue(isset($services['foo']), '->load() parses service elements');
        $this->assertEquals('Symfony\\Components\\DependencyInjection\\Definition', get_class($services['foo']), '->load() converts service element to Definition instances');
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
        $this->assertTrue(isset($aliases['alias_for_foo']), '->load() parses aliases');
        $this->assertEquals('foo', $aliases['alias_for_foo'], '->load() parses aliases');
    }

    public function testExtensions()
    {
        Loader::registerExtension(new \ProjectExtension());
        $loader = new ProjectLoader3(self::$fixturesPath.'/yaml');

        $config = $loader->load('services10.yml');
        $services = $config->getDefinitions();
        $parameters = $config->getParameterBag()->all();

        $this->assertTrue(isset($services['project.service.bar']), '->load() parses extension elements');
        $this->assertTrue(isset($parameters['project.parameter.bar']), '->load() parses extension elements');

        $this->assertEquals('BAR', $services['project.service.foo']->getClass(), '->load() parses extension elements');
        $this->assertEquals('BAR', $parameters['project.parameter.foo'], '->load() parses extension elements');

        try {
            $config = $loader->load('services11.yml');
            $this->fail('->load() throws an InvalidArgumentException if the tag is not valid');
        } catch (\Exception $e) {
            $this->assertInstanceOf('\InvalidArgumentException', $e, '->load() throws an InvalidArgumentException if the tag is not valid');
            $this->assertStringStartsWith('There is no extension able to load the configuration for "foobar.foobar" (in', $e->getMessage(), '->load() throws an InvalidArgumentException if the tag is not valid');
        }

        try {
            $config = $loader->load('services12.yml');
            $this->fail('->load() throws an InvalidArgumentException if an extension is not loaded');
        } catch (\Exception $e) {
            $this->assertInstanceOf('\InvalidArgumentException', $e, '->load() throws an InvalidArgumentException if an extension is not loaded');
            $this->assertStringStartsWith('The "foobar" tag is not valid (in', $e->getMessage(), '->load() throws an InvalidArgumentException if an extension is not loaded');
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
