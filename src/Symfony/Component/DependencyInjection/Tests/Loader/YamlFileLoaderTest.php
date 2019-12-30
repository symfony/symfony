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
use Symfony\Component\DependencyInjection\Argument\BoundArgument;
use Symfony\Component\DependencyInjection\Argument\IteratorArgument;
use Symfony\Component\DependencyInjection\Argument\ServiceLocatorArgument;
use Symfony\Component\DependencyInjection\Argument\TaggedIteratorArgument;
use Symfony\Component\DependencyInjection\Compiler\ResolveBindingsPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;
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

    public static function setUpBeforeClass(): void
    {
        self::$fixturesPath = realpath(__DIR__.'/../Fixtures/');
        require_once self::$fixturesPath.'/includes/foo.php';
        require_once self::$fixturesPath.'/includes/ProjectExtension.php';
    }

    public function testLoadUnExistFile()
    {
        $this->expectException('Symfony\Component\DependencyInjection\Exception\InvalidArgumentException');
        $this->expectExceptionMessageRegExp('/The file ".+" does not exist./');
        $loader = new YamlFileLoader(new ContainerBuilder(), new FileLocator(self::$fixturesPath.'/ini'));
        $r = new \ReflectionObject($loader);
        $m = $r->getMethod('loadFile');
        $m->setAccessible(true);

        $m->invoke($loader, 'foo.yml');
    }

    public function testLoadInvalidYamlFile()
    {
        $this->expectException('Symfony\Component\DependencyInjection\Exception\InvalidArgumentException');
        $this->expectExceptionMessageRegExp('/The file ".+" does not contain valid YAML./');
        $path = self::$fixturesPath.'/ini';
        $loader = new YamlFileLoader(new ContainerBuilder(), new FileLocator($path));
        $r = new \ReflectionObject($loader);
        $m = $r->getMethod('loadFile');
        $m->setAccessible(true);

        $m->invoke($loader, $path.'/parameters.ini');
    }

    /**
     * @dataProvider provideInvalidFiles
     */
    public function testLoadInvalidFile($file)
    {
        $this->expectException('Symfony\Component\DependencyInjection\Exception\InvalidArgumentException');
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

        // Bad import with nonexistent file throws no exception due to ignore_errors: not_found value.
        $loader->load('services4_bad_import_file_not_found.yml');

        try {
            $loader->load('services4_bad_import_with_errors.yml');
            $this->fail('->load() throws a LoaderLoadException if the imported yaml file does not exist');
        } catch (\Exception $e) {
            $this->assertInstanceOf('Symfony\\Component\\Config\\Exception\\LoaderLoadException', $e, '->load() throws a LoaderLoadException if the imported yaml file does not exist');
            $this->assertRegExp(sprintf('#^The file "%1$s" does not exist \(in: .+\) in %1$s \(which is being imported from ".+%2$s"\)\.$#', 'foo_fake\.yml', 'services4_bad_import_with_errors\.yml'), $e->getMessage(), '->load() throws a LoaderLoadException if the imported yaml file does not exist');

            $e = $e->getPrevious();
            $this->assertInstanceOf('Symfony\\Component\\Config\\Exception\\FileLocatorFileNotFoundException', $e, '->load() throws a FileLocatorFileNotFoundException if the imported yaml file does not exist');
            $this->assertRegExp(sprintf('#^The file "%s" does not exist \(in: .+\)\.$#', 'foo_fake\.yml'), $e->getMessage(), '->load() throws a FileLocatorFileNotFoundException if the imported yaml file does not exist');
        }

        try {
            $loader->load('services4_bad_import_nonvalid.yml');
            $this->fail('->load() throws a LoaderLoadException if the tag in the imported yaml file is not valid');
        } catch (\Exception $e) {
            $this->assertInstanceOf('Symfony\\Component\\Config\\Exception\\LoaderLoadException', $e, '->load() throws a LoaderLoadException if the tag in the imported yaml file is not valid');
            $this->assertRegExp(sprintf('#^The service file ".+%1$s" is not valid\. It should contain an array\. Check your YAML syntax in .+%1$s \(which is being imported from ".+%2$s"\)\.$#', 'nonvalid2\.yml', 'services4_bad_import_nonvalid.yml'), $e->getMessage(), '->load() throws a LoaderLoadException if the tag in the imported yaml file is not valid');

            $e = $e->getPrevious();
            $this->assertInstanceOf('Symfony\\Component\\DependencyInjection\\Exception\\InvalidArgumentException', $e, '->load() throws an InvalidArgumentException if the tag in the imported yaml file is not valid');
            $this->assertRegExp(sprintf('#^The service file ".+%s" is not valid\. It should contain an array\. Check your YAML syntax\.$#', 'nonvalid2\.yml'), $e->getMessage(), '->load() throws an InvalidArgumentException if the tag in the imported yaml file is not valid');
        }
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
        $this->assertEquals([new Reference('baz'), '__invoke'], $services['new_factory5']->getFactory(), '->load() accepts service reference as invokable factory');
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
        $this->assertEquals(['decorated', 'decorated.pif-pouf', 5, ContainerInterface::IGNORE_ON_INVALID_REFERENCE], $services['decorator_service_with_name_and_priority_and_on_invalid']->getDecoratedService());
    }

    public function testDeprecatedAliases()
    {
        $container = new ContainerBuilder();
        $loader = new YamlFileLoader($container, new FileLocator(self::$fixturesPath.'/yaml'));
        $loader->load('deprecated_alias_definitions.yml');

        $this->assertTrue($container->getAlias('alias_for_foobar')->isDeprecated());
        $message = 'The "alias_for_foobar" service alias is deprecated.';
        $this->assertSame($message, $container->getAlias('alias_for_foobar')->getDeprecationMessage('alias_for_foobar'));
    }

    public function testFactorySyntaxError()
    {
        $container = new ContainerBuilder();
        $loader = new YamlFileLoader($container, new FileLocator(self::$fixturesPath.'/yaml'));
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The value of the "factory" option for the "invalid_factory" service must be the id of the service without the "@" prefix (replace "@factory:method" with "factory:method"');
        $loader->load('bad_factory_syntax.yml');
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

    public function testTaggedArgumentsWithIndex()
    {
        $container = new ContainerBuilder();
        $loader = new YamlFileLoader($container, new FileLocator(self::$fixturesPath.'/yaml'));
        $loader->load('services_with_tagged_argument.yml');

        $this->assertCount(1, $container->getDefinition('foo_service')->getTag('foo'));
        $this->assertCount(1, $container->getDefinition('foo_service_tagged_iterator')->getArguments());
        $this->assertCount(1, $container->getDefinition('foo_service_tagged_locator')->getArguments());

        $taggedIterator = new TaggedIteratorArgument('foo', 'barfoo', 'foobar', false, 'getPriority');
        $this->assertEquals($taggedIterator, $container->getDefinition('foo_service_tagged_iterator')->getArgument(0));

        $taggedIterator = new TaggedIteratorArgument('foo', 'barfoo', 'foobar', true, 'getPriority');
        $this->assertEquals(new ServiceLocatorArgument($taggedIterator), $container->getDefinition('foo_service_tagged_locator')->getArgument(0));

        $taggedIterator = new TaggedIteratorArgument('foo', null, null, true);
        $this->assertEquals(new ServiceLocatorArgument($taggedIterator), $container->getDefinition('bar_service_tagged_locator')->getArgument(0));
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

    public function testTagWithEmptyNameThrowsException()
    {
        $this->expectException('Symfony\Component\DependencyInjection\Exception\InvalidArgumentException');
        $this->expectExceptionMessageRegExp('/The tag name for service ".+" in .+ must be a non-empty string/');
        $loader = new YamlFileLoader(new ContainerBuilder(), new FileLocator(self::$fixturesPath.'/yaml'));
        $loader->load('tag_name_empty_string.yml');
    }

    public function testTagWithNonStringNameThrowsException()
    {
        $this->expectException('Symfony\Component\DependencyInjection\Exception\InvalidArgumentException');
        $this->expectExceptionMessageRegExp('/The tag name for service ".+" in .+ must be a non-empty string/');
        $loader = new YamlFileLoader(new ContainerBuilder(), new FileLocator(self::$fixturesPath.'/yaml'));
        $loader->load('tag_name_no_string.yml');
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
        $this->assertContains((string) new FileResource($fixturesDir.'yaml'.\DIRECTORY_SEPARATOR.'services_prototype.yml'), $resources);

        $prototypeRealPath = realpath(__DIR__.\DIRECTORY_SEPARATOR.'..'.\DIRECTORY_SEPARATOR.'Fixtures'.\DIRECTORY_SEPARATOR.'Prototype');
        $globResource = new GlobResource(
            $fixturesDir.'Prototype',
            '',
            true,
            false, [
                str_replace(\DIRECTORY_SEPARATOR, '/', $prototypeRealPath.\DIRECTORY_SEPARATOR.'BadClasses') => true,
                str_replace(\DIRECTORY_SEPARATOR, '/', $prototypeRealPath.\DIRECTORY_SEPARATOR.'OtherDir') => true,
                str_replace(\DIRECTORY_SEPARATOR, '/', $prototypeRealPath.\DIRECTORY_SEPARATOR.'SinglyImplementedInterface') => true,
            ]
        );
        $this->assertContains((string) $globResource, $resources);
        $resources = array_map('strval', $resources);
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

    public function testPrototypeWithNamespaceAndNoResource()
    {
        $this->expectException('Symfony\Component\DependencyInjection\Exception\InvalidArgumentException');
        $this->expectExceptionMessageRegExp('/A "resource" attribute must be set when the "namespace" attribute is set for service ".+" in .+/');
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

    public function testInstanceOfAndChildDefinitionNotAllowed()
    {
        $this->expectException('Symfony\Component\DependencyInjection\Exception\InvalidArgumentException');
        $this->expectExceptionMessage('The service "child_service" cannot use the "parent" option in the same file where "_instanceof" configuration is defined as using both is not supported. Move your child definitions to a separate file.');
        $container = new ContainerBuilder();
        $loader = new YamlFileLoader($container, new FileLocator(self::$fixturesPath.'/yaml'));
        $loader->load('services_instanceof_with_parent.yml');
        $container->compile();
    }

    public function testAutoConfigureAndChildDefinitionNotAllowed()
    {
        $this->expectException('Symfony\Component\DependencyInjection\Exception\InvalidArgumentException');
        $this->expectExceptionMessage('The service "child_service" cannot have a "parent" and also have "autoconfigure". Try setting "autoconfigure: false" for the service.');
        $container = new ContainerBuilder();
        $loader = new YamlFileLoader($container, new FileLocator(self::$fixturesPath.'/yaml'));
        $loader->load('services_autoconfigure_with_parent.yml');
        $container->compile();
    }

    public function testDefaultsAndChildDefinitionNotAllowed()
    {
        $this->expectException('Symfony\Component\DependencyInjection\Exception\InvalidArgumentException');
        $this->expectExceptionMessage('Attribute "autowire" on service "child_service" cannot be inherited from "_defaults" when a "parent" is set. Move your child definitions to a separate file or define this attribute explicitly.');
        $container = new ContainerBuilder();
        $loader = new YamlFileLoader($container, new FileLocator(self::$fixturesPath.'/yaml'));
        $loader->load('services_defaults_with_parent.yml');
        $container->compile();
    }

    public function testChildDefinitionWithWrongSyntaxThrowsException()
    {
        $this->expectException('Symfony\Component\DependencyInjection\Exception\InvalidArgumentException');
        $this->expectExceptionMessage('The value of the "parent" option for the "bar" service must be the id of the service without the "@" prefix (replace "@foo" with "foo").');
        $loader = new YamlFileLoader(new ContainerBuilder(), new FileLocator(self::$fixturesPath.'/yaml'));
        $loader->load('bad_parent.yml');
    }

    public function testDecoratedServicesWithWrongSyntaxThrowsException()
    {
        $this->expectException('Symfony\Component\DependencyInjection\Exception\InvalidArgumentException');
        $this->expectExceptionMessage('The value of the "decorates" option for the "bar" service must be the id of the service without the "@" prefix (replace "@foo" with "foo").');
        $loader = new YamlFileLoader(new ContainerBuilder(), new FileLocator(self::$fixturesPath.'/yaml'));
        $loader->load('bad_decorates.yml');
    }

    public function testDecoratedServicesWithWrongOnInvalidSyntaxThrowsException()
    {
        $this->expectException('Symfony\Component\DependencyInjection\Exception\InvalidArgumentException');
        $this->expectExceptionMessage('Did you mean null (without quotes)');
        $loader = new YamlFileLoader(new ContainerBuilder(), new FileLocator(self::$fixturesPath.'/yaml'));
        $loader->load('bad_decoration_on_invalid_null.yml');
    }

    public function testInvalidTagsWithDefaults()
    {
        $this->expectException('Symfony\Component\DependencyInjection\Exception\InvalidArgumentException');
        $this->expectExceptionMessageRegExp('/Parameter "tags" must be an array for service "Foo\\\Bar" in .+services31_invalid_tags\.yml\. Check your YAML syntax./');
        $loader = new YamlFileLoader(new ContainerBuilder(), new FileLocator(self::$fixturesPath.'/yaml'));
        $loader->load('services31_invalid_tags.yml');
    }

    public function testUnderscoreServiceId()
    {
        $this->expectException('Symfony\Component\DependencyInjection\Exception\InvalidArgumentException');
        $this->expectExceptionMessage('Service names that start with an underscore are reserved. Rename the "_foo" service or define it in XML instead.');
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
        $this->assertRegExp('/^\.\d+_Bar~[._A-Za-z0-9]{7}$/', (string) $args[0]);

        $anonymous = $container->getDefinition((string) $args[0]);
        $this->assertEquals('Bar', $anonymous->getClass());
        $this->assertFalse($anonymous->isPublic());
        $this->assertTrue($anonymous->isAutowired());

        // Anonymous service in a callable
        $factory = $definition->getFactory();
        $this->assertIsArray($factory);
        $this->assertInstanceOf(Reference::class, $factory[0]);
        $this->assertTrue($container->has((string) $factory[0]));
        $this->assertRegExp('/^\.\d+_Quz~[._A-Za-z0-9]{7}$/', (string) $factory[0]);
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

    public function testAnonymousServicesWithAliases()
    {
        $this->expectException('Symfony\Component\DependencyInjection\Exception\InvalidArgumentException');
        $this->expectExceptionMessageRegExp('/Creating an alias using the tag "!service" is not allowed in ".+anonymous_services_alias\.yml"\./');
        $container = new ContainerBuilder();
        $loader = new YamlFileLoader($container, new FileLocator(self::$fixturesPath.'/yaml'));
        $loader->load('anonymous_services_alias.yml');
    }

    public function testAnonymousServicesInParameters()
    {
        $this->expectException('Symfony\Component\DependencyInjection\Exception\InvalidArgumentException');
        $this->expectExceptionMessageRegExp('/Using an anonymous service in a parameter is not allowed in ".+anonymous_services_in_parameters\.yml"\./');
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

    public function testEmptyDefaultsThrowsClearException()
    {
        $this->expectException('Symfony\Component\DependencyInjection\Exception\InvalidArgumentException');
        $this->expectExceptionMessageRegExp('/Service "_defaults" key must be an array, "NULL" given in ".+bad_empty_defaults\.yml"\./');
        $container = new ContainerBuilder();
        $loader = new YamlFileLoader($container, new FileLocator(self::$fixturesPath.'/yaml'));
        $loader->load('bad_empty_defaults.yml');
    }

    public function testEmptyInstanceofThrowsClearException()
    {
        $this->expectException('Symfony\Component\DependencyInjection\Exception\InvalidArgumentException');
        $this->expectExceptionMessageRegExp('/Service "_instanceof" key must be an array, "NULL" given in ".+bad_empty_instanceof\.yml"\./');
        $container = new ContainerBuilder();
        $loader = new YamlFileLoader($container, new FileLocator(self::$fixturesPath.'/yaml'));
        $loader->load('bad_empty_instanceof.yml');
    }

    public function testUnsupportedKeywordThrowsException()
    {
        $this->expectException('Symfony\Component\DependencyInjection\Exception\InvalidArgumentException');
        $this->expectExceptionMessageRegExp('/^The configuration key "private" is unsupported for definition "bar"/');
        $container = new ContainerBuilder();
        $loader = new YamlFileLoader($container, new FileLocator(self::$fixturesPath.'/yaml'));
        $loader->load('bad_keyword.yml');
    }

    public function testUnsupportedKeywordInServiceAliasThrowsException()
    {
        $this->expectException('Symfony\Component\DependencyInjection\Exception\InvalidArgumentException');
        $this->expectExceptionMessageRegExp('/^The configuration key "calls" is unsupported for the service "bar" which is defined as an alias/');
        $container = new ContainerBuilder();
        $loader = new YamlFileLoader($container, new FileLocator(self::$fixturesPath.'/yaml'));
        $loader->load('bad_alias.yml');
    }

    public function testCaseSensitivity()
    {
        $container = new ContainerBuilder();
        $loader = new YamlFileLoader($container, new FileLocator(self::$fixturesPath.'/yaml'));
        $loader->load('services_case.yml');
        $container->compile();

        $this->assertTrue($container->has('bar'));
        $this->assertTrue($container->has('BAR'));
        $this->assertFalse($container->has('baR'));
        $this->assertNotSame($container->get('BAR'), $container->get('bar'));
        $this->assertSame($container->get('BAR')->arguments->bar, $container->get('bar'));
        $this->assertSame($container->get('BAR')->bar, $container->get('bar'));
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
        ], array_map(function (BoundArgument $v) { return $v->getValues()[0]; }, $definition->getBindings()));
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
        ], array_map(function (BoundArgument $v) { return $v->getValues()[0]; }, $definition->getBindings()));
    }

    public function testProcessNotExistingActionParam()
    {
        $this->expectException('Symfony\Component\DependencyInjection\Exception\RuntimeException');
        $this->expectExceptionMessage('Cannot autowire service "Symfony\Component\DependencyInjection\Tests\Fixtures\ConstructNotExists": argument "$notExist" of method "__construct()" has type "Symfony\Component\DependencyInjection\Tests\Fixtures\NotExist" but this class was not found.');
        $container = new ContainerBuilder();
        $loader = new YamlFileLoader($container, new FileLocator(self::$fixturesPath.'/yaml'));
        $loader->load('services_not_existing.yml');
        $container->compile();
    }

    public function testFqcnLazyProxy()
    {
        $container = new ContainerBuilder();
        $loader = new YamlFileLoader($container, new FileLocator(self::$fixturesPath.'/yaml'));
        $loader->load('services_lazy_fqcn.yml');

        $definition = $container->getDefinition('foo');
        $this->assertSame([['interface' => 'SomeInterface']], $definition->getTag('proxy'));
    }

    public function testServiceWithSameNameAsInterfaceAndFactoryIsNotTagged()
    {
        $container = new ContainerBuilder();
        $loader = new YamlFileLoader($container, new FileLocator(self::$fixturesPath.'/yaml'));
        $loader->load('service_instanceof_factory.yml');
        $container->compile();

        $tagged = $container->findTaggedServiceIds('bar');
        $this->assertCount(1, $tagged);
    }

    /**
     * The pass may throw an exception, which will cause the test to fail.
     */
    public function testOverriddenDefaultsBindings()
    {
        $container = new ContainerBuilder();

        $loader = new YamlFileLoader($container, new FileLocator(self::$fixturesPath.'/yaml'));
        $loader->load('defaults_bindings.yml');
        $loader->load('defaults_bindings2.yml');

        (new ResolveBindingsPass())->process($container);

        $this->assertSame('overridden', $container->get('bar')->quz);
    }

    /**
     * When creating a tagged iterator using the array syntax, all optional parameters should be properly handled.
     */
    public function testDefaultValueOfTagged()
    {
        $container = new ContainerBuilder();
        $loader = new YamlFileLoader($container, new FileLocator(self::$fixturesPath.'/yaml'));
        $loader->load('tagged_iterator_optional.yml');

        $iteratorArgument = $container->getDefinition('iterator_service')->getArgument(0);
        $this->assertInstanceOf(TaggedIteratorArgument::class, $iteratorArgument);
        $this->assertNull($iteratorArgument->getIndexAttribute());
    }

    public function testReturnsClone()
    {
        $container = new ContainerBuilder();
        $loader = new YamlFileLoader($container, new FileLocator(self::$fixturesPath.'/yaml'));
        $loader->load('returns_clone.yaml');

        $expected = [
            ['bar', [1], true],
            ['bar', [2], true],
        ];
        $this->assertSame($expected, $container->getDefinition('foo')->getMethodCalls());
    }

    public function testSinglyImplementedInterfacesInMultipleResources()
    {
        $container = new ContainerBuilder();

        $loader = new YamlFileLoader($container, new FileLocator(self::$fixturesPath.'/yaml'));
        $loader->load('singly_implemented_interface_in_multiple_resources.yml');

        $alias = $container->getAlias(Prototype\SinglyImplementedInterface\Port\PortInterface::class);

        $this->assertSame(Prototype\SinglyImplementedInterface\Adapter\Adapter::class, (string) $alias);
    }

    public function testNotSinglyImplementedInterfacesInMultipleResources()
    {
        $container = new ContainerBuilder();

        $loader = new YamlFileLoader($container, new FileLocator(self::$fixturesPath.'/yaml'));
        $loader->load('not_singly_implemented_interface_in_multiple_resources.yml');

        $this->assertFalse($container->hasAlias(Prototype\SinglyImplementedInterface\Port\PortInterface::class));
    }

    public function testNotSinglyImplementedInterfacesInMultipleResourcesWithPreviouslyRegisteredAlias()
    {
        $container = new ContainerBuilder();

        $loader = new YamlFileLoader($container, new FileLocator(self::$fixturesPath.'/yaml'));
        $loader->load('not_singly_implemented_interface_in_multiple_resources_with_previously_registered_alias.yml');

        $alias = $container->getAlias(Prototype\SinglyImplementedInterface\Port\PortInterface::class);

        $this->assertSame(Prototype\SinglyImplementedInterface\Adapter\Adapter::class, (string) $alias);
    }

    public function testNotSinglyImplementedInterfacesInMultipleResourcesWithPreviouslyRegisteredAlias2()
    {
        $container = new ContainerBuilder();

        $loader = new YamlFileLoader($container, new FileLocator(self::$fixturesPath.'/yaml'));
        $loader->load('not_singly_implemented_interface_in_multiple_resources_with_previously_registered_alias2.yml');

        $alias = $container->getAlias(Prototype\SinglyImplementedInterface\Port\PortInterface::class);

        $this->assertSame(Prototype\SinglyImplementedInterface\Adapter\Adapter::class, (string) $alias);
    }

    public function testAlternativeMethodCalls()
    {
        $container = new ContainerBuilder();

        $loader = new YamlFileLoader($container, new FileLocator(self::$fixturesPath.'/yaml'));
        $loader->load('alt_call.yaml');

        $expected = [
            ['foo', [1, 2, 3]],
            ['bar', [1, 2, 3], true],
        ];

        $this->assertSame($expected, $container->getDefinition('foo')->getMethodCalls());
    }
}
