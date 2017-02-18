<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DependencyInjection\Tests\Loader;

use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\DependencyInjection\Loader\IniFileLoader;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;
use Symfony\Component\Config\Loader\LoaderResolver;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\ExpressionLanguage\Expression;

class YamlFileLoaderTest extends TestCase
{
    protected static $fixturesPath;

    public static function setUpBeforeClass()
    {
        self::$fixturesPath = realpath(__DIR__.'/../Fixtures/');
        require_once self::$fixturesPath.'/includes/foo.php';
        require_once self::$fixturesPath.'/includes/ProjectExtension.php';
    }

    public function testLoadFile()
    {
        $loader = new YamlFileLoader(new ContainerBuilder(), new FileLocator(self::$fixturesPath.'/ini'));
        $r = new \ReflectionObject($loader);
        $m = $r->getMethod('loadFile');
        $m->setAccessible(true);

        try {
            $m->invoke($loader, 'foo.yml');
            $this->fail('->load() throws an InvalidArgumentException if the loaded file does not exist');
        } catch (\Exception $e) {
            $this->assertInstanceOf('\InvalidArgumentException', $e, '->load() throws an InvalidArgumentException if the loaded file does not exist');
            $this->assertEquals('The service file "foo.yml" is not valid.', $e->getMessage(), '->load() throws an InvalidArgumentException if the loaded file does not exist');
        }

        try {
            $m->invoke($loader, 'parameters.ini');
            $this->fail('->load() throws an InvalidArgumentException if the loaded file is not a valid YAML file');
        } catch (\Exception $e) {
            $this->assertInstanceOf('\InvalidArgumentException', $e, '->load() throws an InvalidArgumentException if the loaded file is not a valid YAML file');
            $this->assertEquals('The service file "parameters.ini" is not valid.', $e->getMessage(), '->load() throws an InvalidArgumentException if the loaded file is not a valid YAML file');
        }

        $loader = new YamlFileLoader(new ContainerBuilder(), new FileLocator(self::$fixturesPath.'/yaml'));

        foreach (array('nonvalid1', 'nonvalid2') as $fixture) {
            try {
                $m->invoke($loader, $fixture.'.yml');
                $this->fail('->load() throws an InvalidArgumentException if the loaded file does not validate');
            } catch (\Exception $e) {
                $this->assertInstanceOf('\InvalidArgumentException', $e, '->load() throws an InvalidArgumentException if the loaded file does not validate');
                $this->assertStringMatchesFormat('The service file "nonvalid%d.yml" is not valid.', $e->getMessage(), '->load() throws an InvalidArgumentException if the loaded file does not validate');
            }
        }
    }

    /**
     * @dataProvider provideInvalidFiles
     * @expectedException \Symfony\Component\DependencyInjection\Exception\InvalidArgumentException
     */
    public function testLoadInvalidFile($file)
    {
        $loader = new YamlFileLoader(new ContainerBuilder(), new FileLocator(self::$fixturesPath.'/yaml'));

        $loader->load($file.'.yml');
    }

    public function provideInvalidFiles()
    {
        return array(
            array('bad_parameters'),
            array('bad_imports'),
            array('bad_import'),
            array('bad_services'),
            array('bad_service'),
            array('bad_calls'),
            array('bad_format'),
        );
    }

    public function testLoadParameters()
    {
        $container = new ContainerBuilder();
        $loader = new YamlFileLoader($container, new FileLocator(self::$fixturesPath.'/yaml'));
        $loader->load('services2.yml');
        $this->assertEquals(array('foo' => 'bar', 'mixedcase' => array('MixedCaseKey' => 'value'), 'values' => array(true, false, 0, 1000.3), 'bar' => 'foo', 'escape' => '@escapeme', 'foo_bar' => new Reference('foo_bar')), $container->getParameterBag()->all(), '->load() converts YAML keys to lowercase');
    }

    public function testLoadImports()
    {
        $container = new ContainerBuilder();
        $resolver = new LoaderResolver(array(
            new IniFileLoader($container, new FileLocator(self::$fixturesPath.'/ini')),
            new XmlFileLoader($container, new FileLocator(self::$fixturesPath.'/xml')),
            new PhpFileLoader($container, new FileLocator(self::$fixturesPath.'/php')),
            $loader = new YamlFileLoader($container, new FileLocator(self::$fixturesPath.'/yaml')),
        ));
        $loader->setResolver($resolver);
        $loader->load('services4.yml');

        $actual = $container->getParameterBag()->all();
        $expected = array('foo' => 'bar', 'values' => array(true, false), 'bar' => '%foo%', 'escape' => '@escapeme', 'foo_bar' => new Reference('foo_bar'), 'mixedcase' => array('MixedCaseKey' => 'value'), 'imported_from_ini' => true, 'imported_from_xml' => true);
        $this->assertEquals(array_keys($expected), array_keys($actual), '->load() imports and merges imported files');

        // Bad import throws no exception due to ignore_errors value.
        $loader->load('services4_bad_import.yml');
    }

    /**
     * @group legacy
     */
    public function testLegacyLoadServices()
    {
        $container = new ContainerBuilder();
        $loader = new YamlFileLoader($container, new FileLocator(self::$fixturesPath.'/yaml'));
        $loader->load('legacy-services6.yml');
        $services = $container->getDefinitions();
        $this->assertEquals('FooClass', $services['constructor']->getClass());
        $this->assertEquals('getInstance', $services['constructor']->getFactoryMethod());
        $this->assertEquals('BazClass', $services['factory_service']->getClass());
        $this->assertEquals('baz_factory', $services['factory_service']->getFactoryService());
        $this->assertEquals('getInstance', $services['factory_service']->getFactoryMethod());
        $this->assertEquals('container', $services['scope.container']->getScope());
        $this->assertEquals('custom', $services['scope.custom']->getScope());
        $this->assertEquals('prototype', $services['scope.prototype']->getScope());
        $this->assertTrue($services['request']->isSynthetic(), '->load() parses the synthetic flag');
        $this->assertTrue($services['request']->isSynchronized(), '->load() parses the synchronized flag');
        $this->assertTrue($services['request']->isLazy(), '->load() parses the lazy flag');
        $this->assertNull($services['request']->getDecoratedService());
    }

    public function testLoadServices()
    {
        $container = new ContainerBuilder();
        $loader = new YamlFileLoader($container, new FileLocator(self::$fixturesPath.'/yaml'));
        $loader->load('services6.yml');
        $services = $container->getDefinitions();
        $this->assertTrue(isset($services['foo']), '->load() parses service elements');
        $this->assertFalse($services['not_shared']->isShared(), '->load() parses the shared flag');
        $this->assertInstanceOf('Symfony\\Component\\DependencyInjection\\Definition', $services['foo'], '->load() converts service element to Definition instances');
        $this->assertEquals('FooClass', $services['foo']->getClass(), '->load() parses the class attribute');
        $this->assertEquals('%path%/foo.php', $services['file']->getFile(), '->load() parses the file tag');
        $this->assertEquals(array('foo', new Reference('foo'), array(true, false)), $services['arguments']->getArguments(), '->load() parses the argument tags');
        $this->assertEquals('sc_configure', $services['configurator1']->getConfigurator(), '->load() parses the configurator tag');
        $this->assertEquals(array(new Reference('baz'), 'configure'), $services['configurator2']->getConfigurator(), '->load() parses the configurator tag');
        $this->assertEquals(array('BazClass', 'configureStatic'), $services['configurator3']->getConfigurator(), '->load() parses the configurator tag');
        $this->assertEquals(array(array('setBar', array()), array('setBar', array()), array('setBar', array(new Expression('service("foo").foo() ~ (container.hasParameter("foo") ? parameter("foo") : "default")')))), $services['method_call1']->getMethodCalls(), '->load() parses the method_call tag');
        $this->assertEquals(array(array('setBar', array('foo', new Reference('foo'), array(true, false)))), $services['method_call2']->getMethodCalls(), '->load() parses the method_call tag');
        $this->assertEquals('factory', $services['new_factory1']->getFactory(), '->load() parses the factory tag');
        $this->assertEquals(array(new Reference('baz'), 'getClass'), $services['new_factory2']->getFactory(), '->load() parses the factory tag');
        $this->assertEquals(array('BazClass', 'getInstance'), $services['new_factory3']->getFactory(), '->load() parses the factory tag');

        $aliases = $container->getAliases();
        $this->assertTrue(isset($aliases['alias_for_foo']), '->load() parses aliases');
        $this->assertEquals('foo', (string) $aliases['alias_for_foo'], '->load() parses aliases');
        $this->assertTrue($aliases['alias_for_foo']->isPublic());
        $this->assertTrue(isset($aliases['another_alias_for_foo']));
        $this->assertEquals('foo', (string) $aliases['another_alias_for_foo']);
        $this->assertFalse($aliases['another_alias_for_foo']->isPublic());

        $this->assertEquals(array('decorated', null, 0), $services['decorator_service']->getDecoratedService());
        $this->assertEquals(array('decorated', 'decorated.pif-pouf', 0), $services['decorator_service_with_name']->getDecoratedService());
        $this->assertEquals(array('decorated', 'decorated.pif-pouf', 5), $services['decorator_service_with_name_and_priority']->getDecoratedService());
    }

    public function testLoadFactoryShortSyntax()
    {
        $container = new ContainerBuilder();
        $loader = new YamlFileLoader($container, new FileLocator(self::$fixturesPath.'/yaml'));
        $loader->load('services14.yml');
        $services = $container->getDefinitions();

        $this->assertEquals(array(new Reference('baz'), 'getClass'), $services['factory']->getFactory(), '->load() parses the factory tag with service:method');
        $this->assertEquals(array('FooBacFactory', 'createFooBar'), $services['factory_with_static_call']->getFactory(), '->load() parses the factory tag with Class::method');
    }

    public function testExtensions()
    {
        $container = new ContainerBuilder();
        $container->registerExtension(new \ProjectExtension());
        $loader = new YamlFileLoader($container, new FileLocator(self::$fixturesPath.'/yaml'));
        $loader->load('services10.yml');
        $container->compile();
        $services = $container->getDefinitions();
        $parameters = $container->getParameterBag()->all();

        $this->assertTrue(isset($services['project.service.bar']), '->load() parses extension elements');
        $this->assertTrue(isset($parameters['project.parameter.bar']), '->load() parses extension elements');

        $this->assertEquals('BAR', $services['project.service.foo']->getClass(), '->load() parses extension elements');
        $this->assertEquals('BAR', $parameters['project.parameter.foo'], '->load() parses extension elements');

        try {
            $loader->load('services11.yml');
            $this->fail('->load() throws an InvalidArgumentException if the tag is not valid');
        } catch (\Exception $e) {
            $this->assertInstanceOf('\InvalidArgumentException', $e, '->load() throws an InvalidArgumentException if the tag is not valid');
            $this->assertStringStartsWith('There is no extension able to load the configuration for "foobarfoobar" (in', $e->getMessage(), '->load() throws an InvalidArgumentException if the tag is not valid');
        }
    }

    public function testSupports()
    {
        $loader = new YamlFileLoader(new ContainerBuilder(), new FileLocator());

        $this->assertTrue($loader->supports('foo.yml'), '->supports() returns true if the resource is loadable');
        $this->assertTrue($loader->supports('foo.yaml'), '->supports() returns true if the resource is loadable');
        $this->assertFalse($loader->supports('foo.foo'), '->supports() returns true if the resource is loadable');
    }

    public function testNonArrayTagsThrowsException()
    {
        $loader = new YamlFileLoader(new ContainerBuilder(), new FileLocator(self::$fixturesPath.'/yaml'));
        try {
            $loader->load('badtag1.yml');
            $this->fail('->load() should throw an exception when the tags key of a service is not an array');
        } catch (\Exception $e) {
            $this->assertInstanceOf('Symfony\Component\DependencyInjection\Exception\InvalidArgumentException', $e, '->load() throws an InvalidArgumentException if the tags key is not an array');
            $this->assertStringStartsWith('Parameter "tags" must be an array for service', $e->getMessage(), '->load() throws an InvalidArgumentException if the tags key is not an array');
        }
    }

    /**
     * @expectedException \Symfony\Component\DependencyInjection\Exception\InvalidArgumentException
     * @expectedExceptionMessage A "tags" entry must be an array for service
     */
    public function testNonArrayTagThrowsException()
    {
        $loader = new YamlFileLoader(new ContainerBuilder(), new FileLocator(self::$fixturesPath.'/yaml'));
        $loader->load('badtag4.yml');
    }

    public function testTagWithoutNameThrowsException()
    {
        $loader = new YamlFileLoader(new ContainerBuilder(), new FileLocator(self::$fixturesPath.'/yaml'));
        try {
            $loader->load('badtag2.yml');
            $this->fail('->load() should throw an exception when a tag is missing the name key');
        } catch (\Exception $e) {
            $this->assertInstanceOf('Symfony\Component\DependencyInjection\Exception\InvalidArgumentException', $e, '->load() throws an InvalidArgumentException if a tag is missing the name key');
            $this->assertStringStartsWith('A "tags" entry is missing a "name" key for service ', $e->getMessage(), '->load() throws an InvalidArgumentException if a tag is missing the name key');
        }
    }

    public function testTagWithAttributeArrayThrowsException()
    {
        $loader = new YamlFileLoader(new ContainerBuilder(), new FileLocator(self::$fixturesPath.'/yaml'));
        try {
            $loader->load('badtag3.yml');
            $this->fail('->load() should throw an exception when a tag-attribute is not a scalar');
        } catch (\Exception $e) {
            $this->assertInstanceOf('Symfony\Component\DependencyInjection\Exception\InvalidArgumentException', $e, '->load() throws an InvalidArgumentException if a tag-attribute is not a scalar');
            $this->assertStringStartsWith('A "tags" attribute must be of a scalar-type for service "foo_service", tag "foo", attribute "bar"', $e->getMessage(), '->load() throws an InvalidArgumentException if a tag-attribute is not a scalar');
        }
    }

    public function testLoadYamlOnlyWithKeys()
    {
        $container = new ContainerBuilder();
        $loader = new YamlFileLoader($container, new FileLocator(self::$fixturesPath.'/yaml'));
        $loader->load('services21.yml');

        $definition = $container->getDefinition('manager');
        $this->assertEquals(array(array('setLogger', array(new Reference('logger'))), array('setClass', array('User'))), $definition->getMethodCalls());
        $this->assertEquals(array(true), $definition->getArguments());
        $this->assertEquals(array('manager' => array(array('alias' => 'user'))), $definition->getTags());
    }

    /**
     * @expectedException \Symfony\Component\DependencyInjection\Exception\InvalidArgumentException
     * @expectedExceptionMessageRegExp /The tag name for service ".+" in .+ must be a non-empty string/
     */
    public function testTagWithEmptyNameThrowsException()
    {
        $loader = new YamlFileLoader(new ContainerBuilder(), new FileLocator(self::$fixturesPath.'/yaml'));
        $loader->load('tag_name_empty_string.yml');
    }

    /**
     * @expectedException \Symfony\Component\DependencyInjection\Exception\InvalidArgumentException
     * @expectedExceptionMessageREgExp /The tag name for service "\.+" must be a non-empty string/
     */
    public function testTagWithNonStringNameThrowsException()
    {
        $loader = new YamlFileLoader(new ContainerBuilder(), new FileLocator(self::$fixturesPath.'/yaml'));
        $loader->load('tag_name_no_string.yml');
    }

    /**
     * @expectedException \Symfony\Component\DependencyInjection\Exception\InvalidArgumentException
     */
    public function testTypesNotArray()
    {
        $loader = new YamlFileLoader(new ContainerBuilder(), new FileLocator(self::$fixturesPath.'/yaml'));
        $loader->load('bad_types1.yml');
    }

    /**
     * @expectedException \Symfony\Component\DependencyInjection\Exception\InvalidArgumentException
     */
    public function testTypeNotString()
    {
        $loader = new YamlFileLoader(new ContainerBuilder(), new FileLocator(self::$fixturesPath.'/yaml'));
        $loader->load('bad_types2.yml');
    }

    public function testTypes()
    {
        $container = new ContainerBuilder();
        $loader = new YamlFileLoader($container, new FileLocator(self::$fixturesPath.'/yaml'));
        $loader->load('services22.yml');

        $this->assertEquals(array('Foo', 'Bar'), $container->getDefinition('foo_service')->getAutowiringTypes());
        $this->assertEquals(array('Foo'), $container->getDefinition('baz_service')->getAutowiringTypes());
    }

    public function testAutowire()
    {
        $container = new ContainerBuilder();
        $loader = new YamlFileLoader($container, new FileLocator(self::$fixturesPath.'/yaml'));
        $loader->load('services23.yml');

        $this->assertTrue($container->getDefinition('bar_service')->isAutowired());
    }

    /**
     * @expectedException \Symfony\Component\DependencyInjection\Exception\InvalidArgumentException
     * @expectedExceptionMessage The value of the "decorates" option for the "bar" service must be the id of the service without the "@" prefix (replace "@foo" with "foo").
     */
    public function testDecoratedServicesWithWrongSyntaxThrowsException()
    {
        $loader = new YamlFileLoader(new ContainerBuilder(), new FileLocator(self::$fixturesPath.'/yaml'));
        $loader->load('bad_decorates.yml');
    }
}
