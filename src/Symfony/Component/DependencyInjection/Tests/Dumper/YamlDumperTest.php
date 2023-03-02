<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DependencyInjection\Tests\Dumper;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\Argument\AbstractArgument;
use Symfony\Component\DependencyInjection\Argument\ServiceClosureArgument;
use Symfony\Component\DependencyInjection\Argument\ServiceLocatorArgument;
use Symfony\Component\DependencyInjection\Argument\TaggedIteratorArgument;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Dumper\YamlDumper;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\DependencyInjection\Tests\Fixtures\FooClassWithEnumAttribute;
use Symfony\Component\DependencyInjection\Tests\Fixtures\FooUnitEnum;
use Symfony\Component\DependencyInjection\Tests\Fixtures\FooWithAbstractArgument;
use Symfony\Component\Yaml\Parser;
use Symfony\Component\Yaml\Yaml;

class YamlDumperTest extends TestCase
{
    protected static $fixturesPath;

    public static function setUpBeforeClass(): void
    {
        self::$fixturesPath = realpath(__DIR__.'/../Fixtures/');
    }

    public function testDump()
    {
        $dumper = new YamlDumper($container = new ContainerBuilder());

        $this->assertEqualYamlStructure(file_get_contents(self::$fixturesPath.'/yaml/services1.yml'), $dumper->dump(), '->dump() dumps an empty container as an empty YAML file');
    }

    public function testAddParameters()
    {
        $container = include self::$fixturesPath.'/containers/container8.php';
        $dumper = new YamlDumper($container);
        $this->assertEqualYamlStructure(file_get_contents(self::$fixturesPath.'/yaml/services8.yml'), $dumper->dump(), '->dump() dumps parameters');
    }

    public function testAddService()
    {
        $container = include self::$fixturesPath.'/containers/container9.php';
        $dumper = new YamlDumper($container);
        $this->assertEqualYamlStructure(str_replace('%path%', self::$fixturesPath.\DIRECTORY_SEPARATOR.'includes'.\DIRECTORY_SEPARATOR, file_get_contents(self::$fixturesPath.'/yaml/services9.yml')), $dumper->dump(), '->dump() dumps services');

        $dumper = new YamlDumper($container = new ContainerBuilder());
        $container->register('foo', 'FooClass')->addArgument(new \stdClass())->setPublic(true);
        try {
            $dumper->dump();
            $this->fail('->dump() throws a RuntimeException if the container to be dumped has reference to objects or resources');
        } catch (\Exception $e) {
            $this->assertInstanceOf(\RuntimeException::class, $e, '->dump() throws a RuntimeException if the container to be dumped has reference to objects or resources');
            $this->assertEquals('Unable to dump a service container if a parameter is an object or a resource, got "stdClass".', $e->getMessage(), '->dump() throws a RuntimeException if the container to be dumped has reference to objects or resources');
        }
    }

    public function testDumpAutowireData()
    {
        $container = include self::$fixturesPath.'/containers/container24.php';
        $dumper = new YamlDumper($container);
        $this->assertStringEqualsFile(self::$fixturesPath.'/yaml/services24.yml', $dumper->dump());
    }

    public function testDumpDecoratedServices()
    {
        $container = include self::$fixturesPath.'/containers/container34.php';
        $dumper = new YamlDumper($container);
        $this->assertStringEqualsFile(self::$fixturesPath.'/yaml/services34.yml', $dumper->dump());
    }

    public function testDumpLoad()
    {
        $container = new ContainerBuilder();
        $loader = new YamlFileLoader($container, new FileLocator(self::$fixturesPath.'/yaml'));
        $loader->load('services_dump_load.yml');

        $this->assertEquals([new Reference('bar', ContainerInterface::IGNORE_ON_UNINITIALIZED_REFERENCE)], $container->getDefinition('foo')->getArguments());

        $dumper = new YamlDumper($container);
        $this->assertStringEqualsFile(self::$fixturesPath.'/yaml/services_dump_load.yml', $dumper->dump());
    }

    public function testInlineServices()
    {
        $container = new ContainerBuilder();
        $container->register('foo', 'Class1')
            ->setPublic(true)
            ->addArgument((new Definition('Class2'))
                ->addArgument(new Definition('Class2'))
            )
        ;

        $dumper = new YamlDumper($container);
        $this->assertStringEqualsFile(self::$fixturesPath.'/yaml/services_inline.yml', $dumper->dump());
    }

    public function testTaggedArguments()
    {
        $taggedIterator = new TaggedIteratorArgument('foo', 'barfoo', 'foobar', false, 'getPriority');
        $taggedIterator2 = new TaggedIteratorArgument('foo', null, null, false, null, ['baz']);
        $taggedIterator3 = new TaggedIteratorArgument('foo', null, null, false, null, ['baz', 'qux']);

        $container = new ContainerBuilder();

        $container->register('foo_service', 'Foo')->addTag('foo');
        $container->register('baz_service', 'Baz')->addTag('foo');
        $container->register('qux_service', 'Qux')->addTag('foo');

        $container->register('foo_service_tagged_iterator', 'Bar')->addArgument($taggedIterator);
        $container->register('foo2_service_tagged_iterator', 'Bar')->addArgument($taggedIterator2);
        $container->register('foo3_service_tagged_iterator', 'Bar')->addArgument($taggedIterator3);

        $container->register('foo_service_tagged_locator', 'Bar')->addArgument(new ServiceLocatorArgument($taggedIterator));
        $container->register('foo2_service_tagged_locator', 'Bar')->addArgument(new ServiceLocatorArgument($taggedIterator2));
        $container->register('foo3_service_tagged_locator', 'Bar')->addArgument(new ServiceLocatorArgument($taggedIterator3));
        $container->register('bar_service_tagged_locator', 'Bar')->addArgument(new ServiceLocatorArgument(new TaggedIteratorArgument('foo')));

        $dumper = new YamlDumper($container);
        $this->assertStringEqualsFile(self::$fixturesPath.'/yaml/services_with_tagged_argument.yml', $dumper->dump());
    }

    public function testServiceClosure()
    {
        $container = new ContainerBuilder();
        $container->register('foo', 'Foo')
            ->addArgument(new ServiceClosureArgument(new Reference('bar', ContainerInterface::IGNORE_ON_INVALID_REFERENCE)))
        ;

        $dumper = new YamlDumper($container);
        $this->assertStringEqualsFile(self::$fixturesPath.'/yaml/services_with_service_closure.yml', $dumper->dump());
    }

    public function testDumpHandlesEnumeration()
    {
        $container = new ContainerBuilder();
        $container
            ->register(FooClassWithEnumAttribute::class, FooClassWithEnumAttribute::class)
            ->setPublic(true)
            ->addArgument(FooUnitEnum::BAR);

        $container->setParameter('unit_enum', FooUnitEnum::BAR);
        $container->setParameter('enum_array', [FooUnitEnum::BAR, FooUnitEnum::FOO]);

        $container->compile();
        $dumper = new YamlDumper($container);

        $this->assertEquals(file_get_contents(self::$fixturesPath.'/yaml/services_with_enumeration.yml'), $dumper->dump());
    }

    public function testDumpServiceWithAbstractArgument()
    {
        $container = new ContainerBuilder();
        $container->register(FooWithAbstractArgument::class, FooWithAbstractArgument::class)
            ->setArgument('$baz', new AbstractArgument('should be defined by Pass'))
            ->setArgument('$bar', 'test');

        $dumper = new YamlDumper($container);
        $this->assertStringEqualsFile(self::$fixturesPath.'/yaml/services_with_abstract_argument.yml', $dumper->dump());
    }

    public function testDumpNonScalarTags()
    {
        $container = include self::$fixturesPath.'/containers/container_non_scalar_tags.php';
        $dumper = new YamlDumper($container);

        $this->assertEquals(file_get_contents(self::$fixturesPath.'/yaml/services_with_array_tags.yml'), $dumper->dump());
    }

    private function assertEqualYamlStructure(string $expected, string $yaml, string $message = '')
    {
        $parser = new Parser();

        $this->assertEquals($parser->parse($expected, Yaml::PARSE_CUSTOM_TAGS), $parser->parse($yaml, Yaml::PARSE_CUSTOM_TAGS), $message);
    }
}
