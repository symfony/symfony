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

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestWith;
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
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\DependencyInjection\Tests\Fixtures\Prototype\AbstractClass;
use Symfony\Component\DependencyInjection\Tests\Fixtures\Prototype\BadClasses\MissingParent;
use Symfony\Component\DependencyInjection\Tests\Fixtures\Prototype\Foo;
use Symfony\Component\DependencyInjection\Tests\Fixtures\Prototype\FooInterface;
use Symfony\Component\DependencyInjection\Tests\Fixtures\Prototype\NotFoo;
use Symfony\Component\DependencyInjection\Tests\Fixtures\Prototype\OtherDir\AnotherSub;
use Symfony\Component\DependencyInjection\Tests\Fixtures\Prototype\OtherDir\AnotherSub\DeeperBaz;
use Symfony\Component\DependencyInjection\Tests\Fixtures\Prototype\OtherDir\Baz;
use Symfony\Component\DependencyInjection\Tests\Fixtures\Prototype\StaticConstructor\PrototypeStaticConstructor;
use Symfony\Component\DependencyInjection\Tests\Fixtures\Prototype\Sub\Bar;
use Symfony\Component\DependencyInjection\Tests\Fixtures\Prototype\Sub\BarInterface;
use Symfony\Component\DependencyInjection\Tests\Fixtures\PrototypeAsAlias\AliasBarInterface;
use Symfony\Component\DependencyInjection\Tests\Fixtures\PrototypeAsAlias\AliasFooInterface;
use Symfony\Component\DependencyInjection\Tests\Fixtures\PrototypeAsAlias\WithAsAlias;
use Symfony\Component\DependencyInjection\Tests\Fixtures\PrototypeAsAlias\WithAsAliasBothEnv;
use Symfony\Component\DependencyInjection\Tests\Fixtures\PrototypeAsAlias\WithAsAliasDevEnv;
use Symfony\Component\DependencyInjection\Tests\Fixtures\PrototypeAsAlias\WithAsAliasIdMultipleInterface;
use Symfony\Component\DependencyInjection\Tests\Fixtures\PrototypeAsAlias\WithAsAliasInterface;
use Symfony\Component\DependencyInjection\Tests\Fixtures\PrototypeAsAlias\WithAsAliasMultiple;
use Symfony\Component\DependencyInjection\Tests\Fixtures\PrototypeAsAlias\WithAsAliasProdEnv;
use Symfony\Component\DependencyInjection\Tests\Fixtures\PrototypeAsAlias\WithCustomAsAlias;
use Symfony\Component\DependencyInjection\Tests\Fixtures\Utils\NotAService;

class FileLoaderTest extends TestCase
{
    protected static string $fixturesPath;

    public static function setUpBeforeClass(): void
    {
        self::$fixturesPath = realpath(__DIR__.'/../');
    }

    public function testImportWithGlobPattern()
    {
        $container = new ContainerBuilder();
        $loader = new TestFileLoader($container, new FileLocator(self::$fixturesPath));

        $resolver = new LoaderResolver([
            new PhpFileLoader($container, new FileLocator(self::$fixturesPath.'/php')),
            new YamlFileLoader($container, new FileLocator(self::$fixturesPath.'/yaml')),
        ]);

        $loader->setResolver($resolver);
        $loader->import('{F}ixtures/{php,yaml}/services2.{php,yml}');

        $actual = $container->getParameterBag()->all();
        $expectedKeys = ['a_string', 'foo', 'values', 'mixedcase', 'constant', 'bar', 'escape', 'foo_bar'];

        $this->assertEquals($expectedKeys, array_keys($actual), '->load() imports and merges imported files');
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
            ['service_container', Bar::class, '.abstract.'.BarInterface::class],
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

    #[TestWith([true])]
    #[TestWith([false])]
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
        $this->assertTrue($container->has(NotFoo::class));

        $this->assertEquals([FooInterface::class], array_keys($container->getAliases()));

        $alias = $container->getAlias(FooInterface::class);
        $this->assertSame(Foo::class, (string) $alias);
        $this->assertFalse($alias->isPublic());
        $this->assertTrue($alias->isPrivate());

        $this->assertEquals([FooInterface::class => (new ChildDefinition(''))->addTag('foo')], $container->getAutoconfiguredInstanceof());
    }

    public function testRegisterClassesWithAbstractClassesAndAutoconfigure()
    {
        $container = new ContainerBuilder();
        $loader = new TestFileLoader($container, new FileLocator(self::$fixturesPath.'/Fixtures'));

        $loader->registerClasses(
            (new Definition())->setAutoconfigured(true),
            'Symfony\Component\DependencyInjection\Tests\Fixtures\Prototype\\',
            'Prototype/*',
            'Prototype/{StaticConstructor}'
        );

        $definition = $container->getDefinition('.abstract.'.AbstractClass::class);
        $this->assertTrue($definition->isAbstract());
        $this->assertTrue($definition->hasTag('container.excluded'));
        $this->assertTrue($definition->isAutoconfigured());
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

    #[DataProvider('excludeTrailingSlashConsistencyProvider')]
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

    #[TestWith(['prod', false])]
    #[TestWith(['dev', false])]
    #[TestWith(['bar', true])]
    #[TestWith([null, false])]
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

    #[DataProvider('provideEnvAndExpectedExclusions')]
    public function testRegisterWithNotWhenAttributes(string $env, bool $expectedNotFooExclusion)
    {
        $container = new ContainerBuilder();
        $loader = new TestFileLoader($container, new FileLocator(self::$fixturesPath.'/Fixtures'), $env);

        $loader->registerClasses(
            (new Definition())->setAutoconfigured(true),
            'Symfony\Component\DependencyInjection\Tests\Fixtures\Prototype\\',
            'Prototype/*',
            'Prototype/BadAttributes/*'
        );

        $this->assertTrue($container->has(NotFoo::class));
        $this->assertSame($expectedNotFooExclusion, $container->getDefinition(NotFoo::class)->hasTag('container.excluded'));
    }

    public static function provideEnvAndExpectedExclusions(): iterable
    {
        yield ['dev', true];
        yield ['prod', true];
        yield ['test', false];
    }

    public function testRegisterThrowsWithBothWhenAndNotWhenAttribute()
    {
        $container = new ContainerBuilder();
        $loader = new TestFileLoader($container, new FileLocator(self::$fixturesPath.'/Fixtures'), 'dev');

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('The "Symfony\Component\DependencyInjection\Tests\Fixtures\Prototype\BadAttributes\WhenNotWhenFoo" class cannot have both #[When] and #[WhenNot] attributes.');

        $loader->registerClasses(
            (new Definition())->setAutoconfigured(true),
            'Symfony\Component\DependencyInjection\Tests\Fixtures\Prototype\BadAttributes\\',
            'Prototype/BadAttributes/*',
        );
    }

    #[DataProvider('provideResourcesWithAsAliasAttributes')]
    public function testRegisterClassesWithAsAlias(string $resource, array $expectedAliases, ?string $env = null)
    {
        $container = new ContainerBuilder();
        $loader = new TestFileLoader($container, new FileLocator(self::$fixturesPath.'/Fixtures'), $env);
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
        yield 'PrivateCustomAlias' => ['PrototypeAsAlias/{WithCustomAsAlias,AliasFooInterface}.php', [AliasFooInterface::class => new Alias(WithCustomAsAlias::class)], 'prod'];
        yield 'PrivateCustomAliasNoMatch' => ['PrototypeAsAlias/{WithCustomAsAlias,AliasFooInterface}.php', [], 'dev'];
        yield 'Interface' => ['PrototypeAsAlias/{WithAsAliasInterface,AliasFooInterface}.php', [AliasFooInterface::class => new Alias(WithAsAliasInterface::class)]];
        yield 'Multiple' => ['PrototypeAsAlias/{WithAsAliasMultiple,AliasFooInterface}.php', [
            AliasFooInterface::class => new Alias(WithAsAliasMultiple::class, true),
            'some-alias' => new Alias(WithAsAliasMultiple::class),
        ]];
        yield 'Multiple with id' => ['PrototypeAsAlias/{WithAsAliasIdMultipleInterface,AliasBarInterface,AliasFooInterface}.php', [
            AliasBarInterface::class => new Alias(WithAsAliasIdMultipleInterface::class),
            AliasFooInterface::class => new Alias(WithAsAliasIdMultipleInterface::class),
        ]];
        yield 'Dev-env specific' => ['PrototypeAsAlias/WithAsAlias*Env.php', [
            AliasFooInterface::class => new Alias(WithAsAliasDevEnv::class),
            AliasBarInterface::class => new Alias(WithAsAliasBothEnv::class),
        ], 'dev'];
        yield 'Prod-env specific' => ['PrototypeAsAlias/WithAsAlias*Env.php', [
            AliasFooInterface::class => new Alias(WithAsAliasProdEnv::class),
            AliasBarInterface::class => new Alias(WithAsAliasBothEnv::class),
        ], 'prod'];
        yield 'Test-env specific' => ['PrototypeAsAlias/WithAsAlias*Env.php', [], 'test'];
    }

    #[DataProvider('provideResourcesWithDuplicatedAsAliasAttributes')]
    public function testRegisterClassesWithDuplicatedAsAlias(string $resource, string $expectedExceptionMessage)
    {
        $container = new ContainerBuilder();
        $loader = new TestFileLoader($container, new FileLocator(self::$fixturesPath.'/Fixtures'));

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage($expectedExceptionMessage);
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
        $container = new ContainerBuilder();
        $loader = new TestFileLoader($container, new FileLocator(self::$fixturesPath.'/Fixtures'));

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Alias cannot be automatically determined for class "Symfony\Component\DependencyInjection\Tests\Fixtures\PrototypeAsAlias\WithAsAliasMultipleInterface". If you have used the #[AsAlias] attribute with a class implementing multiple interfaces, add the interface you want to alias to the first parameter of #[AsAlias].');
        $loader->registerClasses(
            (new Definition())->setAutoconfigured(true),
            'Symfony\Component\DependencyInjection\Tests\Fixtures\PrototypeAsAlias\\',
            'PrototypeAsAlias/{WithAsAliasMultipleInterface,AliasBarInterface,AliasFooInterface}.php'
        );
    }

    public function testRegisterClassesWithStaticConstructor()
    {
        $container = new ContainerBuilder();
        $loader = new TestFileLoader($container, new FileLocator(self::$fixturesPath.'/Fixtures'));

        $prototype = (new Definition())->setAutoconfigured(true);
        $loader->registerClasses($prototype, 'Symfony\Component\DependencyInjection\Tests\Fixtures\Prototype\StaticConstructor\\', 'Prototype/StaticConstructor');

        $this->assertTrue($container->has(PrototypeStaticConstructor::class));
    }
}

class TestFileLoader extends FileLoader
{
    public function noAutoRegisterAliasesForSinglyImplementedInterfaces()
    {
        $this->autoRegisterAliasesForSinglyImplementedInterfaces = false;
    }

    public function load(mixed $resource, ?string $type = null): mixed
    {
        return $resource;
    }

    public function supports(mixed $resource, ?string $type = null): bool
    {
        return false;
    }
}
