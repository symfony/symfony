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
use Symfony\Bridge\PhpUnit\ExpectUserDeprecationMessageTrait;
use Symfony\Component\Config\Exception\FileLocatorFileNotFoundException;
use Symfony\Component\Config\Exception\LoaderLoadException;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\Config\Loader\LoaderResolver;
use Symfony\Component\Config\Resource\FileResource;
use Symfony\Component\Config\Resource\GlobResource;
use Symfony\Component\Config\Util\Exception\XmlParsingException;
use Symfony\Component\DependencyInjection\Argument\AbstractArgument;
use Symfony\Component\DependencyInjection\Argument\BoundArgument;
use Symfony\Component\DependencyInjection\Argument\IteratorArgument;
use Symfony\Component\DependencyInjection\Argument\ServiceClosureArgument;
use Symfony\Component\DependencyInjection\Argument\ServiceLocatorArgument;
use Symfony\Component\DependencyInjection\Argument\TaggedIteratorArgument;
use Symfony\Component\DependencyInjection\Compiler\ResolveBindingsPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Dumper\PhpDumper;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;
use Symfony\Component\DependencyInjection\Exception\LogicException;
use Symfony\Component\DependencyInjection\Loader\IniFileLoader;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\DependencyInjection\Tests\Fixtures\Bar;
use Symfony\Component\DependencyInjection\Tests\Fixtures\BarInterface;
use Symfony\Component\DependencyInjection\Tests\Fixtures\CaseSensitiveClass;
use Symfony\Component\DependencyInjection\Tests\Fixtures\FooClassWithEnumAttribute;
use Symfony\Component\DependencyInjection\Tests\Fixtures\FooUnitEnum;
use Symfony\Component\DependencyInjection\Tests\Fixtures\FooWithAbstractArgument;
use Symfony\Component\DependencyInjection\Tests\Fixtures\NamedArgumentsDummy;
use Symfony\Component\DependencyInjection\Tests\Fixtures\Prototype;
use Symfony\Component\DependencyInjection\Tests\Fixtures\RemoteCaller;
use Symfony\Component\DependencyInjection\Tests\Fixtures\RemoteCallerHttp;
use Symfony\Component\DependencyInjection\Tests\Fixtures\RemoteCallerSocket;
use Symfony\Component\ExpressionLanguage\Expression;

class XmlFileLoaderTest extends TestCase
{
    use ExpectUserDeprecationMessageTrait;

    protected static string $fixturesPath;

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
            $this->assertInstanceOf(\InvalidArgumentException::class, $e, '->load() throws an InvalidArgumentException if the loaded file does not exist');
            $this->assertStringStartsWith('The file "foo.xml" does not exist (in:', $e->getMessage(), '->load() throws an InvalidArgumentException if the loaded file does not exist');
        }
    }

    public function testParseFile()
    {
        $loader = new XmlFileLoader(new ContainerBuilder(), new FileLocator(self::$fixturesPath.'/ini'));
        $r = new \ReflectionObject($loader);
        $m = $r->getMethod('parseFileToDOM');

        try {
            $m->invoke($loader, self::$fixturesPath.'/ini/parameters.ini');
            $this->fail('->parseFileToDOM() throws an InvalidArgumentException if the loaded file is not a valid XML file');
        } catch (\Exception $e) {
            $this->assertInstanceOf(InvalidArgumentException::class, $e, '->parseFileToDOM() throws an InvalidArgumentException if the loaded file is not a valid XML file');
            $this->assertMatchesRegularExpression(\sprintf('#^Unable to parse file ".+%s": .+.$#', 'parameters.ini'), $e->getMessage(), '->parseFileToDOM() throws an InvalidArgumentException if the loaded file is not a valid XML file');

            $e = $e->getPrevious();
            $this->assertInstanceOf(\InvalidArgumentException::class, $e, '->parseFileToDOM() throws an InvalidArgumentException if the loaded file is not a valid XML file');
            $this->assertStringStartsWith('[ERROR 4] Start tag expected, \'<\' not found (in', $e->getMessage(), '->parseFileToDOM() throws an InvalidArgumentException if the loaded file is not a valid XML file');
        }

        $loader = new XmlFileLoader(new ContainerBuilder(), new FileLocator(self::$fixturesPath.'/xml'));

        try {
            $m->invoke($loader, self::$fixturesPath.'/xml/nonvalid.xml');
            $this->fail('->parseFileToDOM() throws an InvalidArgumentException if the loaded file does not validate the XSD');
        } catch (\Exception $e) {
            $this->assertInstanceOf(InvalidArgumentException::class, $e, '->parseFileToDOM() throws an InvalidArgumentException if the loaded file does not validate the XSD');
            $this->assertMatchesRegularExpression(\sprintf('#^Unable to parse file ".+%s": .+.$#', 'nonvalid.xml'), $e->getMessage(), '->parseFileToDOM() throws an InvalidArgumentException if the loaded file is not a valid XML file');

            $e = $e->getPrevious();
            $this->assertInstanceOf(\InvalidArgumentException::class, $e, '->parseFileToDOM() throws an InvalidArgumentException if the loaded file does not validate the XSD');
            $this->assertStringStartsWith('[ERROR 1845] Element \'nonvalid\': No matching global declaration available for the validation root. (in', $e->getMessage(), '->parseFileToDOM() throws an InvalidArgumentException if the loaded file does not validate the XSD');
        }

        $xml = $m->invoke($loader, self::$fixturesPath.'/xml/services1.xml');
        $this->assertInstanceOf(\DOMDocument::class, $xml, '->parseFileToDOM() returns an SimpleXMLElement object');
    }

    public function testLoadWithExternalEntitiesDisabled()
    {
        $container = new ContainerBuilder();
        $loader = new XmlFileLoader($container, new FileLocator(self::$fixturesPath.'/xml'));
        $loader->load('services2.xml');

        $this->assertGreaterThan(0, $container->getParameterBag()->all(), 'Parameters can be read from the config file.');
    }

    public function testLoadParameters()
    {
        $container = new ContainerBuilder();
        $loader = new XmlFileLoader($container, new FileLocator(self::$fixturesPath.'/xml'));
        $loader->load('services2.xml');

        $actual = $container->getParameterBag()->all();
        $expected = [
            'a_string' => 'a string',
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
                ' a string not trimmed ',
                'a trimmed string',
                'an explicit trimmed string',
                ['foo', 'bar'],
            ],
            'mixedcase' => ['MixedCaseKey' => 'value'],
            'constant' => \PHP_EOL,
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
            'a_string' => 'a string',
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
            'constant' => \PHP_EOL,
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
            $this->assertInstanceOf(LoaderLoadException::class, $e, '->load() throws a LoaderLoadException if the imported xml file configuration does not exist');
            $this->assertMatchesRegularExpression(\sprintf('#^The file "%1$s" does not exist \(in: .+\) in %1$s \(which is being imported from ".+%2$s"\)\.$#', 'foo_fake\.xml', 'services4_bad_import_with_errors\.xml'), $e->getMessage(), '->load() throws a LoaderLoadException if the imported xml file configuration does not exist');

            $e = $e->getPrevious();
            $this->assertInstanceOf(FileLocatorFileNotFoundException::class, $e, '->load() throws a FileLocatorFileNotFoundException if the imported xml file configuration does not exist');
            $this->assertMatchesRegularExpression(\sprintf('#^The file "%s" does not exist \(in: .+\)\.$#', 'foo_fake\.xml'), $e->getMessage(), '->load() throws a FileLocatorFileNotFoundException if the imported xml file configuration does not exist');
        }

        try {
            $loader->load('services4_bad_import_nonvalid.xml');
            $this->fail('->load() throws an LoaderLoadException if the imported configuration does not validate the XSD');
        } catch (\Exception $e) {
            $this->assertInstanceOf(LoaderLoadException::class, $e, '->load() throws a LoaderLoadException if the imported configuration does not validate the XSD');
            $this->assertMatchesRegularExpression(\sprintf('#^Unable to parse file ".+%s": .+.$#', 'nonvalid\.xml'), $e->getMessage(), '->load() throws a LoaderLoadException if the imported configuration does not validate the XSD');

            $e = $e->getPrevious();
            $this->assertInstanceOf(InvalidArgumentException::class, $e, '->load() throws an InvalidArgumentException if the configuration does not validate the XSD');
            $this->assertMatchesRegularExpression(\sprintf('#^Unable to parse file ".+%s": .+.$#', 'nonvalid\.xml'), $e->getMessage(), '->load() throws an InvalidArgumentException if the configuration does not validate the XSD');

            $e = $e->getPrevious();
            $this->assertInstanceOf(XmlParsingException::class, $e, '->load() throws a XmlParsingException if the configuration does not validate the XSD');
            $this->assertStringStartsWith('[ERROR 1845] Element \'nonvalid\': No matching global declaration available for the validation root. (in', $e->getMessage(), '->load() throws a XmlParsingException if the loaded file does not validate the XSD');
        }
    }

    public function testLoadWithEnvironment()
    {
        $container = new ContainerBuilder();

        $loader = new XmlFileLoader($container, new FileLocator(self::$fixturesPath.'/xml'), 'dev');
        $loader->load('services29.xml');

        self::assertSame([
            'imported_parameter' => 'value when on dev',
            'root_parameter' => 'value when on dev',
        ], $container->getParameterBag()->all());

        $loader = new XmlFileLoader($container, new FileLocator(self::$fixturesPath.'/xml'), 'test');
        $loader->load('services29.xml');

        self::assertSame([
            'imported_parameter' => 'value when on test',
            'root_parameter' => 'value when on test',
        ], $container->getParameterBag()->all());

        $loader = new XmlFileLoader($container, new FileLocator(self::$fixturesPath.'/xml'), 'prod');
        $loader->load('services29.xml');

        self::assertSame([
            'imported_parameter' => 'value when on prod',
            'root_parameter' => 'value when on prod',
        ], $container->getParameterBag()->all());

        $loader = new XmlFileLoader($container, new FileLocator(self::$fixturesPath.'/xml'), 'other');
        $loader->load('services29.xml');

        self::assertSame([
            'imported_parameter' => 'default value',
            'root_parameter' => 'default value',
        ], $container->getParameterBag()->all());
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
        $this->assertInstanceOf(Reference::class, $args[0], '->load() converts anonymous services to references to "normal" services');
        $this->assertArrayHasKey((string) $args[0], $services, '->load() makes a reference to the created ones');
        $inner = $services[(string) $args[0]];
        $this->assertEquals('BarClass', $inner->getClass(), '->load() uses the same configuration as for the anonymous ones');
        $this->assertFalse($inner->isPublic());

        // inner anonymous services
        $args = $inner->getArguments();
        $this->assertCount(1, $args, '->load() references anonymous services as "normal" ones');
        $this->assertInstanceOf(Reference::class, $args[0], '->load() converts anonymous services to references to "normal" services');
        $this->assertArrayHasKey((string) $args[0], $services, '->load() makes a reference to the created ones');
        $inner = $services[(string) $args[0]];
        $this->assertEquals('BazClass', $inner->getClass(), '->load() uses the same configuration as for the anonymous ones');
        $this->assertFalse($inner->isPublic());

        // anonymous service as a property
        $properties = $services['foo']->getProperties();
        $property = $properties['p'];
        $this->assertInstanceOf(Reference::class, $property, '->load() converts anonymous services to references to "normal" services');
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
        $this->expectException(InvalidArgumentException::class);
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
        $this->assertInstanceOf(Definition::class, $services['foo'], '->load() converts <service> element to Definition instances');
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
        $this->assertFalse($aliases['alias_for_foo']->isPublic());
        $this->assertArrayHasKey('another_alias_for_foo', $aliases);
        $this->assertEquals('foo', (string) $aliases['another_alias_for_foo']);
        $this->assertTrue($aliases['another_alias_for_foo']->isPublic());

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

    /**
     * @testWith ["foo_tag"]
     *           ["bar_tag"]
     */
    public function testParsesTags(string $tag)
    {
        $container = new ContainerBuilder();
        $loader = new XmlFileLoader($container, new FileLocator(self::$fixturesPath.'/xml'));
        $loader->load('services10.xml');

        $services = $container->findTaggedServiceIds($tag);
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
        $taggedIterator2 = new TaggedIteratorArgument('foo_tag', null, null, false, null, ['baz']);
        $this->assertEquals($taggedIterator2, $container->getDefinition('foo2_tagged_iterator')->getArgument(0));
        $taggedIterator3 = new TaggedIteratorArgument('foo_tag', null, null, false, null, ['baz', 'qux'], false);
        $this->assertEquals($taggedIterator3, $container->getDefinition('foo3_tagged_iterator')->getArgument(0));

        $taggedIterator = new TaggedIteratorArgument('foo_tag', 'barfoo', 'foobar', true, 'getPriority');
        $this->assertEquals(new ServiceLocatorArgument($taggedIterator), $container->getDefinition('foo_tagged_locator')->getArgument(0));
        $taggedIterator2 = new TaggedIteratorArgument('foo_tag', 'foo_tag', 'getDefaultFooTagName', true, 'getDefaultFooTagPriority', ['baz']);
        $this->assertEquals(new ServiceLocatorArgument($taggedIterator2), $container->getDefinition('foo2_tagged_locator')->getArgument(0));
        $taggedIterator3 = new TaggedIteratorArgument('foo_tag', 'foo_tag', 'getDefaultFooTagName', true, 'getDefaultFooTagPriority', ['baz', 'qux'], false);
        $this->assertEquals(new ServiceLocatorArgument($taggedIterator3), $container->getDefinition('foo3_tagged_locator')->getArgument(0));
    }

    public function testServiceWithServiceLocatorArgument()
    {
        $container = new ContainerBuilder();
        $loader = new XmlFileLoader($container, new FileLocator(self::$fixturesPath.'/xml'));
        $loader->load('services_with_service_locator_argument.xml');

        $values = ['foo' => new Reference('foo_service'), 'bar' => new Reference('bar_service')];
        $this->assertEquals([new ServiceLocatorArgument($values)], $container->getDefinition('locator_dependent_service_indexed')->getArguments());

        $values = [new Reference('foo_service'), new Reference('bar_service')];
        $this->assertEquals([new ServiceLocatorArgument($values)], $container->getDefinition('locator_dependent_service_not_indexed')->getArguments());

        $values = ['foo' => new Reference('foo_service'), 0 => new Reference('bar_service')];
        $this->assertEquals([new ServiceLocatorArgument($values)], $container->getDefinition('locator_dependent_service_mixed')->getArguments());

        $inlinedServiceArguments = $container->getDefinition('locator_dependent_inline_service')->getArguments();
        $this->assertEquals((new Definition(\stdClass::class))->setPublic(false), $container->getDefinition((string) $inlinedServiceArguments[0]->getValues()['foo']));
        $this->assertEquals((new Definition(\stdClass::class))->setPublic(false), $container->getDefinition((string) $inlinedServiceArguments[0]->getValues()['bar']));
    }

    public function testParseServiceClosure()
    {
        $container = new ContainerBuilder();
        $loader = new XmlFileLoader($container, new FileLocator(self::$fixturesPath.'/xml'));
        $loader->load('services_with_service_closure.xml');

        $this->assertEquals(new ServiceClosureArgument(new Reference('bar', ContainerInterface::IGNORE_ON_INVALID_REFERENCE)), $container->getDefinition('foo')->getArgument(0));
    }

    public function testParseServiceTagsWithArrayAttributes()
    {
        $container = new ContainerBuilder();
        $loader = new XmlFileLoader($container, new FileLocator(self::$fixturesPath.'/xml'));
        $loader->load('services_with_array_tags.xml');

        $this->assertEquals(['foo_tag' => [['name' => 'attributeName', 'foo' => 'bar', 'bar' => ['foo' => 'bar', 'bar' => 'foo']]]], $container->getDefinition('foo')->getTags());
    }

    public function testParseTagsWithoutNameThrowsException()
    {
        $this->expectException(InvalidArgumentException::class);
        $container = new ContainerBuilder();
        $loader = new XmlFileLoader($container, new FileLocator(self::$fixturesPath.'/xml'));
        $loader->load('tag_without_name.xml');
    }

    public function testParseTagWithEmptyNameThrowsException()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/The tag name for service ".+" in .* must be a non-empty string/');
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
        $this->assertSame($message, $container->getDefinition('foo')->getDeprecation('foo')['message']);

        $this->assertTrue($container->getDefinition('bar')->isDeprecated());
        $message = 'The "bar" service is deprecated.';
        $this->assertSame($message, $container->getDefinition('bar')->getDeprecation('bar')['message']);
    }

    public function testDeprecatedAliases()
    {
        $container = new ContainerBuilder();
        $loader = new XmlFileLoader($container, new FileLocator(self::$fixturesPath.'/xml'));
        $loader->load('deprecated_alias_definitions.xml');

        $this->assertTrue($container->getAlias('alias_for_foo')->isDeprecated());
        $message = 'The "alias_for_foo" service alias is deprecated. You should stop using it, as it will be removed in the future.';
        $this->assertSame($message, $container->getAlias('alias_for_foo')->getDeprecation('alias_for_foo')['message']);

        $this->assertTrue($container->getAlias('alias_for_foobar')->isDeprecated());
        $message = 'The "alias_for_foobar" service alias is deprecated.';
        $this->assertSame($message, $container->getAlias('alias_for_foobar')->getDeprecation('alias_for_foobar')['message']);
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
            $this->assertInstanceOf(InvalidArgumentException::class, $e, '->load() throws an InvalidArgumentException if the configuration does not validate the XSD');
            $this->assertMatchesRegularExpression(\sprintf('#^Unable to parse file ".+%s": .+.$#', 'services3.xml'), $e->getMessage(), '->load() throws an InvalidArgumentException if the configuration does not validate the XSD');

            $e = $e->getPrevious();
            $this->assertInstanceOf(\InvalidArgumentException::class, $e, '->load() throws an InvalidArgumentException if the configuration does not validate the XSD');
            $this->assertStringContainsString('The attribute \'bar\' is not allowed', $e->getMessage(), '->load() throws an InvalidArgumentException if the configuration does not validate the XSD');
        }

        // non-registered extension
        try {
            $loader->load('extensions/services4.xml');
            $this->fail('->load() throws an InvalidArgumentException if the tag is not valid');
        } catch (\Exception $e) {
            $this->assertInstanceOf(\InvalidArgumentException::class, $e, '->load() throws an InvalidArgumentException if the tag is not valid');
            $this->assertStringStartsWith('There is no extension able to load the configuration for "project:bar" (in', $e->getMessage(), '->load() throws an InvalidArgumentException if the tag is not valid');
        }
    }

    public function testPrependExtensionConfig()
    {
        $container = new ContainerBuilder();
        $container->prependExtensionConfig('http://www.example.com/schema/project', ['foo' => 'bar']);
        $loader = new XmlFileLoader($container, new FileLocator(self::$fixturesPath.'/xml'), prepend: true);
        $loader->load('extensions/services1.xml');

        $expected = [
            [
                'foo' => 'ping',
                'another' => null,
                'another2' => '%project.parameter.foo%',
            ],
            ['foo' => 'bar'],
        ];
        $this->assertSame($expected, $container->getExtensionConfig('http://www.example.com/schema/project'));
    }

    public function testExtensionInPhar()
    {
        if (\extension_loaded('suhosin') && !str_contains(\ini_get('suhosin.executor.include.whitelist'), 'phar')) {
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
            $this->assertInstanceOf(InvalidArgumentException::class, $e, '->load() throws an InvalidArgumentException if the configuration does not validate the XSD');
            $this->assertMatchesRegularExpression(\sprintf('#^Unable to parse file ".+%s": .+.$#', 'services7.xml'), $e->getMessage(), '->load() throws an InvalidArgumentException if the configuration does not validate the XSD');

            $e = $e->getPrevious();
            $this->assertInstanceOf(\InvalidArgumentException::class, $e, '->load() throws an InvalidArgumentException if the configuration does not validate the XSD');
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
            $this->assertInstanceOf(InvalidArgumentException::class, $e, '->load() throws an InvalidArgumentException if the configuration contains a document type');
            $this->assertMatchesRegularExpression(\sprintf('#^Unable to parse file ".+%s": .+.$#', 'withdoctype.xml'), $e->getMessage(), '->load() throws an InvalidArgumentException if the configuration contains a document type');

            $e = $e->getPrevious();
            $this->assertInstanceOf(\InvalidArgumentException::class, $e, '->load() throws an InvalidArgumentException if the configuration contains a document type');
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

        $ids = array_keys(array_filter($container->getDefinitions(), fn ($def) => !$def->hasTag('container.excluded')));
        sort($ids);
        $this->assertSame([Prototype\Foo::class, Prototype\NotFoo::class, Prototype\Sub\Bar::class, 'service_container'], $ids);

        $resources = array_map('strval', $container->getResources());

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
                str_replace(\DIRECTORY_SEPARATOR, '/', $prototypeRealPath.\DIRECTORY_SEPARATOR.'BadAttributes') => true,
                str_replace(\DIRECTORY_SEPARATOR, '/', $prototypeRealPath.\DIRECTORY_SEPARATOR.'SinglyImplementedInterface') => true,
                str_replace(\DIRECTORY_SEPARATOR, '/', $prototypeRealPath.\DIRECTORY_SEPARATOR.'StaticConstructor') => true,
            ]
        );
        $this->assertContains((string) $globResource, $resources);
        $this->assertContains('reflection.Symfony\Component\DependencyInjection\Tests\Fixtures\Prototype\Foo', $resources);
        $this->assertContains('reflection.Symfony\Component\DependencyInjection\Tests\Fixtures\Prototype\Sub\Bar', $resources);
    }

    /**
     * @dataProvider prototypeExcludeWithArrayDataProvider
     */
    public function testPrototypeExcludeWithArray(string $fileName)
    {
        $container = new ContainerBuilder();
        $loader = new XmlFileLoader($container, new FileLocator(self::$fixturesPath.'/xml'));
        $loader->load($fileName);

        $ids = array_keys(array_filter($container->getDefinitions(), fn ($def) => !$def->hasTag('container.excluded')));
        sort($ids);
        $this->assertSame([Prototype\Foo::class, Prototype\NotFoo::class, Prototype\Sub\Bar::class, 'service_container'], $ids);

        $resources = array_map('strval', $container->getResources());

        $fixturesDir = \dirname(__DIR__).\DIRECTORY_SEPARATOR.'Fixtures'.\DIRECTORY_SEPARATOR;
        $this->assertContains((string) new FileResource($fixturesDir.'xml'.\DIRECTORY_SEPARATOR.$fileName), $resources);

        $prototypeRealPath = realpath(__DIR__.\DIRECTORY_SEPARATOR.'..'.\DIRECTORY_SEPARATOR.'Fixtures'.\DIRECTORY_SEPARATOR.'Prototype');
        $globResource = new GlobResource(
            $fixturesDir.'Prototype',
            '/*',
            true,
            false,
            [
                str_replace(\DIRECTORY_SEPARATOR, '/', $prototypeRealPath.\DIRECTORY_SEPARATOR.'BadClasses') => true,
                str_replace(\DIRECTORY_SEPARATOR, '/', $prototypeRealPath.\DIRECTORY_SEPARATOR.'BadAttributes') => true,
                str_replace(\DIRECTORY_SEPARATOR, '/', $prototypeRealPath.\DIRECTORY_SEPARATOR.'OtherDir') => true,
                str_replace(\DIRECTORY_SEPARATOR, '/', $prototypeRealPath.\DIRECTORY_SEPARATOR.'SinglyImplementedInterface') => true,
                str_replace(\DIRECTORY_SEPARATOR, '/', $prototypeRealPath.\DIRECTORY_SEPARATOR.'StaticConstructor') => true,
            ]
        );
        $this->assertContains((string) $globResource, $resources);
        $this->assertContains('reflection.Symfony\Component\DependencyInjection\Tests\Fixtures\Prototype\Foo', $resources);
        $this->assertContains('reflection.Symfony\Component\DependencyInjection\Tests\Fixtures\Prototype\Sub\Bar', $resources);
    }

    public static function prototypeExcludeWithArrayDataProvider(): iterable
    {
        return [
            ['services_prototype_array.xml'],
            // Same config than above but “<exclude> </exclude>” has been added
            ['services_prototype_array_with_space_node.xml'],
        ];
    }

    public function testPrototypeExcludeWithArrayWithEmptyNode()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The exclude list must not contain an empty value.');

        $container = new ContainerBuilder();
        $loader = new XmlFileLoader($container, new FileLocator(self::$fixturesPath.'/xml'));
        $loader->load('services_prototype_array_with_empty_node.xml');
    }

    public function testAliasDefinitionContainsUnsupportedElements()
    {
        $this->expectException(InvalidArgumentException::class);
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

    public function testEnumeration()
    {
        $container = new ContainerBuilder();
        $loader = new XmlFileLoader($container, new FileLocator(self::$fixturesPath.'/xml'));
        $loader->load('services_with_enumeration.xml');
        $container->compile();

        $definition = $container->getDefinition(FooClassWithEnumAttribute::class);
        $this->assertSame([FooUnitEnum::BAR], $definition->getArguments());
    }

    public function testInvalidEnumeration()
    {
        $container = new ContainerBuilder();
        $loader = new XmlFileLoader($container, new FileLocator(self::$fixturesPath.'/xml'));

        $this->expectException(\Error::class);
        $loader->load('services_with_invalid_enumeration.xml');
    }

    public function testInstanceOfAndChildDefinition()
    {
        $container = new ContainerBuilder();
        $loader = new XmlFileLoader($container, new FileLocator(self::$fixturesPath.'/xml'));
        $loader->load('services_instanceof_with_parent.xml');
        $container->compile();

        $this->assertTrue($container->getDefinition('child_service')->isAutowired());
    }

    public function testAutoConfigureAndChildDefinition()
    {
        $container = new ContainerBuilder();
        $loader = new XmlFileLoader($container, new FileLocator(self::$fixturesPath.'/xml'));
        $loader->load('services_autoconfigure_with_parent.xml');
        $container->compile();

        $this->assertTrue($container->getDefinition('child_service')->isAutoconfigured());
    }

    public function testDefaultsAndChildDefinition()
    {
        $container = new ContainerBuilder();
        $loader = new XmlFileLoader($container, new FileLocator(self::$fixturesPath.'/xml'));
        $loader->load('services_defaults_with_parent.xml');
        $container->compile();

        $this->assertTrue($container->getDefinition('child_service')->isAutowired());
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
            'iterable $baz' => new TaggedIteratorArgument('bar'),
        ], array_map(fn (BoundArgument $v) => $v->getValues()[0], $definition->getBindings()));
        $this->assertEquals([
            'quz',
            null,
            new Reference(Bar::class),
            [null],
            new TaggedIteratorArgument('bar'),
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
        ], array_map(fn (BoundArgument $v) => $v->getValues()[0], $definition->getBindings()));
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

    public function testLoadServiceWithAbstractArgument()
    {
        $container = new ContainerBuilder();
        $loader = new XmlFileLoader($container, new FileLocator(self::$fixturesPath.'/xml'));
        $loader->load('service_with_abstract_argument.xml');

        $this->assertTrue($container->hasDefinition(FooWithAbstractArgument::class));
        $arguments = $container->getDefinition(FooWithAbstractArgument::class)->getArguments();
        $this->assertInstanceOf(AbstractArgument::class, $arguments['$baz']);
    }

    public function testStack()
    {
        $container = new ContainerBuilder();

        $loader = new XmlFileLoader($container, new FileLocator(self::$fixturesPath.'/xml'));
        $loader->load('stack.xml');

        $container->compile();

        $expected = (object) [
            'label' => 'A',
            'inner' => (object) [
                'label' => 'B',
                'inner' => (object) [
                    'label' => 'C',
                ],
            ],
        ];
        $this->assertEquals($expected, $container->get('stack_a'));
        $this->assertEquals($expected, $container->get('stack_b'));

        $expected = (object) [
            'label' => 'Z',
            'inner' => $expected,
        ];
        $this->assertEquals($expected, $container->get('stack_c'));

        $expected = $expected->inner;
        $expected->label = 'Z';
        $this->assertEquals($expected, $container->get('stack_d'));
    }

    public function testWhenEnv()
    {
        $container = new ContainerBuilder();
        $loader = new XmlFileLoader($container, new FileLocator(self::$fixturesPath.'/xml'), 'some-env');
        $loader->load('when-env.xml');

        $this->assertSame(['foo' => 234, 'bar' => 345], $container->getParameterBag()->all());
    }

    public function testClosure()
    {
        $container = new ContainerBuilder();
        $loader = new XmlFileLoader($container, new FileLocator(self::$fixturesPath.'/xml'));
        $loader->load('closure.xml');

        $definition = $container->getDefinition('closure_property')->getProperties()['foo'];
        $this->assertEquals((new Definition('Closure'))->setFactory(['Closure', 'fromCallable'])->addArgument(new Reference('bar')), $definition);
    }

    /**
     * @dataProvider dataForBindingsAndInnerCollections
     */
    public function testBindingsAndInnerCollections($bindName, $expected)
    {
        $container = new ContainerBuilder();
        $loader = new XmlFileLoader($container, new FileLocator(self::$fixturesPath.'/xml'));
        $loader->load('bindings_and_inner_collections.xml');
        (new ResolveBindingsPass())->process($container);
        $definition = $container->getDefinition($bindName);
        $actual = $definition->getBindings()['$foo']->getValues()[0];
        $this->assertEquals($actual, $expected);
    }

    public static function dataForBindingsAndInnerCollections()
    {
        return [
            ['bar1', ['item.1', 'item.2']],
            ['bar2', ['item.1', 'item.2']],
            ['bar3', ['item.1', 'item.2', 'item.3', 'item.4']],
            ['bar4', ['item.1', 'item.3', 'item.4']],
            ['bar5', ['item.1', 'item.2', ['item.3.1', 'item.3.2']]],
            ['bar6', ['item.1', ['item.2.1', 'item.2.2'], 'item.3']],
            ['bar7', new IteratorArgument(['item.1', 'item.2'])],
            ['bar8', new IteratorArgument(['item.1', 'item.2', ['item.3.1', 'item.3.2']])],
        ];
    }

    public function testTagNameAttribute()
    {
        $container = new ContainerBuilder();
        $loader = new XmlFileLoader($container, new FileLocator(self::$fixturesPath.'/xml'));
        $loader->load('tag_with_name_attribute.xml');

        $definition = $container->getDefinition('foo');
        $this->assertSame([['name' => 'name_attribute']], $definition->getTag('tag_name'));
    }

    public function testFromCallable()
    {
        $container = new ContainerBuilder();
        $loader = new XmlFileLoader($container, new FileLocator(self::$fixturesPath.'/xml'));
        $loader->load('from_callable.xml');

        $definition = $container->getDefinition('from_callable');
        $this->assertEquals((new Definition('stdClass'))->setFactory(['Closure', 'fromCallable'])->addArgument([new Reference('bar'), 'do'])->setLazy(true), $definition);
    }

    public function testStaticConstructor()
    {
        $container = new ContainerBuilder();
        $loader = new XmlFileLoader($container, new FileLocator(self::$fixturesPath.'/xml'));
        $loader->load('static_constructor.xml');

        $definition = $container->getDefinition('static_constructor');
        $this->assertEquals((new Definition('stdClass'))->setFactory([null, 'create']), $definition);
    }

    public function testStaticConstructorWithFactoryThrows()
    {
        $container = new ContainerBuilder();
        $loader = new XmlFileLoader($container, new FileLocator(self::$fixturesPath . '/xml'));

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('The "static_constructor" service cannot declare a factory as well as a constructor.');
        $loader->load('static_constructor_and_factory.xml');
    }

    public function testArgumentKeyType()
    {
        $container = new ContainerBuilder();
        $loader = new XmlFileLoader($container, new FileLocator(self::$fixturesPath.'/xml'));
        $loader->load('key_type_argument.xml');

        $definition = $container->getDefinition('foo');
        $this->assertSame([
            \PHP_INT_MAX => 'Value 1',
            'PHP_INT_MAX' => 'Value 2',
            "\x01\x02\x03" => 'Value 3',
        ], $definition->getArgument(0));
    }

    public function testPropertyKeyType()
    {
        $container = new ContainerBuilder();
        $loader = new XmlFileLoader($container, new FileLocator(self::$fixturesPath.'/xml'));
        $loader->load('key_type_property.xml');

        $definition = $container->getDefinition('foo');
        $this->assertSame([
            \PHP_INT_MAX => 'Value 1',
            'PHP_INT_MAX' => 'Value 2',
            "\x01\x02\x03" => 'Value 3',
        ], $definition->getProperties()['quz']);
    }

    public function testInvalidBinaryKeyType()
    {
        $container = new ContainerBuilder();
        $loader = new XmlFileLoader($container, new FileLocator(self::$fixturesPath.'/xml'));

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(\sprintf('Tag "<property>" with key-type="binary" does not have a valid base64 encoded key in "%s/xml%skey_type_incorrect_bin.xml".', self::$fixturesPath, \DIRECTORY_SEPARATOR));
        $loader->load('key_type_incorrect_bin.xml');
    }

    public function testUnknownConstantAsKey()
    {
        $container = new ContainerBuilder();
        $loader = new XmlFileLoader($container, new FileLocator(self::$fixturesPath.'/xml'));

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(\sprintf('The key "PHP_Unknown_CONST" is not a valid constant in "%s/xml%skey_type_wrong_constant.xml".', self::$fixturesPath, \DIRECTORY_SEPARATOR));
        $loader->load('key_type_wrong_constant.xml');
    }

    /**
     * @group legacy
     */
    public function testDeprecatedTagged()
    {
        $container = new ContainerBuilder();
        $loader = new XmlFileLoader($container, new FileLocator(self::$fixturesPath . '/xml'));

        $this->expectUserDeprecationMessage(\sprintf('Since symfony/dependency-injection 7.2: Type "tagged" is deprecated for tag <argument>, use "tagged_iterator" instead in "%s/xml%sservices_with_deprecated_tagged.xml".', self::$fixturesPath, \DIRECTORY_SEPARATOR));

        $loader->load('services_with_deprecated_tagged.xml');
    }

    public function testLoadServicesWithEnvironment()
    {
        $container = new ContainerBuilder();

        $loader = new XmlFileLoader($container, new FileLocator(self::$fixturesPath.'/xml'), 'prod');
        $loader->load('when-env-services.xml');

        self::assertInstanceOf(RemoteCallerHttp::class, $container->get(RemoteCaller::class));

        $loader = new XmlFileLoader($container, new FileLocator(self::$fixturesPath.'/xml'), 'dev');
        $loader->load('when-env-services.xml');

        self::assertInstanceOf(RemoteCallerSocket::class, $container->get(RemoteCaller::class));
    }
}
