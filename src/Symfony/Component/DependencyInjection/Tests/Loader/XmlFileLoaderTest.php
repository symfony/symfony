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
use Symfony\Component\DependencyInjection\Dumper\PhpDumper;
use Symfony\Component\DependencyInjection\Loader\IniFileLoader;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\DependencyInjection\Tests\Fixtures\Bar;
use Symfony\Component\DependencyInjection\Tests\Fixtures\BarInterface;
use Symfony\Component\DependencyInjection\Tests\Fixtures\CaseSensitiveClass;
use Symfony\Component\DependencyInjection\Tests\Fixtures\NamedArgumentsDummy;
use Symfony\Component\DependencyInjection\Tests\Fixtures\Prototype;
use Symfony\Component\ExpressionLanguage\Expression;

class XmlFileLoaderTest extends TestCase
{
    protected static $fixturesPath;

    public static function setUpBeforeClass(): void
    {
        self::$fixturesPath = realpath(__DIR__.'/../Fixtures/');
        require_once self::$fixturesPath.'/includes/foo.php';
        require_once self::$fixturesPath.'/includes/ProjectExtension.php';
        require_once self::$fixturesPath.'/includes/ProjectWithXsdExtension.php';
    }

    public function testLoad()
    {
        $loader = new XmlFileLoader(new ContainerBuilder(), new FileLocator(self::$fixturesPath.'/ini'));

        try {
            $loader->load('foo.xml');
            $this->fail('->load() throws an InvalidArgumentException if the loaded file does not exist');
        } catch (\Exception $e) {
            $this->assertInstanceOf('InvalidArgumentException', $e, '->load() throws an InvalidArgumentException if the loaded file does not exist');
            $this->assertStringStartsWith('The file "foo.xml" does not exist (in:', $e->getMessage(), '->load() throws an InvalidArgumentException if the loaded file does not exist');
        }
    }

    public function testParseFile()
    {
        $loader = new XmlFileLoader(new ContainerBuilder(), new FileLocator(self::$fixturesPath.'/ini'));
        $r = new \ReflectionObject($loader);
        $m = $r->getMethod('parseFileToDOM');
        $m->setAccessible(true);

        try {
            $m->invoke($loader, self::$fixturesPath.'/ini/parameters.ini');
            $this->fail('->parseFileToDOM() throws an InvalidArgumentException if the loaded file is not a valid XML file');
        } catch (\Exception $e) {
            $this->assertInstanceOf('Symfony\\Component\\DependencyInjection\\Exception\\InvalidArgumentException', $e, '->parseFileToDOM() throws an InvalidArgumentException if the loaded file is not a valid XML file');
            $this->assertRegExp(sprintf('#^Unable to parse file ".+%s": .+.$#', 'parameters.ini'), $e->getMessage(), '->parseFileToDOM() throws an InvalidArgumentException if the loaded file is not a valid XML file');

            $e = $e->getPrevious();
            $this->assertInstanceOf('InvalidArgumentException', $e, '->parseFileToDOM() throws an InvalidArgumentException if the loaded file is not a valid XML file');
            $this->assertStringStartsWith('[ERROR 4] Start tag expected, \'<\' not found (in', $e->getMessage(), '->parseFileToDOM() throws an InvalidArgumentException if the loaded file is not a valid XML file');
        }

        $loader = new XmlFileLoader(new ContainerBuilder(), new FileLocator(self::$fixturesPath.'/xml'));

        try {
            $m->invoke($loader, self::$fixturesPath.'/xml/nonvalid.xml');
            $this->fail('->parseFileToDOM() throws an InvalidArgumentException if the loaded file does not validate the XSD');
        } catch (\Exception $e) {
            $this->assertInstanceOf('Symfony\\Component\\DependencyInjection\\Exception\\InvalidArgumentException', $e, '->parseFileToDOM() throws an InvalidArgumentException if the loaded file does not validate the XSD');
            $this->assertRegExp(sprintf('#^Unable to parse file ".+%s": .+.$#', 'nonvalid.xml'), $e->getMessage(), '->parseFileToDOM() throws an InvalidArgumentException if the loaded file is not a valid XML file');

            $e = $e->getPrevious();
            $this->assertInstanceOf('InvalidArgumentException', $e, '->parseFileToDOM() throws an InvalidArgumentException if the loaded file does not validate the XSD');
            $this->assertStringStartsWith('[ERROR 1845] Element \'nonvalid\': No matching global declaration available for the validation root. (in', $e->getMessage(), '->parseFileToDOM() throws an InvalidArgumentException if the loaded file does not validate the XSD');
        }

        $xml = $m->invoke($loader, self::$fixturesPath.'/xml/services1.xml');
        $this->assertInstanceOf('DOMDocument', $xml, '->parseFileToDOM() returns an SimpleXMLElement object');
    }

    public function testLoadWithExternalEntitiesDisabled()
    {
        $disableEntities = libxml_disable_entity_loader(true);

        $containerBuilder = new ContainerBuilder();
        $loader = new XmlFileLoader($containerBuilder, new FileLocator(self::$fixturesPath.'/xml'));
        $loader->load('services2.xml');

        libxml_disable_entity_loader($disableEntities);

        $this->assertGreaterThan(0, $containerBuilder->getParameterBag()->all(), 'Parameters can be read from the config file.');
    }

    public function testLoadParameters()
    {
        $container = new ContainerBuilder();
        $loader = new XmlFileLoader($container, new FileLocator(self::$fixturesPath.'/xml'));
        $loader->load('services2.xml');

        $actual = $container->getParameterBag()->all();
        $expected = [
            'a string',
            'foo' => 'bar',
            'values' => [
                0,
                'integer' => 4,
                100 => null,
                'true',
                true,
                false,
                'on',
                'off',
                'float' => 1.3,
                1000.3,
                'a string',
                ['foo', 'bar'],
            ],
            'mixedcase' => ['MixedCaseKey' => 'value'],
            'constant' => PHP_EOL,
        ];

        $this->assertEquals($expected, $actual, '->load() converts XML values to PHP ones');
    }

    public function testLoadImports()
    {
        $container = new ContainerBuilder();
        $resolver = new LoaderResolver([
            new IniFileLoader($container, new FileLocator(self::$fixturesPath.'/ini')),
            new YamlFileLoader($container, new FileLocator(self::$fixturesPath.'/yml')),
            $loader = new XmlFileLoader($container, new FileLocator(self::$fixturesPath.'/xml')),
        ]);
        $loader->setResolver($resolver);
        $loader->load('services4.xml');

        $actual = $container->getParameterBag()->all();
        $expected = [
            'a string',
            'foo' => 'bar',
            'values' => [
                0,
                'integer' => 4,
                100 => null,
                'true',
                true,
                false,
                'on',
                'off',
                'float' => 1.3,
                1000.3,
                'a string',
                ['foo', 'bar'],
            ],
            'mixedcase' => ['MixedCaseKey' => 'value'],
            'constant' => PHP_EOL,
            'bar' => '%foo%',
            'imported_from_ini' => true,
            'imported_from_yaml' => true,
            'with_wrong_ext' => 'from yaml',
        ];

        $this->assertEquals(array_keys($expected), array_keys($actual), '->load() imports and merges imported files');
        $this->assertTrue($actual['imported_from_ini']);

        // Bad import throws no exception due to ignore_errors value.
        $loader->load('services4_bad_import.xml');

        // Bad import with nonexistent file throws no exception due to ignore_errors: not_found value.
        $loader->load('services4_bad_import_file_not_found.xml');

        try {
            $loader->load('services4_bad_import_with_errors.xml');
            $this->fail('->load() throws a LoaderLoadException if the imported xml file configuration does not exist');
        } catch (\Exception $e) {
            $this->assertInstanceOf('Symfony\\Component\\Config\\Exception\\LoaderLoadException', $e, '->load() throws a LoaderLoadException if the imported xml file configuration does not exist');
            $this->assertRegExp(sprintf('#^The file "%1$s" does not exist \(in: .+\) in %1$s \(which is being imported from ".+%2$s"\)\.$#', 'foo_fake\.xml', 'services4_bad_import_with_errors\.xml'), $e->getMessage(), '->load() throws a LoaderLoadException if the imported xml file configuration does not exist');

            $e = $e->getPrevious();
            $this->assertInstanceOf('Symfony\\Component\\Config\\Exception\\FileLocatorFileNotFoundException', $e, '->load() throws a FileLocatorFileNotFoundException if the imported xml file configuration does not exist');
            $this->assertRegExp(sprintf('#^The file "%s" does not exist \(in: .+\)\.$#', 'foo_fake\.xml'), $e->getMessage(), '->load() throws a FileLocatorFileNotFoundException if the imported xml file configuration does not exist');
        }

        try {
            $loader->load('services4_bad_import_nonvalid.xml');
            $this->fail('->load() throws an LoaderLoadException if the imported configuration does not validate the XSD');
        } catch (\Exception $e) {
            $this->assertInstanceOf('Symfony\\Component\\Config\\Exception\\LoaderLoadException', $e, '->load() throws a LoaderLoadException if the imported configuration does not validate the XSD');
            $this->assertRegExp(sprintf('#^Unable to parse file ".+%s": .+.$#', 'nonvalid\.xml'), $e->getMessage(), '->load() throws a LoaderLoadException if the imported configuration does not validate the XSD');

            $e = $e->getPrevious();
            $this->assertInstanceOf('Symfony\\Component\\DependencyInjection\\Exception\\InvalidArgumentException', $e, '->load() throws an InvalidArgumentException if the configuration does not validate the XSD');
            $this->assertRegExp(sprintf('#^Unable to parse file ".+%s": .+.$#', 'nonvalid\.xml'), $e->getMessage(), '->load() throws an InvalidArgumentException if the configuration does not validate the XSD');

            $e = $e->getPrevious();
            $this->assertInstanceOf('Symfony\\Component\\Config\\Util\\Exception\\XmlParsingException', $e, '->load() throws a XmlParsingException if the configuration does not validate the XSD');
            $this->assertStringStartsWith('[ERROR 1845] Element \'nonvalid\': No matching global declaration available for the validation root. (in', $e->getMessage(), '->load() throws a XmlParsingException if the loaded file does not validate the XSD');
        }
    }

    public function testLoadAnonymousServices()
    {
        $container = new ContainerBuilder();
        $loader = new XmlFileLoader($container, new FileLocator(self::$fixturesPath.'/xml'));
        $loader->load('services5.xml');
        $services = $container->getDefinitions();
        $this->assertCount(6, $services, '->load() attributes unique ids to anonymous services');

        // anonymous service as an argument
        $args = $services['foo']->getArguments();
        $this->assertCount(1, $args, '->load() references anonymous services as "normal" ones');
        $this->assertInstanceOf('Symfony\\Component\\DependencyInjection\\Reference', $args[0], '->load() converts anonymous services to references to "normal" services');
        $this->assertArrayHasKey((string) $args[0], $services, '->load() makes a reference to the created ones');
        $inner = $services[(string) $args[0]];
        $this->assertEquals('BarClass', $inner->getClass(), '->load() uses the same configuration as for the anonymous ones');
        $this->assertFalse($inner->isPublic());

        // inner anonymous services
        $args = $inner->getArguments();
        $this->assertCount(1, $args, '->load() references anonymous services as "normal" ones');
        $this->assertInstanceOf('Symfony\\Component\\DependencyInjection\\Reference', $args[0], '->load() converts anonymous services to references to "normal" services');
        $this->assertArrayHasKey((string) $args[0], $services, '->load() makes a reference to the created ones');
        $inner = $services[(string) $args[0]];
        $this->assertEquals('BazClass', $inner->getClass(), '->load() uses the same configuration as for the anonymous ones');
        $this->assertFalse($inner->isPublic());

        // anonymous service as a property
        $properties = $services['foo']->getProperties();
        $property = $properties['p'];
        $this->assertInstanceOf('Symfony\\Component\\DependencyInjection\\Reference', $property, '->load() converts anonymous services to references to "normal" services');
        $this->assertArrayHasKey((string) $property, $services, '->load() makes a reference to the created ones');
        $inner = $services[(string) $property];
        $this->assertEquals('BuzClass', $inner->getClass(), '->load() uses the same configuration as for the anonymous ones');
        $this->assertFalse($inner->isPublic());

        // anonymous services are shared when using decoration definitions
        $container->compile();
        $services = $container->getDefinitions();
        $fooArgs = $services['foo']->getArguments();
        $barArgs = $services['bar']->getArguments();
        $this->assertSame($fooArgs[0], $barArgs[0]);
    }

    public function testLoadAnonymousServicesWithoutId()
    {
        $this->expectException('Symfony\Component\DependencyInjection\Exception\InvalidArgumentException');
        $this->expectExceptionMessage('Top-level services must have "id" attribute, none found in');
        $container = new ContainerBuilder();
        $loader = new XmlFileLoader($container, new FileLocator(self::$fixturesPath.'/xml'));
        $loader->load('services_without_id.xml');
    }

    public function testLoadAnonymousNestedServices()
    {
        $container = new ContainerBuilder();
        $loader = new XmlFileLoader($container, new FileLocator(self::$fixturesPath.'/xml'));
        $loader->load('nested_service_without_id.xml');

        $this->assertTrue($container->hasDefinition('FooClass'));
        $arguments = $container->getDefinition('FooClass')->getArguments();
        $this->assertInstanceOf(Reference::class, array_shift($arguments));
    }

    public function testLoadServices()
    {
        $container = new ContainerBuilder();
        $loader = new XmlFileLoader($container, new FileLocator(self::$fixturesPath.'/xml'));
        $loader->load('services6.xml');
        $services = $container->getDefinitions();
        $this->assertArrayHasKey('foo', $services, '->load() parses <service> elements');
        $this->assertFalse($services['not_shared']->isShared(), '->load() parses shared flag');
        $this->assertInstanceOf('Symfony\\Component\\DependencyInjection\\Definition', $services['foo'], '->load() converts <service> element to Definition instances');
        $this->assertEquals('FooClass', $services['foo']->getClass(), '->load() parses the class attribute');
        $this->assertEquals('%path%/foo.php', $services['file']->getFile(), '->load() parses the file tag');
        $this->assertEquals(['foo', new Reference('foo'), [true, false]], $services['arguments']->getArguments(), '->load() parses the argument tags');
        $this->assertEquals('sc_configure', $services['configurator1']->getConfigurator(), '->load() parses the configurator tag');
        $this->assertEquals([new Reference('baz'), 'configure'], $services['configurator2']->getConfigurator(), '->load() parses the configurator tag');
        $this->assertEquals(['BazClass', 'configureStatic'], $services['configurator3']->getConfigurator(), '->load() parses the configurator tag');
        $this->assertEquals([['setBar', []], ['setBar', [new Expression('service("foo").foo() ~ (container.hasParameter("foo") ? parameter("foo") : "default")')]]], $services['method_call1']->getMethodCalls(), '->load() parses the method_call tag');
        $this->assertEquals([['setBar', ['foo', new Reference('foo'), [true, false]]]], $services['method_call2']->getMethodCalls(), '->load() parses the method_call tag');
        $this->assertEquals('factory', $services['new_factory1']->getFactory(), '->load() parses the factory tag');
        $this->assertEquals([new Reference('baz'), 'getClass'], $services['new_factory2']->getFactory(), '->load() parses the factory tag');
        $this->assertEquals(['BazClass', 'getInstance'], $services['new_factory3']->getFactory(), '->load() parses the factory tag');
        $this->assertSame([null, 'getInstance'], $services['new_factory4']->getFactory(), '->load() accepts factory tag without class');
        $this->assertEquals([new Reference('baz'), '__invoke'], $services['new_factory5']->getFactory(), '->load() accepts service reference as invokable factory');

        $aliases = $container->getAliases();
        $this->assertArrayHasKey('alias_for_foo', $aliases, '->load() parses <service> elements');
        $this->assertEquals('foo', (string) $aliases['alias_for_foo'], '->load() parses aliases');
        $this->assertTrue($aliases['alias_for_foo']->isPublic());
        $this->assertArrayHasKey('another_alias_for_foo', $aliases);
        $this->assertEquals('foo', (string) $aliases['another_alias_for_foo']);
        $this->assertFalse($aliases['another_alias_for_foo']->isPublic());

        $this->assertEquals(['decorated', null, 0], $services['decorator_service']->getDecoratedService());
        $this->assertEquals(['decorated', 'decorated.pif-pouf', 0], $services['decorator_service_with_name']->getDecoratedService());
        $this->assertEquals(['decorated', 'decorated.pif-pouf', 5], $services['decorator_service_with_name_and_priority']->getDecoratedService());
        $this->assertEquals(['decorated', 'decorated.pif-pouf', 5, ContainerInterface::IGNORE_ON_INVALID_REFERENCE], $services['decorator_service_with_name_and_priority_and_on_invalid']->getDecoratedService());
    }

    public function testParsesIteratorArgument()
    {
        $container = new ContainerBuilder();
        $loader = new XmlFileLoader($container, new FileLocator(self::$fixturesPath.'/xml'));
        $loader->load('services9.xml');

        $lazyDefinition = $container->getDefinition('lazy_context');

        $this->assertEquals([new IteratorArgument(['k1' => new Reference('foo.baz'), 'k2' => new Reference('service_container')]), new IteratorArgument([])], $lazyDefinition->getArguments(), '->load() parses lazy arguments');
    }

    public function testParsesTags()
    {
        $container = new ContainerBuilder();
        $loader = new XmlFileLoader($container, new FileLocator(self::$fixturesPath.'/xml'));
        $loader->load('services10.xml');

        $services = $container->findTaggedServiceIds('foo_tag');
        $this->assertCount(1, $services);

        foreach ($services as $id => $tagAttributes) {
            foreach ($tagAttributes as $attributes) {
                $this->assertArrayHasKey('other_option', $attributes);
                $this->assertEquals('lorem', $attributes['other_option']);
                $this->assertArrayHasKey('other-option', $attributes, 'unnormalized tag attributes should not be removed');

                $this->assertEquals('ciz', $attributes['some_option'], 'no overriding should be done when normalizing');
                $this->assertEquals('cat', $attributes['some-option']);

                $this->assertArrayNotHasKey('an_other_option', $attributes, 'normalization should not be done when an underscore is already found');
            }
        }
    }

    public function testParseTaggedArgumentsWithIndexBy()
    {
        $container = new ContainerBuilder();
        $loader = new XmlFileLoader($container, new FileLocator(self::$fixturesPath.'/xml'));
        $loader->load('services_with_tagged_arguments.xml');

        $this->assertCount(1, $container->getDefinition('foo')->getTag('foo_tag'));
        $this->assertCount(1, $container->getDefinition('foo_tagged_iterator')->getArguments());
        $this->assertCount(1, $container->getDefinition('foo_tagged_locator')->getArguments());

        $taggedIterator = new TaggedIteratorArgument('foo_tag', 'barfoo', 'foobar', false, 'getPriority');
        $this->assertEquals($taggedIterator, $container->getDefinition('foo_tagged_iterator')->getArgument(0));

        $taggedIterator = new TaggedIteratorArgument('foo_tag', 'barfoo', 'foobar', true, 'getPriority');
        $this->assertEquals(new ServiceLocatorArgument($taggedIterator), $container->getDefinition('foo_tagged_locator')->getArgument(0));
    }

    public function testParseTagsWithoutNameThrowsException()
    {
        $this->expectException('Symfony\Component\DependencyInjection\Exception\InvalidArgumentException');
        $container = new ContainerBuilder();
        $loader = new XmlFileLoader($container, new FileLocator(self::$fixturesPath.'/xml'));
        $loader->load('tag_without_name.xml');
    }

    public function testParseTagWithEmptyNameThrowsException()
    {
        $this->expectException('Symfony\Component\DependencyInjection\Exception\InvalidArgumentException');
        $this->expectExceptionMessageRegExp('/The tag name for service ".+" in .* must be a non-empty string/');
        $container = new ContainerBuilder();
        $loader = new XmlFileLoader($container, new FileLocator(self::$fixturesPath.'/xml'));
        $loader->load('tag_with_empty_name.xml');
    }

    public function testDeprecated()
    {
        $container = new ContainerBuilder();
        $loader = new XmlFileLoader($container, new FileLocator(self::$fixturesPath.'/xml'));
        $loader->load('services_deprecated.xml');

        $this->assertTrue($container->getDefinition('foo')->isDeprecated());
        $message = 'The "foo" service is deprecated. You should stop using it, as it will be removed in the future.';
        $this->assertSame($message, $container->getDefinition('foo')->getDeprecationMessage('foo'));

        $this->assertTrue($container->getDefinition('bar')->isDeprecated());
        $message = 'The "bar" service is deprecated.';
        $this->assertSame($message, $container->getDefinition('bar')->getDeprecationMessage('bar'));
    }

    public function testDeprecatedAliases()
    {
        $container = new ContainerBuilder();
        $loader = new XmlFileLoader($container, new FileLocator(self::$fixturesPath.'/xml'));
        $loader->load('deprecated_alias_definitions.xml');

        $this->assertTrue($container->getAlias('alias_for_foo')->isDeprecated());
        $message = 'The "alias_for_foo" service alias is deprecated. You should stop using it, as it will be removed in the future.';
        $this->assertSame($message, $container->getAlias('alias_for_foo')->getDeprecationMessage('alias_for_foo'));

        $this->assertTrue($container->getAlias('alias_for_foobar')->isDeprecated());
        $message = 'The "alias_for_foobar" service alias is deprecated.';
        $this->assertSame($message, $container->getAlias('alias_for_foobar')->getDeprecationMessage('alias_for_foobar'));
    }

    public function testConvertDomElementToArray()
    {
        $doc = new \DOMDocument('1.0');
        $doc->loadXML('<foo>bar</foo>');
        $this->assertEquals('bar', XmlFileLoader::convertDomElementToArray($doc->documentElement), '::convertDomElementToArray() converts a \DomElement to an array');

        $doc = new \DOMDocument('1.0');
        $doc->loadXML('<foo foo="bar" />');
        $this->assertEquals(['foo' => 'bar'], XmlFileLoader::convertDomElementToArray($doc->documentElement), '::convertDomElementToArray() converts a \DomElement to an array');

        $doc = new \DOMDocument('1.0');
        $doc->loadXML('<foo><foo>bar</foo></foo>');
        $this->assertEquals(['foo' => 'bar'], XmlFileLoader::convertDomElementToArray($doc->documentElement), '::convertDomElementToArray() converts a \DomElement to an array');

        $doc = new \DOMDocument('1.0');
        $doc->loadXML('<foo><foo>bar<foo>bar</foo></foo></foo>');
        $this->assertEquals(['foo' => ['value' => 'bar', 'foo' => 'bar']], XmlFileLoader::convertDomElementToArray($doc->documentElement), '::convertDomElementToArray() converts a \DomElement to an array');

        $doc = new \DOMDocument('1.0');
        $doc->loadXML('<foo><foo></foo></foo>');
        $this->assertEquals(['foo' => null], XmlFileLoader::convertDomElementToArray($doc->documentElement), '::convertDomElementToArray() converts a \DomElement to an array');

        $doc = new \DOMDocument('1.0');
        $doc->loadXML('<foo><foo><!-- foo --></foo></foo>');
        $this->assertEquals(['foo' => null], XmlFileLoader::convertDomElementToArray($doc->documentElement), '::convertDomElementToArray() converts a \DomElement to an array');

        $doc = new \DOMDocument('1.0');
        $doc->loadXML('<foo><foo foo="bar"/><foo foo="bar"/></foo>');
        $this->assertEquals(['foo' => [['foo' => 'bar'], ['foo' => 'bar']]], XmlFileLoader::convertDomElementToArray($doc->documentElement), '::convertDomElementToArray() converts a \DomElement to an array');
    }

    public function testExtensions()
    {
        $container = new ContainerBuilder();
        $container->registerExtension(new \ProjectExtension());
        $container->registerExtension(new \ProjectWithXsdExtension());
        $loader = new XmlFileLoader($container, new FileLocator(self::$fixturesPath.'/xml'));

        // extension without an XSD
        $loader->load('extensions/services1.xml');
        $container->compile();
        $services = $container->getDefinitions();
        $parameters = $container->getParameterBag()->all();

        $this->assertArrayHasKey('project.service.bar', $services, '->load() parses extension elements');
        $this->assertArrayHasKey('project.parameter.bar', $parameters, '->load() parses extension elements');

        $this->assertEquals('BAR', $services['project.service.foo']->getClass(), '->load() parses extension elements');
        $this->assertEquals('BAR', $parameters['project.parameter.foo'], '->load() parses extension elements');

        // extension with an XSD
        $container = new ContainerBuilder();
        $container->registerExtension(new \ProjectExtension());
        $container->registerExtension(new \ProjectWithXsdExtension());
        $loader = new XmlFileLoader($container, new FileLocator(self::$fixturesPath.'/xml'));
        $loader->load('extensions/services2.xml');
        $container->compile();
        $services = $container->getDefinitions();
        $parameters = $container->getParameterBag()->all();

        $this->assertArrayHasKey('project.service.bar', $services, '->load() parses extension elements');
        $this->assertArrayHasKey('project.parameter.bar', $parameters, '->load() parses extension elements');

        $this->assertEquals('BAR', $services['project.service.foo']->getClass(), '->load() parses extension elements');
        $this->assertEquals('BAR', $parameters['project.parameter.foo'], '->load() parses extension elements');

        $container = new ContainerBuilder();
        $container->registerExtension(new \ProjectExtension());
        $container->registerExtension(new \ProjectWithXsdExtension());
        $loader = new XmlFileLoader($container, new FileLocator(self::$fixturesPath.'/xml'));

        // extension with an XSD (does not validate)
        try {
            $loader->load('extensions/services3.xml');
            $this->fail('->load() throws an InvalidArgumentException if the configuration does not validate the XSD');
        } catch (\Exception $e) {
            $this->assertInstanceOf('Symfony\\Component\\DependencyInjection\\Exception\\InvalidArgumentException', $e, '->load() throws an InvalidArgumentException if the configuration does not validate the XSD');
            $this->assertRegExp(sprintf('#^Unable to parse file ".+%s": .+.$#', 'services3.xml'), $e->getMessage(), '->load() throws an InvalidArgumentException if the configuration does not validate the XSD');

            $e = $e->getPrevious();
            $this->assertInstanceOf('InvalidArgumentException', $e, '->load() throws an InvalidArgumentException if the configuration does not validate the XSD');
            $this->assertStringContainsString('The attribute \'bar\' is not allowed', $e->getMessage(), '->load() throws an InvalidArgumentException if the configuration does not validate the XSD');
        }

        // non-registered extension
        try {
            $loader->load('extensions/services4.xml');
            $this->fail('->load() throws an InvalidArgumentException if the tag is not valid');
        } catch (\Exception $e) {
            $this->assertInstanceOf('\InvalidArgumentException', $e, '->load() throws an InvalidArgumentException if the tag is not valid');
            $this->assertStringStartsWith('There is no extension able to load the configuration for "project:bar" (in', $e->getMessage(), '->load() throws an InvalidArgumentException if the tag is not valid');
        }
    }

    public function testExtensionInPhar()
    {
        if (\extension_loaded('suhosin') && false === strpos(ini_get('suhosin.executor.include.whitelist'), 'phar')) {
            $this->markTestSkipped('To run this test, add "phar" to the "suhosin.executor.include.whitelist" settings in your php.ini file.');
        }

        require_once self::$fixturesPath.'/includes/ProjectWithXsdExtensionInPhar.phar';

        // extension with an XSD in PHAR archive
        $container = new ContainerBuilder();
        $container->registerExtension(new \ProjectWithXsdExtensionInPhar());
        $loader = new XmlFileLoader($container, new FileLocator(self::$fixturesPath.'/xml'));
        $loader->load('extensions/services6.xml');

        // extension with an XSD in PHAR archive (does not validate)
        try {
            $loader->load('extensions/services7.xml');
            $this->fail('->load() throws an InvalidArgumentException if the configuration does not validate the XSD');
        } catch (\Exception $e) {
            $this->assertInstanceOf('Symfony\\Component\\DependencyInjection\\Exception\\InvalidArgumentException', $e, '->load() throws an InvalidArgumentException if the configuration does not validate the XSD');
            $this->assertRegExp(sprintf('#^Unable to parse file ".+%s": .+.$#', 'services7.xml'), $e->getMessage(), '->load() throws an InvalidArgumentException if the configuration does not validate the XSD');

            $e = $e->getPrevious();
            $this->assertInstanceOf('InvalidArgumentException', $e, '->load() throws an InvalidArgumentException if the configuration does not validate the XSD');
            $this->assertStringContainsString('The attribute \'bar\' is not allowed', $e->getMessage(), '->load() throws an InvalidArgumentException if the configuration does not validate the XSD');
        }
    }

    public function testSupports()
    {
        $loader = new XmlFileLoader(new ContainerBuilder(), new FileLocator());

        $this->assertTrue($loader->supports('foo.xml'), '->supports() returns true if the resource is loadable');
        $this->assertFalse($loader->supports('foo.foo'), '->supports() returns false if the resource is not loadable');
        $this->assertTrue($loader->supports('with_wrong_ext.yml', 'xml'), '->supports() returns true if the resource with forced type is loadable');
    }

    public function testNoNamingConflictsForAnonymousServices()
    {
        $container = new ContainerBuilder();

        $loader1 = new XmlFileLoader($container, new FileLocator(self::$fixturesPath.'/xml/extension1'));
        $loader1->load('services.xml');
        $services = $container->getDefinitions();
        $this->assertCount(3, $services, '->load() attributes unique ids to anonymous services');
        $loader2 = new XmlFileLoader($container, new FileLocator(self::$fixturesPath.'/xml/extension2'));
        $loader2->load('services.xml');
        $services = $container->getDefinitions();
        $this->assertCount(5, $services, '->load() attributes unique ids to anonymous services');

        $services = $container->getDefinitions();
        $args1 = $services['extension1.foo']->getArguments();
        $inner1 = $services[(string) $args1[0]];
        $this->assertEquals('BarClass1', $inner1->getClass(), '->load() uses the same configuration as for the anonymous ones');
        $args2 = $services['extension2.foo']->getArguments();
        $inner2 = $services[(string) $args2[0]];
        $this->assertEquals('BarClass2', $inner2->getClass(), '->load() uses the same configuration as for the anonymous ones');
    }

    public function testDocTypeIsNotAllowed()
    {
        $container = new ContainerBuilder();

        $loader = new XmlFileLoader($container, new FileLocator(self::$fixturesPath.'/xml'));

        // document types are not allowed.
        try {
            $loader->load('withdoctype.xml');
            $this->fail('->load() throws an InvalidArgumentException if the configuration contains a document type');
        } catch (\Exception $e) {
            $this->assertInstanceOf('Symfony\\Component\\DependencyInjection\\Exception\\InvalidArgumentException', $e, '->load() throws an InvalidArgumentException if the configuration contains a document type');
            $this->assertRegExp(sprintf('#^Unable to parse file ".+%s": .+.$#', 'withdoctype.xml'), $e->getMessage(), '->load() throws an InvalidArgumentException if the configuration contains a document type');

            $e = $e->getPrevious();
            $this->assertInstanceOf('InvalidArgumentException', $e, '->load() throws an InvalidArgumentException if the configuration contains a document type');
            $this->assertSame('Document types are not allowed.', $e->getMessage(), '->load() throws an InvalidArgumentException if the configuration contains a document type');
        }
    }

    public function testXmlNamespaces()
    {
        $container = new ContainerBuilder();
        $loader = new XmlFileLoader($container, new FileLocator(self::$fixturesPath.'/xml'));
        $loader->load('namespaces.xml');
        $services = $container->getDefinitions();

        $this->assertArrayHasKey('foo', $services, '->load() parses <srv:service> elements');
        $this->assertCount(1, $services['foo']->getTag('foo.tag'), '->load parses <srv:tag> elements');
        $this->assertEquals([['setBar', ['foo']]], $services['foo']->getMethodCalls(), '->load() parses the <srv:call> tag');
    }

    public function testLoadIndexedArguments()
    {
        $container = new ContainerBuilder();
        $loader = new XmlFileLoader($container, new FileLocator(self::$fixturesPath.'/xml'));
        $loader->load('services14.xml');

        $this->assertEquals(['index_0' => 'app'], $container->findDefinition('logger')->getArguments());
    }

    public function testLoadInlinedServices()
    {
        $container = new ContainerBuilder();
        $loader = new XmlFileLoader($container, new FileLocator(self::$fixturesPath.'/xml'));
        $loader->load('services21.xml');

        $foo = $container->getDefinition('foo');

        $fooFactory = $foo->getFactory();
        $this->assertInstanceOf(Reference::class, $fooFactory[0]);
        $this->assertTrue($container->has((string) $fooFactory[0]));
        $fooFactoryDefinition = $container->getDefinition((string) $fooFactory[0]);
        $this->assertSame('FooFactory', $fooFactoryDefinition->getClass());
        $this->assertSame('createFoo', $fooFactory[1]);

        $fooFactoryFactory = $fooFactoryDefinition->getFactory();
        $this->assertInstanceOf(Reference::class, $fooFactoryFactory[0]);
        $this->assertTrue($container->has((string) $fooFactoryFactory[0]));
        $this->assertSame('Foobar', $container->getDefinition((string) $fooFactoryFactory[0])->getClass());
        $this->assertSame('createFooFactory', $fooFactoryFactory[1]);

        $fooConfigurator = $foo->getConfigurator();
        $this->assertInstanceOf(Reference::class, $fooConfigurator[0]);
        $this->assertTrue($container->has((string) $fooConfigurator[0]));
        $fooConfiguratorDefinition = $container->getDefinition((string) $fooConfigurator[0]);
        $this->assertSame('Bar', $fooConfiguratorDefinition->getClass());
        $this->assertSame('configureFoo', $fooConfigurator[1]);

        $barConfigurator = $fooConfiguratorDefinition->getConfigurator();
        $this->assertInstanceOf(Reference::class, $barConfigurator[0]);
        $this->assertSame('Baz', $container->getDefinition((string) $barConfigurator[0])->getClass());
        $this->assertSame('configureBar', $barConfigurator[1]);
    }

    public function testAutowire()
    {
        $container = new ContainerBuilder();
        $loader = new XmlFileLoader($container, new FileLocator(self::$fixturesPath.'/xml'));
        $loader->load('services23.xml');

        $this->assertTrue($container->getDefinition('bar')->isAutowired());
    }

    public function testClassFromId()
    {
        $container = new ContainerBuilder();
        $loader = new XmlFileLoader($container, new FileLocator(self::$fixturesPath.'/xml'));
        $loader->load('class_from_id.xml');
        $container->compile();

        $this->assertEquals(CaseSensitiveClass::class, $container->getDefinition(CaseSensitiveClass::class)->getClass());
    }

    public function testPrototype()
    {
        $container = new ContainerBuilder();
        $loader = new XmlFileLoader($container, new FileLocator(self::$fixturesPath.'/xml'));
        $loader->load('services_prototype.xml');

        $ids = array_keys($container->getDefinitions());
        sort($ids);
        $this->assertSame([Prototype\Foo::class, Prototype\Sub\Bar::class, 'service_container'], $ids);

        $resources = $container->getResources();

        $fixturesDir = \dirname(__DIR__).\DIRECTORY_SEPARATOR.'Fixtures'.\DIRECTORY_SEPARATOR;
        $this->assertContains((string) new FileResource($fixturesDir.'xml'.\DIRECTORY_SEPARATOR.'services_prototype.xml'), $resources);

        $prototypeRealPath = realpath(__DIR__.\DIRECTORY_SEPARATOR.'..'.\DIRECTORY_SEPARATOR.'Fixtures'.\DIRECTORY_SEPARATOR.'Prototype');
        $globResource = new GlobResource(
            $fixturesDir.'Prototype',
            '/*',
            true,
            false,
            [
                str_replace(\DIRECTORY_SEPARATOR, '/', $prototypeRealPath.\DIRECTORY_SEPARATOR.'OtherDir') => true,
                str_replace(\DIRECTORY_SEPARATOR, '/', $prototypeRealPath.\DIRECTORY_SEPARATOR.'BadClasses') => true,
                str_replace(\DIRECTORY_SEPARATOR, '/', $prototypeRealPath.\DIRECTORY_SEPARATOR.'SinglyImplementedInterface') => true,
            ]
        );
        $this->assertContains((string) $globResource, $resources);
        $resources = array_map('strval', $resources);
        $this->assertContains('reflection.Symfony\Component\DependencyInjection\Tests\Fixtures\Prototype\Foo', $resources);
        $this->assertContains('reflection.Symfony\Component\DependencyInjection\Tests\Fixtures\Prototype\Sub\Bar', $resources);
    }

    public function testPrototypeExcludeWithArray()
    {
        $container = new ContainerBuilder();
        $loader = new XmlFileLoader($container, new FileLocator(self::$fixturesPath.'/xml'));
        $loader->load('services_prototype_array.xml');

        $ids = array_keys($container->getDefinitions());
        sort($ids);
        $this->assertSame([Prototype\Foo::class, Prototype\Sub\Bar::class, 'service_container'], $ids);

        $resources = $container->getResources();

        $fixturesDir = \dirname(__DIR__).\DIRECTORY_SEPARATOR.'Fixtures'.\DIRECTORY_SEPARATOR;
        $this->assertContains((string) new FileResource($fixturesDir.'xml'.\DIRECTORY_SEPARATOR.'services_prototype_array.xml'), $resources);

        $prototypeRealPath = realpath(__DIR__.\DIRECTORY_SEPARATOR.'..'.\DIRECTORY_SEPARATOR.'Fixtures'.\DIRECTORY_SEPARATOR.'Prototype');
        $globResource = new GlobResource(
            $fixturesDir.'Prototype',
            '/*',
            true,
            false,
            [
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

    public function testAliasDefinitionContainsUnsupportedElements()
    {
        $this->expectException('Symfony\Component\DependencyInjection\Exception\InvalidArgumentException');
        $this->expectExceptionMessage('Invalid attribute "class" defined for alias "bar" in');
        $container = new ContainerBuilder();
        $loader = new XmlFileLoader($container, new FileLocator(self::$fixturesPath.'/xml'));

        $loader->load('invalid_alias_definition.xml');

        $this->assertTrue($container->has('bar'));
    }

    public function testArgumentWithKeyOutsideCollection()
    {
        $container = new ContainerBuilder();
        $loader = new XmlFileLoader($container, new FileLocator(self::$fixturesPath.'/xml'));
        $loader->load('with_key_outside_collection.xml');

        $this->assertSame(['type' => 'foo', 'bar'], $container->getDefinition('foo')->getArguments());
    }

    public function testDefaults()
    {
        $container = new ContainerBuilder();
        $loader = new XmlFileLoader($container, new FileLocator(self::$fixturesPath.'/xml'));
        $loader->load('services28.xml');

        $this->assertFalse($container->getDefinition('with_defaults')->isPublic());
        $this->assertSame(['foo' => [[]]], $container->getDefinition('with_defaults')->getTags());
        $this->assertTrue($container->getDefinition('with_defaults')->isAutowired());
        $this->assertArrayNotHasKey('public', $container->getDefinition('with_defaults')->getChanges());
        $this->assertArrayNotHasKey('autowire', $container->getDefinition('with_defaults')->getChanges());

        $container->compile();

        $this->assertTrue($container->getDefinition('no_defaults')->isPublic());

        $this->assertSame(['foo' => [[]]], $container->getDefinition('no_defaults')->getTags());

        $this->assertFalse($container->getDefinition('no_defaults')->isAutowired());

        $this->assertTrue($container->getDefinition('child_def')->isPublic());
        $this->assertSame(['foo' => [[]]], $container->getDefinition('child_def')->getTags());
        $this->assertFalse($container->getDefinition('child_def')->isAutowired());

        $definitions = $container->getDefinitions();
        $this->assertSame('service_container', key($definitions));

        array_shift($definitions);
        $anonymous = current($definitions);
        $this->assertSame('bar', key($definitions));
        $this->assertTrue($anonymous->isPublic());
        $this->assertTrue($anonymous->isAutowired());
        $this->assertSame(['foo' => [[]]], $anonymous->getTags());
    }

    public function testNamedArguments()
    {
        $container = new ContainerBuilder();
        $loader = new XmlFileLoader($container, new FileLocator(self::$fixturesPath.'/xml'));
        $loader->load('services_named_args.xml');

        $this->assertEquals(['$apiKey' => 'ABCD', CaseSensitiveClass::class => null], $container->getDefinition(NamedArgumentsDummy::class)->getArguments());

        $container->compile();

        $this->assertEquals([null, 'ABCD'], $container->getDefinition(NamedArgumentsDummy::class)->getArguments());
        $this->assertEquals([['setApiKey', ['123']]], $container->getDefinition(NamedArgumentsDummy::class)->getMethodCalls());
    }

    public function testInstanceof()
    {
        $container = new ContainerBuilder();
        $loader = new XmlFileLoader($container, new FileLocator(self::$fixturesPath.'/xml'));
        $loader->load('services_instanceof.xml');
        $container->compile();

        $definition = $container->getDefinition(Bar::class);
        $this->assertTrue($definition->isAutowired());
        $this->assertTrue($definition->isLazy());
        $this->assertSame(['foo' => [[]], 'bar' => [[]]], $definition->getTags());
    }

    public function testInstanceOfAndChildDefinitionNotAllowed()
    {
        $this->expectException('Symfony\Component\DependencyInjection\Exception\InvalidArgumentException');
        $this->expectExceptionMessage('The service "child_service" cannot use the "parent" option in the same file where "instanceof" configuration is defined as using both is not supported. Move your child definitions to a separate file.');
        $container = new ContainerBuilder();
        $loader = new XmlFileLoader($container, new FileLocator(self::$fixturesPath.'/xml'));
        $loader->load('services_instanceof_with_parent.xml');
        $container->compile();
    }

    public function testAutoConfigureAndChildDefinitionNotAllowed()
    {
        $this->expectException('Symfony\Component\DependencyInjection\Exception\InvalidArgumentException');
        $this->expectExceptionMessage('The service "child_service" cannot have a "parent" and also have "autoconfigure". Try setting autoconfigure="false" for the service.');
        $container = new ContainerBuilder();
        $loader = new XmlFileLoader($container, new FileLocator(self::$fixturesPath.'/xml'));
        $loader->load('services_autoconfigure_with_parent.xml');
        $container->compile();
    }

    public function testDefaultsAndChildDefinitionNotAllowed()
    {
        $this->expectException('Symfony\Component\DependencyInjection\Exception\InvalidArgumentException');
        $this->expectExceptionMessage('Attribute "autowire" on service "child_service" cannot be inherited from "defaults" when a "parent" is set. Move your child definitions to a separate file or define this attribute explicitly.');
        $container = new ContainerBuilder();
        $loader = new XmlFileLoader($container, new FileLocator(self::$fixturesPath.'/xml'));
        $loader->load('services_defaults_with_parent.xml');
        $container->compile();
    }

    public function testAutoConfigureInstanceof()
    {
        $container = new ContainerBuilder();
        $loader = new XmlFileLoader($container, new FileLocator(self::$fixturesPath.'/xml'));
        $loader->load('services_autoconfigure.xml');

        $this->assertTrue($container->getDefinition('use_defaults_settings')->isAutoconfigured());
        $this->assertFalse($container->getDefinition('override_defaults_settings_to_false')->isAutoconfigured());
    }

    public function testCaseSensitivity()
    {
        $container = new ContainerBuilder();
        $loader = new XmlFileLoader($container, new FileLocator(self::$fixturesPath.'/xml'));
        $loader->load('services_case.xml');
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
        $loader = new XmlFileLoader($container, new FileLocator(self::$fixturesPath.'/xml'));
        $loader->load('services_bindings.xml');
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

    public function testFqcnLazyProxy()
    {
        $container = new ContainerBuilder();
        $loader = new XmlFileLoader($container, new FileLocator(self::$fixturesPath.'/xml'));
        $loader->load('services_lazy_fqcn.xml');

        $definition = $container->getDefinition('foo');
        $this->assertSame([['interface' => 'SomeInterface']], $definition->getTag('proxy'));
    }

    public function testTsantosContainer()
    {
        $container = new ContainerBuilder();
        $loader = new XmlFileLoader($container, new FileLocator(self::$fixturesPath.'/xml'));
        $loader->load('services_tsantos.xml');
        $container->compile();

        $dumper = new PhpDumper($container);
        $this->assertStringEqualsFile(self::$fixturesPath.'/php/services_tsantos.php', $dumper->dump());
    }

    /**
     * The pass may throw an exception, which will cause the test to fail.
     */
    public function testOverriddenDefaultsBindings()
    {
        $container = new ContainerBuilder();

        $loader = new XmlFileLoader($container, new FileLocator(self::$fixturesPath.'/xml'));
        $loader->load('defaults_bindings.xml');
        $loader->load('defaults_bindings2.xml');

        (new ResolveBindingsPass())->process($container);

        $this->assertSame('overridden', $container->get('bar')->quz);
    }

    public function testReturnsClone()
    {
        $container = new ContainerBuilder();
        $loader = new XmlFileLoader($container, new FileLocator(self::$fixturesPath.'/xml'));
        $loader->load('returns_clone.xml');

        $this->assertSame([['bar', [], true]], $container->getDefinition('foo')->getMethodCalls());
    }

    public function testSinglyImplementedInterfacesInMultipleResources()
    {
        $container = new ContainerBuilder();

        $loader = new XmlFileLoader($container, new FileLocator(self::$fixturesPath.'/xml'));
        $loader->load('singly_implemented_interface_in_multiple_resources.xml');

        $alias = $container->getAlias(Prototype\SinglyImplementedInterface\Port\PortInterface::class);

        $this->assertSame(Prototype\SinglyImplementedInterface\Adapter\Adapter::class, (string) $alias);
    }

    public function testNotSinglyImplementedInterfacesInMultipleResources()
    {
        $container = new ContainerBuilder();

        $loader = new XmlFileLoader($container, new FileLocator(self::$fixturesPath.'/xml'));
        $loader->load('not_singly_implemented_interface_in_multiple_resources.xml');

        $this->assertFalse($container->hasAlias(Prototype\SinglyImplementedInterface\Port\PortInterface::class));
    }

    public function testNotSinglyImplementedInterfacesInMultipleResourcesWithPreviouslyRegisteredAlias()
    {
        $container = new ContainerBuilder();

        $loader = new XmlFileLoader($container, new FileLocator(self::$fixturesPath.'/xml'));
        $loader->load('not_singly_implemented_interface_in_multiple_resources_with_previously_registered_alias.xml');

        $alias = $container->getAlias(Prototype\SinglyImplementedInterface\Port\PortInterface::class);

        $this->assertSame(Prototype\SinglyImplementedInterface\Adapter\Adapter::class, (string) $alias);
    }

    public function testNotSinglyImplementedInterfacesInMultipleResourcesWithPreviouslyRegisteredAlias2()
    {
        $container = new ContainerBuilder();

        $loader = new XmlFileLoader($container, new FileLocator(self::$fixturesPath.'/xml'));
        $loader->load('not_singly_implemented_interface_in_multiple_resources_with_previously_registered_alias2.xml');

        $alias = $container->getAlias(Prototype\SinglyImplementedInterface\Port\PortInterface::class);

        $this->assertSame(Prototype\SinglyImplementedInterface\Adapter\Adapter::class, (string) $alias);
    }
}
