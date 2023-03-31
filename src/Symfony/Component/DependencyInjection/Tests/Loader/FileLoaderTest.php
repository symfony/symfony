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
use Symfony\Component\DependencyInjection\Alias;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;
use Symfony\Component\DependencyInjection\Exception\LogicException;
use Symfony\Component\DependencyInjection\Loader\FileLoader;
use Symfony\Component\DependencyInjection\Loader\IniFileLoader;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\DependencyInjection\Tests\Fixtures\Prototype\BadClasses\MissingParent;
use Symfony\Component\DependencyInjection\Tests\Fixtures\Prototype\Foo;
use Symfony\Component\DependencyInjection\Tests\Fixtures\Prototype\FooInterface;
use Symfony\Component\DependencyInjection\Tests\Fixtures\Prototype\OtherDir\AnotherSub;
use Symfony\Component\DependencyInjection\Tests\Fixtures\Prototype\OtherDir\AnotherSub\DeeperBaz;
use Symfony\Component\DependencyInjection\Tests\Fixtures\Prototype\OtherDir\Baz;
use Symfony\Component\DependencyInjection\Tests\Fixtures\Prototype\Sub\Bar;
use Symfony\Component\DependencyInjection\Tests\Fixtures\Prototype\Sub\BarInterface;
use Symfony\Component\DependencyInjection\Tests\Fixtures\PrototypeAsAlias\AliasBarInterface;
use Symfony\Component\DependencyInjection\Tests\Fixtures\PrototypeAsAlias\AliasFooInterface;
use Symfony\Component\DependencyInjection\Tests\Fixtures\PrototypeAsAlias\WithAsAlias;
use Symfony\Component\DependencyInjection\Tests\Fixtures\PrototypeAsAlias\WithAsAliasIdMultipleInterface;
use Symfony\Component\DependencyInjection\Tests\Fixtures\PrototypeAsAlias\WithAsAliasInterface;
use Symfony\Component\DependencyInjection\Tests\Fixtures\PrototypeAsAlias\WithAsAliasMultiple;
use Symfony\Component\DependencyInjection\Tests\Fixtures\Utils\NotAService;

class FileLoaderTest extends TestCase
{
    protected static $fixturesPath;

    public static function setUpBeforeClass(): void
    {
        self::$fixturesPath = realpath(__DIR__.'/../');
    }

    public function testImportWithGlobPattern()
    {
        $container = new ContainerBuilder();
        $loader = new TestFileLoader($container, new FileLocator(self::$fixturesPath));

        $resolver = new LoaderResolver([
            new IniFileLoader($container, new FileLocator(self::$fixturesPath.'/ini')),
            new XmlFileLoader($container, new FileLocator(self::$fixturesPath.'/xml')),
            new PhpFileLoader($container, new FileLocator(self::$fixturesPath.'/php')),
            new YamlFileLoader($container, new FileLocator(self::$fixturesPath.'/yaml')),
        ]);

        $loader->setResolver($resolver);
        $loader->import('{F}ixtures/{xml,yaml}/services2.{yml,xml}');

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
            'escape' => '@escapeme',
            'foo_bar' => new Reference('foo_bar'),
        ];

        $this->assertEquals(array_keys($expected), array_keys($actual), '->load() imports and merges imported files');
    }

    public function testRegisterClasses()
    {
        $container = new ContainerBuilder();
        $container->setParameter('sub_dir', 'Sub');
        $loader = new TestFileLoader($container, new FileLocator(self::$fixturesPath.'/Fixtures'));
        $loader->noAutoRegisterAliasesForSinglyImplementedInterfaces();

        $loader->registerClasses(new Definition(), 'Symfony\Component\DependencyInjection\Tests\Fixtures\Prototype\Sub\\', 'Prototype/%sub_dir%/*');
        $loader->registerClasses(new Definition(), 'Symfony\Component\DependencyInjection\Tests\Fixtures\Prototype\Sub\\', 'Prototype/%sub_dir%/*'); // loading twice should not be an issue
        $loader->registerAliasesForSinglyImplementedInterfaces();

        $this->assertEquals(
            ['service_container', Bar::class],
            array_keys($container->getDefinitions())
        );
        $this->assertEquals([BarInterface::class], array_keys($container->getAliases()));
    }

    public function testRegisterClassesWithExclude()
    {
        $container = new ContainerBuilder();
        $container->setParameter('other_dir', 'OtherDir');
        $loader = new TestFileLoader($container, new FileLocator(self::$fixturesPath.'/Fixtures'));

        $loader->registerClasses(
            new Definition(),
            'Symfony\Component\DependencyInjection\Tests\Fixtures\Prototype\\',
            'Prototype/*',
            // load everything, except OtherDir/AnotherSub & Foo.php
            'Prototype/{%other_dir%/AnotherSub,Foo.php,StaticConstructor}'
        );

        $this->assertFalse($container->getDefinition(Bar::class)->isAbstract());
        $this->assertFalse($container->getDefinition(Baz::class)->isAbstract());
        $this->assertTrue($container->getDefinition(Foo::class)->isAbstract());
        $this->assertTrue($container->getDefinition(AnotherSub::class)->isAbstract());

        $this->assertFalse($container->getDefinition(Bar::class)->hasTag('container.excluded'));
        $this->assertFalse($container->getDefinition(Baz::class)->hasTag('container.excluded'));
        $this->assertTrue($container->getDefinition(Foo::class)->hasTag('container.excluded'));
        $this->assertTrue($container->getDefinition(AnotherSub::class)->hasTag('container.excluded'));

        $this->assertEquals([BarInterface::class], array_keys($container->getAliases()));

        $loader->registerClasses(
            new Definition(),
            'Symfony\Component\DependencyInjection\Tests\Fixtures\Prototype\\',
            'Prototype/*',
            'Prototype/NotExistingDir'
        );
    }

    /**
     * @testWith [true]
     *           [false]
     */
    public function testRegisterClassesWithExcludeAttribute(bool $autoconfigure)
    {
        $container = new ContainerBuilder();
        $loader = new TestFileLoader($container, new FileLocator(self::$fixturesPath.'/Fixtures'));

        $loader->registerClasses(
            (new Definition())->setAutoconfigured($autoconfigure),
            'Symfony\Component\DependencyInjection\Tests\Fixtures\Utils\\',
            'Utils/*',
        );

        $this->assertSame($autoconfigure, $container->getDefinition(NotAService::class)->hasTag('container.excluded'));
    }

    public function testRegisterClassesWithExcludeAsArray()
    {
        $container = new ContainerBuilder();
        $container->setParameter('sub_dir', 'Sub');
        $loader = new TestFileLoader($container, new FileLocator(self::$fixturesPath.'/Fixtures'));
        $loader->registerClasses(
            new Definition(),
            'Symfony\Component\DependencyInjection\Tests\Fixtures\Prototype\\',
            'Prototype/*', [
                'Prototype/%sub_dir%',
                'Prototype/OtherDir/AnotherSub/DeeperBaz.php',
            ]
        );

        $this->assertTrue($container->has(Foo::class));
        $this->assertTrue($container->has(Baz::class));
        $this->assertFalse($container->has(Bar::class));
        $this->assertTrue($container->has(DeeperBaz::class));
        $this->assertTrue($container->getDefinition(DeeperBaz::class)->hasTag('container.excluded'));
    }

    public function testNestedRegisterClasses()
    {
        $container = new ContainerBuilder();
        $loader = new TestFileLoader($container, new FileLocator(self::$fixturesPath.'/Fixtures'));

        $prototype = (new Definition())->setAutoconfigured(true);
        $loader->registerClasses($prototype, 'Symfony\Component\DependencyInjection\Tests\Fixtures\Prototype\\', 'Prototype/*', 'Prototype/{StaticConstructor}');

        $this->assertTrue($container->has(Bar::class));
        $this->assertTrue($container->has(Baz::class));
        $this->assertTrue($container->has(Foo::class));

        $this->assertEquals([FooInterface::class], array_keys($container->getAliases()));

        $alias = $container->getAlias(FooInterface::class);
        $this->assertSame(Foo::class, (string) $alias);
        $this->assertFalse($alias->isPublic());
        $this->assertTrue($alias->isPrivate());

        $this->assertEquals([FooInterface::class => (new ChildDefinition(''))->addTag('foo')], $container->getAutoconfiguredInstanceof());
    }

    public function testMissingParentClass()
    {
        $container = new ContainerBuilder();
        $container->setParameter('bad_classes_dir', 'BadClasses');
        $loader = new TestFileLoader($container, new FileLocator(self::$fixturesPath.'/Fixtures'), 'test');

        $loader->registerClasses(
            (new Definition())->setAutoconfigured(true),
            'Symfony\Component\DependencyInjection\Tests\Fixtures\Prototype\BadClasses\\',
            'Prototype/%bad_classes_dir%/*'
        );

        $this->assertTrue($container->has(MissingParent::class));

        $this->assertMatchesRegularExpression(
            '{Class "?Symfony\\\\Component\\\\DependencyInjection\\\\Tests\\\\Fixtures\\\\Prototype\\\\BadClasses\\\\MissingClass"? not found}',
            $container->getDefinition(MissingParent::class)->getErrors()[0]
        );
    }

    public function testRegisterClassesWithBadPrefix()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/Expected to find class "Symfony\\\Component\\\DependencyInjection\\\Tests\\\Fixtures\\\Prototype\\\Bar" in file ".+" while importing services from resource "Prototype\/Sub\/\*", but it was not found\! Check the namespace prefix used with the resource/');
        $container = new ContainerBuilder();
        $loader = new TestFileLoader($container, new FileLocator(self::$fixturesPath.'/Fixtures'));

        // the Sub is missing from namespace prefix
        $loader->registerClasses(new Definition(), 'Symfony\Component\DependencyInjection\Tests\Fixtures\Prototype\\', 'Prototype/Sub/*');
    }

    public function testRegisterClassesWithIncompatibleExclude()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid "exclude" pattern when importing classes for "Symfony\Component\DependencyInjection\Tests\Fixtures\Prototype\": make sure your "exclude" pattern (yaml/*) is a subset of the "resource" pattern (Prototype/*)');
        $container = new ContainerBuilder();
        $loader = new TestFileLoader($container, new FileLocator(self::$fixturesPath.'/Fixtures'));

        $loader->registerClasses(
            new Definition(),
            'Symfony\Component\DependencyInjection\Tests\Fixtures\Prototype\\',
            'Prototype/*',
            'yaml/*'
        );
    }

    /**
     * @dataProvider excludeTrailingSlashConsistencyProvider
     */
    public function testExcludeTrailingSlashConsistency(string $exclude, string $excludedId)
    {
        $container = new ContainerBuilder();
        $loader = new TestFileLoader($container, new FileLocator(self::$fixturesPath.'/Fixtures'));
        $loader->registerClasses(
            new Definition(),
            'Symfony\Component\DependencyInjection\Tests\Fixtures\Prototype\\',
            'Prototype/*',
            $exclude
        );

        $this->assertTrue($container->has(Foo::class));
        $this->assertTrue($container->has($excludedId));
        $this->assertTrue($container->getDefinition($excludedId)->hasTag('container.excluded'));
    }

    public static function excludeTrailingSlashConsistencyProvider(): iterable
    {
        yield ['Prototype/OtherDir/AnotherSub/', AnotherSub::class];
        yield ['Prototype/OtherDir/AnotherSub', AnotherSub::class];
        yield ['Prototype/OtherDir/AnotherSub/*', DeeperBaz::class];
        yield ['Prototype/*/AnotherSub', AnotherSub::class];
        yield ['Prototype/*/AnotherSub/', AnotherSub::class];
        yield ['Prototype/*/AnotherSub/*', DeeperBaz::class];
        yield ['Prototype/OtherDir/AnotherSub/DeeperBaz.php', DeeperBaz::class];
    }

    /**
     * @testWith ["prod", false]
     *           ["dev", false]
     *           ["bar", true]
     *           [null, false]
     */
    public function testRegisterClassesWithWhenEnv(?string $env, bool $expected)
    {
        $container = new ContainerBuilder();
        $loader = new TestFileLoader($container, new FileLocator(self::$fixturesPath.'/Fixtures'), $env);
        $loader->registerClasses(
            (new Definition())->setAutoconfigured(true),
            'Symfony\Component\DependencyInjection\Tests\Fixtures\Prototype\\',
            'Prototype/{Foo.php}'
        );

        $this->assertSame($expected, $container->getDefinition(Foo::class)->hasTag('container.excluded'));
    }

    /**
     * @dataProvider provideResourcesWithAsAliasAttributes
     */
    public function testRegisterClassesWithAsAlias(string $resource, array $expectedAliases)
    {
        $container = new ContainerBuilder();
        $loader = new TestFileLoader($container, new FileLocator(self::$fixturesPath.'/Fixtures'));
        $loader->registerClasses(
            (new Definition())->setAutoconfigured(true),
            'Symfony\Component\DependencyInjection\Tests\Fixtures\PrototypeAsAlias\\',
            $resource
        );

        $this->assertEquals($expectedAliases, $container->getAliases());
    }

    public static function provideResourcesWithAsAliasAttributes(): iterable
    {
        yield 'Private' => ['PrototypeAsAlias/{WithAsAlias,AliasFooInterface}.php', [AliasFooInterface::class => new Alias(WithAsAlias::class)]];
        yield 'Interface' => ['PrototypeAsAlias/{WithAsAliasInterface,AliasFooInterface}.php', [AliasFooInterface::class => new Alias(WithAsAliasInterface::class)]];
        yield 'Multiple' => ['PrototypeAsAlias/{WithAsAliasMultiple,AliasFooInterface}.php', [
            AliasFooInterface::class => new Alias(WithAsAliasMultiple::class, true),
            'some-alias' => new Alias(WithAsAliasMultiple::class),
        ]];
        yield 'Multiple with id' => ['PrototypeAsAlias/{WithAsAliasIdMultipleInterface,AliasBarInterface,AliasFooInterface}.php', [
            AliasBarInterface::class => new Alias(WithAsAliasIdMultipleInterface::class),
            AliasFooInterface::class => new Alias(WithAsAliasIdMultipleInterface::class),
        ]];
    }

    /**
     * @dataProvider provideResourcesWithDuplicatedAsAliasAttributes
     */
    public function testRegisterClassesWithDuplicatedAsAlias(string $resource, string $expectedExceptionMessage)
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage($expectedExceptionMessage);

        $container = new ContainerBuilder();
        $loader = new TestFileLoader($container, new FileLocator(self::$fixturesPath.'/Fixtures'));
        $loader->registerClasses(
            (new Definition())->setAutoconfigured(true),
            'Symfony\Component\DependencyInjection\Tests\Fixtures\PrototypeAsAlias\\',
            $resource
        );
    }

    public static function provideResourcesWithDuplicatedAsAliasAttributes(): iterable
    {
        yield 'Duplicated' => ['PrototypeAsAlias/{WithAsAlias,WithAsAliasDuplicate,AliasFooInterface}.php', 'The "Symfony\Component\DependencyInjection\Tests\Fixtures\PrototypeAsAlias\AliasFooInterface" alias has already been defined with the #[AsAlias] attribute in "Symfony\Component\DependencyInjection\Tests\Fixtures\PrototypeAsAlias\WithAsAlias".'];
        yield 'Interface duplicated' => ['PrototypeAsAlias/{WithAsAliasInterface,WithAsAlias,AliasFooInterface}.php', 'The "Symfony\Component\DependencyInjection\Tests\Fixtures\PrototypeAsAlias\AliasFooInterface" alias has already been defined with the #[AsAlias] attribute in "Symfony\Component\DependencyInjection\Tests\Fixtures\PrototypeAsAlias\WithAsAlias".'];
    }

    public function testRegisterClassesWithAsAliasAndImplementingMultipleInterfaces()
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Alias cannot be automatically determined for class "Symfony\Component\DependencyInjection\Tests\Fixtures\PrototypeAsAlias\WithAsAliasMultipleInterface". If you have used the #[AsAlias] attribute with a class implementing multiple interfaces, add the interface you want to alias to the first parameter of #[AsAlias].');

        $container = new ContainerBuilder();
        $loader = new TestFileLoader($container, new FileLocator(self::$fixturesPath.'/Fixtures'));
        $loader->registerClasses(
            (new Definition())->setAutoconfigured(true),
            'Symfony\Component\DependencyInjection\Tests\Fixtures\PrototypeAsAlias\\',
            'PrototypeAsAlias/{WithAsAliasMultipleInterface,AliasBarInterface,AliasFooInterface}.php'
        );
    }
}

class TestFileLoader extends FileLoader
{
    public function noAutoRegisterAliasesForSinglyImplementedInterfaces()
    {
        $this->autoRegisterAliasesForSinglyImplementedInterfaces = false;
    }

    public function load(mixed $resource, string $type = null): mixed
    {
        return $resource;
    }

    public function supports(mixed $resource, string $type = null): bool
    {
        return false;
    }
}
