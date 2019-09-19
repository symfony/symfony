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
use Psr\Container\ContainerInterface as PsrContainerInterface;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\Config\Loader\LoaderResolver;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;
use Symfony\Component\DependencyInjection\Loader\FileLoader;
use Symfony\Component\DependencyInjection\Loader\IniFileLoader;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\DependencyInjection\Tests\Fixtures\Prototype\BadClasses\MissingParent;
use Symfony\Component\DependencyInjection\Tests\Fixtures\Prototype\Foo;
use Symfony\Component\DependencyInjection\Tests\Fixtures\Prototype\FooInterface;
use Symfony\Component\DependencyInjection\Tests\Fixtures\Prototype\OtherDir\AnotherSub\DeeperBaz;
use Symfony\Component\DependencyInjection\Tests\Fixtures\Prototype\OtherDir\Baz;
use Symfony\Component\DependencyInjection\Tests\Fixtures\Prototype\Sub\Bar;
use Symfony\Component\DependencyInjection\Tests\Fixtures\Prototype\Sub\BarInterface;

class FileLoaderTest extends TestCase
{
    protected static $fixturesPath;

    public static function setUpBeforeClass()
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

        $loader->registerClasses(new Definition(), 'Symfony\Component\DependencyInjection\Tests\Fixtures\Prototype\Sub\\', 'Prototype/%sub_dir%/*');

        $this->assertEquals(
            ['service_container', Bar::class],
            array_keys($container->getDefinitions())
        );
        $this->assertEquals(
            [
                PsrContainerInterface::class,
                ContainerInterface::class,
                BarInterface::class,
            ],
            array_keys($container->getAliases())
        );
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
            'Prototype/{%other_dir%/AnotherSub,Foo.php}'
        );

        $this->assertTrue($container->has(Bar::class));
        $this->assertTrue($container->has(Baz::class));
        $this->assertFalse($container->has(Foo::class));
        $this->assertFalse($container->has(DeeperBaz::class));

        $this->assertEquals(
            [
                PsrContainerInterface::class,
                ContainerInterface::class,
                BarInterface::class,
            ],
            array_keys($container->getAliases())
        );
    }

    public function testNestedRegisterClasses()
    {
        $container = new ContainerBuilder();
        $loader = new TestFileLoader($container, new FileLocator(self::$fixturesPath.'/Fixtures'));

        $prototype = new Definition();
        $prototype->setPublic(true)->setPrivate(true);
        $loader->registerClasses($prototype, 'Symfony\Component\DependencyInjection\Tests\Fixtures\Prototype\\', 'Prototype/*');

        $this->assertTrue($container->has(Bar::class));
        $this->assertTrue($container->has(Baz::class));
        $this->assertTrue($container->has(Foo::class));

        $this->assertEquals(
            [
                PsrContainerInterface::class,
                ContainerInterface::class,
                FooInterface::class,
            ],
            array_keys($container->getAliases())
        );

        $alias = $container->getAlias(FooInterface::class);
        $this->assertSame(Foo::class, (string) $alias);
        $this->assertFalse($alias->isPublic());
        $this->assertFalse($alias->isPrivate());
    }

    public function testMissingParentClass()
    {
        $container = new ContainerBuilder();
        $container->setParameter('bad_classes_dir', 'BadClasses');
        $loader = new TestFileLoader($container, new FileLocator(self::$fixturesPath.'/Fixtures'));

        $loader->registerClasses(
            (new Definition())->setPublic(false),
            'Symfony\Component\DependencyInjection\Tests\Fixtures\Prototype\BadClasses\\',
            'Prototype/%bad_classes_dir%/*'
        );

        $this->assertTrue($container->has(MissingParent::class));

        $this->assertSame(
            ['While discovering services from namespace "Symfony\Component\DependencyInjection\Tests\Fixtures\Prototype\BadClasses\", an error was thrown when processing the class "Symfony\Component\DependencyInjection\Tests\Fixtures\Prototype\BadClasses\MissingParent": "Class Symfony\Component\DependencyInjection\Tests\Fixtures\Prototype\BadClasses\MissingClass not found".'],
            $container->getDefinition(MissingParent::class)->getErrors()
        );
    }

    public function testRegisterClassesWithBadPrefix()
    {
        $this->expectException('Symfony\Component\DependencyInjection\Exception\InvalidArgumentException');
        $this->expectExceptionMessageRegExp('/Expected to find class "Symfony\\\Component\\\DependencyInjection\\\Tests\\\Fixtures\\\Prototype\\\Bar" in file ".+" while importing services from resource "Prototype\/Sub\/\*", but it was not found\! Check the namespace prefix used with the resource/');
        $container = new ContainerBuilder();
        $loader = new TestFileLoader($container, new FileLocator(self::$fixturesPath.'/Fixtures'));

        // the Sub is missing from namespace prefix
        $loader->registerClasses(new Definition(), 'Symfony\Component\DependencyInjection\Tests\Fixtures\Prototype\\', 'Prototype/Sub/*');
    }

    /**
     * @dataProvider getIncompatibleExcludeTests
     */
    public function testRegisterClassesWithIncompatibleExclude($resourcePattern, $excludePattern)
    {
        $container = new ContainerBuilder();
        $loader = new TestFileLoader($container, new FileLocator(self::$fixturesPath.'/Fixtures'));

        try {
            $loader->registerClasses(
                new Definition(),
                'Symfony\Component\DependencyInjection\Tests\Fixtures\Prototype\\',
                $resourcePattern,
                $excludePattern
            );
        } catch (InvalidArgumentException $e) {
            $this->assertEquals(
                sprintf('Invalid "exclude" pattern when importing classes for "Symfony\Component\DependencyInjection\Tests\Fixtures\Prototype\": make sure your "exclude" pattern (%s) is a subset of the "resource" pattern (%s)', $excludePattern, $resourcePattern),
                $e->getMessage()
            );
        }
    }

    public function getIncompatibleExcludeTests()
    {
        yield ['Prototype/*', 'yaml/*', false];
        yield ['Prototype/OtherDir/*', 'Prototype/*', false];
    }
}

class TestFileLoader extends FileLoader
{
    public function load($resource, $type = null)
    {
        return $resource;
    }

    public function supports($resource, $type = null)
    {
        return false;
    }
}
