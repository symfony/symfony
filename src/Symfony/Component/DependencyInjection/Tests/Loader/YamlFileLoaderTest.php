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
use Symfony\Component\Config\FileLocator;
use Symfony\Component\Config\Loader\LoaderResolver;
use Symfony\Component\Config\Resource\FileResource;
use Symfony\Component\Config\Resource\GlobResource;
use Symfony\Component\DependencyInjection\Argument\IteratorArgument;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\IniFileLoader;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\DependencyInjection\Tests\Fixtures\Bar;
use Symfony\Component\DependencyInjection\Tests\Fixtures\BarInterface;
use Symfony\Component\DependencyInjection\Tests\Fixtures\CaseSensitiveClass;
use Symfony\Component\DependencyInjection\Tests\Fixtures\NamedArgumentsDummy;
use Symfony\Component\DependencyInjection\Tests\Fixtures\Prototype;
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

    /**
     * @expectedException \Symfony\Component\DependencyInjection\Exception\InvalidArgumentException
     * @expectedExceptionMessageRegExp /The file ".+" does not exist./
     */
    public function testLoadUnExistFile()
    {
        $loader = new YamlFileLoader(new ContainerBuilder(), new FileLocator(self::$fixturesPath.'/ini'));
        $r = new \ReflectionObject($loader);
        $m = $r->getMethod('loadFile');
        $m->setAccessible(true);

        $m->invoke($loader, 'foo.yml');
    }

    /**
     * @expectedException \Symfony\Component\DependencyInjection\Exception\InvalidArgumentException
     * @expectedExceptionMessageRegExp /The file ".+" does not contain valid YAML./
     */
    public function testLoadInvalidYamlFile()
    {
        $path = self::$fixturesPath.'/ini';
        $loader = new YamlFileLoader(new ContainerBuilder(), new FileLocator($path));
        $r = new \ReflectionObject($loader);
        $m = $r->getMethod('loadFile');
        $m->setAccessible(true);

        $m->invoke($loader, $path.'/parameters.ini');
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
        return [
            ['bad_parameters'],
            ['bad_imports'],
            ['bad_import'],
            ['bad_services'],
            ['bad_service'],
            ['bad_calls'],
            ['bad_format'],
            ['nonvalid1'],
            ['nonvalid2'],
        ];
    }

    public function testLoadParameters()
    {
        $container = new ContainerBuilder();
        $loader = new YamlFileLoader($container, new FileLocator(self::$fixturesPath.'/yaml'));
        $loader->load('services2.yml');
        $this->assertEquals(['foo' => 'bar', 'mixedcase' => ['MixedCaseKey' => 'value'], 'values' => [true, false, 0, 1000.3, PHP_INT_MAX], 'bar' => 'foo', 'escape' => '@escapeme', 'foo_bar' => new Reference('foo_bar')], $container->getParameterBag()->all(), '->load() converts YAML keys to lowercase');
    }

    public function testLoadImports()
    {
        $container = new ContainerBuilder();
        $resolver = new LoaderResolver([
            new IniFileLoader($container, new FileLocator(self::$fixturesPath.'/ini')),
            new XmlFileLoader($container, new FileLocator(self::$fixturesPath.'/xml')),
            new PhpFileLoader($container, new FileLocator(self::$fixturesPath.'/php')),
            $loader = new YamlFileLoader($container, new FileLocator(self::$fixturesPath.'/yaml')),
        ]);
        $loader->setResolver($resolver);
        $loader->load('services4.yml');

        $actual = $container->getParameterBag()->all();
        $expected = [
            'foo' => 'bar',
            'values' => [true, false, PHP_INT_MAX],
            'bar' => '%foo%',
            'escape' => '@escapeme',
            'foo_bar' => new Reference('foo_bar'),
            'mixedcase' => ['MixedCaseKey' => 'value'],
            'imported_from_ini' => true,
            'imported_from_xml' => true,
            'with_wrong_ext' => 'from yaml',
        ];
        $this->assertEquals(array_keys($expected), array_keys($actual), '->load() imports and merges imported files');
        $this->assertTrue($actual['imported_from_ini']);

        // Bad import throws no exception due to ignore_errors value.
        $loader->load('services4_bad_import.yml');
    }

    public function testLoadServices()
    {
        $container = new ContainerBuilder();
        $loader = new YamlFileLoader($container, new FileLocator(self::$fixturesPath.'/yaml'));
        $loader->load('services6.yml');
        $services = $container->getDefinitions();
        $this->assertArrayHasKey('foo', $services, '->load() parses service elements');
        $this->assertFalse($services['not_shared']->isShared(), '->load() parses the shared flag');
        $this->assertInstanceOf('Symfony\\Component\\DependencyInjection\\Definition', $services['foo'], '->load() converts service element to Definition instances');
        $this->assertEquals('FooClass', $services['foo']->getClass(), '->load() parses the class attribute');
        $this->assertEquals('%path%/foo.php', $services['file']->getFile(), '->load() parses the file tag');
        $this->assertEquals(['foo', new Reference('foo'), [true, false]], $services['arguments']->getArguments(), '->load() parses the argument tags');
        $this->assertEquals('sc_configure', $services['configurator1']->getConfigurator(), '->load() parses the configurator tag');
        $this->assertEquals([new Reference('baz'), 'configure'], $services['configurator2']->getConfigurator(), '->load() parses the configurator tag');
        $this->assertEquals(['BazClass', 'configureStatic'], $services['configurator3']->getConfigurator(), '->load() parses the configurator tag');
        $this->assertEquals([['setBar', []], ['setBar', []], ['setBar', [new Expression('service("foo").foo() ~ (container.hasParameter("foo") ? parameter("foo") : "default")')]]], $services['method_call1']->getMethodCalls(), '->load() parses the method_call tag');
        $this->assertEquals([['setBar', ['foo', new Reference('foo'), [true, false]]]], $services['method_call2']->getMethodCalls(), '->load() parses the method_call tag');
        $this->assertEquals('factory', $services['new_factory1']->getFactory(), '->load() parses the factory tag');
        $this->assertEquals([new Reference('baz'), 'getClass'], $services['new_factory2']->getFactory(), '->load() parses the factory tag');
        $this->assertEquals(['BazClass', 'getInstance'], $services['new_factory3']->getFactory(), '->load() parses the factory tag');
        $this->assertSame([null, 'getInstance'], $services['new_factory4']->getFactory(), '->load() accepts factory tag without class');
        $this->assertEquals(['foo', new Reference('baz')], $services['Acme\WithShortCutArgs']->getArguments(), '->load() parses short service definition');

        $aliases = $container->getAliases();
        $this->assertArrayHasKey('alias_for_foo', $aliases, '->load() parses aliases');
        $this->assertEquals('foo', (string) $aliases['alias_for_foo'], '->load() parses aliases');
        $this->assertTrue($aliases['alias_for_foo']->isPublic());
        $this->assertArrayHasKey('another_alias_for_foo', $aliases);
        $this->assertEquals('foo', (string) $aliases['another_alias_for_foo']);
        $this->assertFalse($aliases['another_alias_for_foo']->isPublic());
        $this->assertTrue(isset($aliases['another_third_alias_for_foo']));
        $this->assertEquals('foo', (string) $aliases['another_third_alias_for_foo']);
        $this->assertTrue($aliases['another_third_alias_for_foo']->isPublic());

        $this->assertEquals(['decorated', null, 0], $services['decorator_service']->getDecoratedService());
        $this->assertEquals(['decorated', 'decorated.pif-pouf', 0], $services['decorator_service_with_name']->getDecoratedService());
        $this->assertEquals(['decorated', 'decorated.pif-pouf', 5], $services['decorator_service_with_name_and_priority']->getDecoratedService());
    }

    public function testLoadFactoryShortSyntax()
    {
        $container = new ContainerBuilder();
        $loader = new YamlFileLoader($container, new FileLocator(self::$fixturesPath.'/yaml'));
        $loader->load('services14.yml');
        $services = $container->getDefinitions();

        $this->assertEquals([new Reference('baz'), 'getClass'], $services['factory']->getFactory(), '->load() parses the factory tag with service:method');
        $this->assertEquals(['FooBacFactory', 'createFooBar'], $services['factory_with_static_call']->getFactory(), '->load() parses the factory tag with Class::method');
    }

    public function testLoadConfiguratorShortSyntax()
    {
        $container = new ContainerBuilder();
        $loader = new YamlFileLoader($container, new FileLocator(self::$fixturesPath.'/yaml'));
        $loader->load('services_configurator_short_syntax.yml');
        $services = $container->getDefinitions();

        $this->assertEquals([new Reference('foo_bar_configurator'), 'configure'], $services['foo_bar']->getConfigurator(), '->load() parses the configurator tag with service:method');
        $this->assertEquals(['FooBarConfigurator', 'configureFooBar'], $services['foo_bar_with_static_call']->getConfigurator(), '->load() parses the configurator tag with Class::method');
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

        $this->assertArrayHasKey('project.service.bar', $services, '->load() parses extension elements');
        $this->assertArrayHasKey('project.parameter.bar', $parameters, '->load() parses extension elements');

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

    public function testExtensionWithNullConfig()
    {
        $container = new ContainerBuilder();
        $container->registerExtension(new \ProjectExtension());
        $loader = new YamlFileLoader($container, new FileLocator(self::$fixturesPath.'/yaml'));
        $loader->load('null_config.yml');
        $container->compile();

        $this->assertSame([null], $container->getParameter('project.configs'));
    }

    public function testSupports()
    {
        $loader = new YamlFileLoader(new ContainerBuilder(), new FileLocator());

        $this->assertTrue($loader->supports('foo.yml'), '->supports() returns true if the resource is loadable');
        $this->assertTrue($loader->supports('foo.yaml'), '->supports() returns true if the resource is loadable');
        $this->assertFalse($loader->supports('foo.foo'), '->supports() returns false if the resource is not loadable');
        $this->assertTrue($loader->supports('with_wrong_ext.xml', 'yml'), '->supports() returns true if the resource with forced type is loadable');
        $this->assertTrue($loader->supports('with_wrong_ext.xml', 'yaml'), '->supports() returns true if the resource with forced type is loadable');
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

    public function testNameOnlyTagsAreAllowedAsString()
    {
        $container = new ContainerBuilder();
        $loader = new YamlFileLoader($container, new FileLocator(self::$fixturesPath.'/yaml'));
        $loader->load('tag_name_only.yml');

        $this->assertCount(1, $container->getDefinition('foo_service')->getTag('foo'));
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
        $this->assertEquals([['setLogger', [new Reference('logger')]], ['setClass', ['User']]], $definition->getMethodCalls());
        $this->assertEquals([true], $definition->getArguments());
        $this->assertEquals(['manager' => [['alias' => 'user']]], $definition->getTags());
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

    /**
     * @group legacy
     */
    public function testTypes()
    {
        $container = new ContainerBuilder();
        $loader = new YamlFileLoader($container, new FileLocator(self::$fixturesPath.'/yaml'));
        $loader->load('services22.yml');

        $this->assertEquals(['Foo', 'Bar'], $container->getDefinition('foo_service')->getAutowiringTypes());
        $this->assertEquals(['Foo'], $container->getDefinition('baz_service')->getAutowiringTypes());
    }

    public function testParsesIteratorArgument()
    {
        $container = new ContainerBuilder();
        $loader = new YamlFileLoader($container, new FileLocator(self::$fixturesPath.'/yaml'));
        $loader->load('services9.yml');

        $lazyDefinition = $container->getDefinition('lazy_context');

        $this->assertEquals([new IteratorArgument(['k1' => new Reference('foo.baz'), 'k2' => new Reference('service_container')]), new IteratorArgument([])], $lazyDefinition->getArguments(), '->load() parses lazy arguments');
    }

    public function testAutowire()
    {
        $container = new ContainerBuilder();
        $loader = new YamlFileLoader($container, new FileLocator(self::$fixturesPath.'/yaml'));
        $loader->load('services23.yml');

        $this->assertTrue($container->getDefinition('bar_service')->isAutowired());
    }

    public function testClassFromId()
    {
        $container = new ContainerBuilder();
        $loader = new YamlFileLoader($container, new FileLocator(self::$fixturesPath.'/yaml'));
        $loader->load('class_from_id.yml');
        $container->compile();

        $this->assertEquals(CaseSensitiveClass::class, $container->getDefinition(CaseSensitiveClass::class)->getClass());
    }

    public function testPrototype()
    {
        $container = new ContainerBuilder();
        $loader = new YamlFileLoader($container, new FileLocator(self::$fixturesPath.'/yaml'));
        $loader->load('services_prototype.yml');

        $ids = array_keys($container->getDefinitions());
        sort($ids);
        $this->assertSame([Prototype\Foo::class, Prototype\Sub\Bar::class, 'service_container'], $ids);

        $resources = $container->getResources();

        $fixturesDir = \dirname(__DIR__).\DIRECTORY_SEPARATOR.'Fixtures'.\DIRECTORY_SEPARATOR;
        $resources = array_map('strval', $resources);
        $this->assertContains((string) (new FileResource($fixturesDir.'yaml'.\DIRECTORY_SEPARATOR.'services_prototype.yml')), $resources);
        $this->assertContains((string) (new GlobResource($fixturesDir.'Prototype', '', true)), $resources);
        $this->assertContains('reflection.Symfony\Component\DependencyInjection\Tests\Fixtures\Prototype\Foo', $resources);
        $this->assertContains('reflection.Symfony\Component\DependencyInjection\Tests\Fixtures\Prototype\Sub\Bar', $resources);
    }

    public function testPrototypeWithNamespace()
    {
        $container = new ContainerBuilder();
        $loader = new YamlFileLoader($container, new FileLocator(self::$fixturesPath.'/yaml'));
        $loader->load('services_prototype_namespace.yml');

        $ids = array_keys($container->getDefinitions());
        sort($ids);

        $this->assertSame([
            Prototype\OtherDir\Component1\Dir1\Service1::class,
            Prototype\OtherDir\Component1\Dir2\Service2::class,
            Prototype\OtherDir\Component2\Dir1\Service4::class,
            Prototype\OtherDir\Component2\Dir2\Service5::class,
            'service_container',
        ], $ids);

        $this->assertTrue($container->getDefinition(Prototype\OtherDir\Component1\Dir1\Service1::class)->hasTag('foo'));
        $this->assertTrue($container->getDefinition(Prototype\OtherDir\Component2\Dir1\Service4::class)->hasTag('foo'));
        $this->assertFalse($container->getDefinition(Prototype\OtherDir\Component1\Dir1\Service1::class)->hasTag('bar'));
        $this->assertFalse($container->getDefinition(Prototype\OtherDir\Component2\Dir1\Service4::class)->hasTag('bar'));

        $this->assertTrue($container->getDefinition(Prototype\OtherDir\Component1\Dir2\Service2::class)->hasTag('bar'));
        $this->assertTrue($container->getDefinition(Prototype\OtherDir\Component2\Dir2\Service5::class)->hasTag('bar'));
        $this->assertFalse($container->getDefinition(Prototype\OtherDir\Component1\Dir2\Service2::class)->hasTag('foo'));
        $this->assertFalse($container->getDefinition(Prototype\OtherDir\Component2\Dir2\Service5::class)->hasTag('foo'));
    }

    /**
     * @expectedException \Symfony\Component\DependencyInjection\Exception\InvalidArgumentException
     * @expectedExceptionMessageRegExp /A "resource" attribute must be set when the "namespace" attribute is set for service ".+" in .+/
     */
    public function testPrototypeWithNamespaceAndNoResource()
    {
        $container = new ContainerBuilder();
        $loader = new YamlFileLoader($container, new FileLocator(self::$fixturesPath.'/yaml'));
        $loader->load('services_prototype_namespace_without_resource.yml');
    }

    public function testDefaults()
    {
        $container = new ContainerBuilder();
        $loader = new YamlFileLoader($container, new FileLocator(self::$fixturesPath.'/yaml'));
        $loader->load('services28.yml');

        $this->assertFalse($container->getDefinition('with_defaults')->isPublic());
        $this->assertSame(['foo' => [[]]], $container->getDefinition('with_defaults')->getTags());
        $this->assertTrue($container->getDefinition('with_defaults')->isAutowired());
        $this->assertArrayNotHasKey('public', $container->getDefinition('with_defaults')->getChanges());
        $this->assertArrayNotHasKey('autowire', $container->getDefinition('with_defaults')->getChanges());

        $this->assertFalse($container->getAlias('with_defaults_aliased')->isPublic());
        $this->assertFalse($container->getAlias('with_defaults_aliased_short')->isPublic());

        $this->assertFalse($container->getDefinition('Acme\WithShortCutArgs')->isPublic());
        $this->assertSame(['foo' => [[]]], $container->getDefinition('Acme\WithShortCutArgs')->getTags());
        $this->assertTrue($container->getDefinition('Acme\WithShortCutArgs')->isAutowired());

        $container->compile();

        $this->assertTrue($container->getDefinition('with_null')->isPublic());
        $this->assertTrue($container->getDefinition('no_defaults')->isPublic());

        // foo tag is inherited from defaults
        $this->assertSame(['foo' => [[]]], $container->getDefinition('with_null')->getTags());
        $this->assertSame(['foo' => [[]]], $container->getDefinition('no_defaults')->getTags());

        $this->assertTrue($container->getDefinition('with_null')->isAutowired());
        $this->assertFalse($container->getDefinition('no_defaults')->isAutowired());

        $this->assertTrue($container->getDefinition('child_def')->isPublic());
        $this->assertSame(['foo' => [[]]], $container->getDefinition('child_def')->getTags());
        $this->assertFalse($container->getDefinition('child_def')->isAutowired());
    }

    public function testNamedArguments()
    {
        $container = new ContainerBuilder();
        $loader = new YamlFileLoader($container, new FileLocator(self::$fixturesPath.'/yaml'));
        $loader->load('services_named_args.yml');

        $this->assertEquals([null, '$apiKey' => 'ABCD'], $container->getDefinition(NamedArgumentsDummy::class)->getArguments());
        $this->assertEquals(['$apiKey' => 'ABCD', CaseSensitiveClass::class => null], $container->getDefinition('another_one')->getArguments());

        $container->compile();

        $this->assertEquals([null, 'ABCD'], $container->getDefinition(NamedArgumentsDummy::class)->getArguments());
        $this->assertEquals([null, 'ABCD'], $container->getDefinition('another_one')->getArguments());
        $this->assertEquals([['setApiKey', ['123']]], $container->getDefinition('another_one')->getMethodCalls());
    }

    public function testInstanceof()
    {
        $container = new ContainerBuilder();
        $loader = new YamlFileLoader($container, new FileLocator(self::$fixturesPath.'/yaml'));
        $loader->load('services_instanceof.yml');
        $container->compile();

        $definition = $container->getDefinition(Bar::class);
        $this->assertTrue($definition->isAutowired());
        $this->assertTrue($definition->isLazy());
        $this->assertSame(['foo' => [[]], 'bar' => [[]]], $definition->getTags());
    }

    /**
     * @expectedException \Symfony\Component\DependencyInjection\Exception\InvalidArgumentException
     * @expectedExceptionMessage The service "child_service" cannot use the "parent" option in the same file where "_instanceof" configuration is defined as using both is not supported. Move your child definitions to a separate file.
     */
    public function testInstanceOfAndChildDefinitionNotAllowed()
    {
        $container = new ContainerBuilder();
        $loader = new YamlFileLoader($container, new FileLocator(self::$fixturesPath.'/yaml'));
        $loader->load('services_instanceof_with_parent.yml');
        $container->compile();
    }

    /**
     * @expectedException \Symfony\Component\DependencyInjection\Exception\InvalidArgumentException
     * @expectedExceptionMessage The service "child_service" cannot have a "parent" and also have "autoconfigure". Try setting "autoconfigure: false" for the service.
     */
    public function testAutoConfigureAndChildDefinitionNotAllowed()
    {
        $container = new ContainerBuilder();
        $loader = new YamlFileLoader($container, new FileLocator(self::$fixturesPath.'/yaml'));
        $loader->load('services_autoconfigure_with_parent.yml');
        $container->compile();
    }

    /**
     * @expectedException \Symfony\Component\DependencyInjection\Exception\InvalidArgumentException
     * @expectedExceptionMessage Attribute "autowire" on service "child_service" cannot be inherited from "_defaults" when a "parent" is set. Move your child definitions to a separate file or define this attribute explicitly.
     */
    public function testDefaultsAndChildDefinitionNotAllowed()
    {
        $container = new ContainerBuilder();
        $loader = new YamlFileLoader($container, new FileLocator(self::$fixturesPath.'/yaml'));
        $loader->load('services_defaults_with_parent.yml');
        $container->compile();
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

    /**
     * @expectedException \Symfony\Component\DependencyInjection\Exception\InvalidArgumentException
     * @expectedExceptionMessageRegExp /Parameter "tags" must be an array for service "Foo\\Bar" in .+services31_invalid_tags\.yml\. Check your YAML syntax./
     */
    public function testInvalidTagsWithDefaults()
    {
        $loader = new YamlFileLoader(new ContainerBuilder(), new FileLocator(self::$fixturesPath.'/yaml'));
        $loader->load('services31_invalid_tags.yml');
    }

    /**
     * @group legacy
     * @expectedDeprecation Service names that start with an underscore are deprecated since Symfony 3.3 and will be reserved in 4.0. Rename the "_foo" service or define it in XML instead.
     */
    public function testUnderscoreServiceId()
    {
        $container = new ContainerBuilder();
        $loader = new YamlFileLoader($container, new FileLocator(self::$fixturesPath.'/yaml'));
        $loader->load('services_underscore.yml');
    }

    public function testAnonymousServices()
    {
        $container = new ContainerBuilder();
        $loader = new YamlFileLoader($container, new FileLocator(self::$fixturesPath.'/yaml'));
        $loader->load('anonymous_services.yml');

        $definition = $container->getDefinition('Foo');
        $this->assertTrue($definition->isAutowired());

        // Anonymous service in an argument
        $args = $definition->getArguments();
        $this->assertCount(1, $args);
        $this->assertInstanceOf(Reference::class, $args[0]);
        $this->assertTrue($container->has((string) $args[0]));
        $this->assertRegExp('/^\d+_Bar~[._A-Za-z0-9]{7}$/', (string) $args[0]);

        $anonymous = $container->getDefinition((string) $args[0]);
        $this->assertEquals('Bar', $anonymous->getClass());
        $this->assertFalse($anonymous->isPublic());
        $this->assertTrue($anonymous->isAutowired());

        // Anonymous service in a callable
        $factory = $definition->getFactory();
        $this->assertInternalType('array', $factory);
        $this->assertInstanceOf(Reference::class, $factory[0]);
        $this->assertTrue($container->has((string) $factory[0]));
        $this->assertRegExp('/^\d+_Quz~[._A-Za-z0-9]{7}$/', (string) $factory[0]);
        $this->assertEquals('constructFoo', $factory[1]);

        $anonymous = $container->getDefinition((string) $factory[0]);
        $this->assertEquals('Quz', $anonymous->getClass());
        $this->assertFalse($anonymous->isPublic());
        $this->assertFalse($anonymous->isAutowired());
    }

    public function testAnonymousServicesInDifferentFilesWithSameNameDoNotConflict()
    {
        $container = new ContainerBuilder();

        $loader = new YamlFileLoader($container, new FileLocator(self::$fixturesPath.'/yaml/foo'));
        $loader->load('services.yml');

        $loader = new YamlFileLoader($container, new FileLocator(self::$fixturesPath.'/yaml/bar'));
        $loader->load('services.yml');

        $this->assertCount(5, $container->getDefinitions());
    }

    public function testAnonymousServicesInInstanceof()
    {
        $container = new ContainerBuilder();
        $loader = new YamlFileLoader($container, new FileLocator(self::$fixturesPath.'/yaml'));
        $loader->load('anonymous_services_in_instanceof.yml');

        $definition = $container->getDefinition('Dummy');

        $instanceof = $definition->getInstanceofConditionals();
        $this->assertCount(3, $instanceof);
        $this->assertArrayHasKey('DummyInterface', $instanceof);

        $args = $instanceof['DummyInterface']->getProperties();
        $this->assertCount(1, $args);
        $this->assertInstanceOf(Reference::class, $args['foo']);
        $this->assertTrue($container->has((string) $args['foo']));

        $anonymous = $container->getDefinition((string) $args['foo']);
        $this->assertEquals('Anonymous', $anonymous->getClass());
        $this->assertFalse($anonymous->isPublic());
        $this->assertEmpty($anonymous->getInstanceofConditionals());

        $this->assertFalse($container->has('Bar'));
    }

    /**
     * @expectedException \Symfony\Component\DependencyInjection\Exception\InvalidArgumentException
     * @expectedExceptionMessageRegExp /Creating an alias using the tag "!service" is not allowed in ".+anonymous_services_alias\.yml"\./
     */
    public function testAnonymousServicesWithAliases()
    {
        $container = new ContainerBuilder();
        $loader = new YamlFileLoader($container, new FileLocator(self::$fixturesPath.'/yaml'));
        $loader->load('anonymous_services_alias.yml');
    }

    /**
     * @expectedException \Symfony\Component\DependencyInjection\Exception\InvalidArgumentException
     * @expectedExceptionMessageRegExp /Using an anonymous service in a parameter is not allowed in ".+anonymous_services_in_parameters\.yml"\./
     */
    public function testAnonymousServicesInParameters()
    {
        $container = new ContainerBuilder();
        $loader = new YamlFileLoader($container, new FileLocator(self::$fixturesPath.'/yaml'));
        $loader->load('anonymous_services_in_parameters.yml');
    }

    public function testAutoConfigureInstanceof()
    {
        $container = new ContainerBuilder();
        $loader = new YamlFileLoader($container, new FileLocator(self::$fixturesPath.'/yaml'));
        $loader->load('services_autoconfigure.yml');

        $this->assertTrue($container->getDefinition('use_defaults_settings')->isAutoconfigured());
        $this->assertFalse($container->getDefinition('override_defaults_settings_to_false')->isAutoconfigured());
    }

    /**
     * @expectedException \Symfony\Component\DependencyInjection\Exception\InvalidArgumentException
     * @expectedExceptionMessageRegExp /Service "_defaults" key must be an array, "NULL" given in ".+bad_empty_defaults\.yml"\./
     */
    public function testEmptyDefaultsThrowsClearException()
    {
        $container = new ContainerBuilder();
        $loader = new YamlFileLoader($container, new FileLocator(self::$fixturesPath.'/yaml'));
        $loader->load('bad_empty_defaults.yml');
    }

    /**
     * @expectedException \Symfony\Component\DependencyInjection\Exception\InvalidArgumentException
     * @expectedExceptionMessageRegExp /Service "_instanceof" key must be an array, "NULL" given in ".+bad_empty_instanceof\.yml"\./
     */
    public function testEmptyInstanceofThrowsClearException()
    {
        $container = new ContainerBuilder();
        $loader = new YamlFileLoader($container, new FileLocator(self::$fixturesPath.'/yaml'));
        $loader->load('bad_empty_instanceof.yml');
    }

    public function testBindings()
    {
        $container = new ContainerBuilder();
        $loader = new YamlFileLoader($container, new FileLocator(self::$fixturesPath.'/yaml'));
        $loader->load('services_bindings.yml');
        $container->compile();

        $definition = $container->getDefinition('bar');
        $this->assertEquals([
            'NonExistent' => null,
            BarInterface::class => new Reference(Bar::class),
            '$foo' => [null],
            '$quz' => 'quz',
            '$factory' => 'factory',
        ], array_map(function ($v) { return $v->getValues()[0]; }, $definition->getBindings()));
        $this->assertEquals([
            'quz',
            null,
            new Reference(Bar::class),
            [null],
        ], $definition->getArguments());

        $definition = $container->getDefinition(Bar::class);
        $this->assertEquals([
            null,
            'factory',
        ], $definition->getArguments());
        $this->assertEquals([
            'NonExistent' => null,
            '$quz' => 'quz',
            '$factory' => 'factory',
        ], array_map(function ($v) { return $v->getValues()[0]; }, $definition->getBindings()));
    }
}
