<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\DependencyInjection\Tests\Loader;

use Psr\Container\ContainerInterface as PsrContainerInterface;
use PHPUnit\Framework\TestCase;
use Symphony\Component\Config\FileLocator;
use Symphony\Component\Config\Loader\LoaderResolver;
use Symphony\Component\DependencyInjection\ContainerBuilder;
use Symphony\Component\DependencyInjection\ContainerInterface;
use Symphony\Component\DependencyInjection\Definition;
use Symphony\Component\DependencyInjection\Exception\InvalidArgumentException;
use Symphony\Component\DependencyInjection\Loader\FileLoader;
use Symphony\Component\DependencyInjection\Loader\IniFileLoader;
use Symphony\Component\DependencyInjection\Loader\PhpFileLoader;
use Symphony\Component\DependencyInjection\Loader\XmlFileLoader;
use Symphony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symphony\Component\DependencyInjection\Reference;
use Symphony\Component\DependencyInjection\Tests\Fixtures\Prototype\BadClasses\MissingParent;
use Symphony\Component\DependencyInjection\Tests\Fixtures\Prototype\OtherDir\AnotherSub\DeeperBaz;
use Symphony\Component\DependencyInjection\Tests\Fixtures\Prototype\OtherDir\Baz;
use Symphony\Component\DependencyInjection\Tests\Fixtures\Prototype\Foo;
use Symphony\Component\DependencyInjection\Tests\Fixtures\Prototype\FooInterface;
use Symphony\Component\DependencyInjection\Tests\Fixtures\Prototype\Sub\Bar;
use Symphony\Component\DependencyInjection\Tests\Fixtures\Prototype\Sub\BarInterface;

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

        $resolver = new LoaderResolver(array(
            new IniFileLoader($container, new FileLocator(self::$fixturesPath.'/ini')),
            new XmlFileLoader($container, new FileLocator(self::$fixturesPath.'/xml')),
            new PhpFileLoader($container, new FileLocator(self::$fixturesPath.'/php')),
            new YamlFileLoader($container, new FileLocator(self::$fixturesPath.'/yaml')),
        ));

        $loader->setResolver($resolver);
        $loader->import('{F}ixtures/{xml,yaml}/services2.{yml,xml}');

        $actual = $container->getParameterBag()->all();
        $expected = array(
            'a string',
            'foo' => 'bar',
            'values' => array(
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
                array('foo', 'bar'),
            ),
            'mixedcase' => array('MixedCaseKey' => 'value'),
            'constant' => PHP_EOL,
            'bar' => '%foo%',
            'escape' => '@escapeme',
            'foo_bar' => new Reference('foo_bar'),
        );

        $this->assertEquals(array_keys($expected), array_keys($actual), '->load() imports and merges imported files');
    }

    public function testRegisterClasses()
    {
        $container = new ContainerBuilder();
        $container->setParameter('sub_dir', 'Sub');
        $loader = new TestFileLoader($container, new FileLocator(self::$fixturesPath.'/Fixtures'));

        $loader->registerClasses(new Definition(), 'Symphony\Component\DependencyInjection\Tests\Fixtures\Prototype\Sub\\', 'Prototype/%sub_dir%/*');

        $this->assertEquals(
            array('service_container', Bar::class),
            array_keys($container->getDefinitions())
        );
        $this->assertEquals(
            array(
                PsrContainerInterface::class,
                ContainerInterface::class,
                BarInterface::class,
            ),
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
            'Symphony\Component\DependencyInjection\Tests\Fixtures\Prototype\\',
            'Prototype/*',
            // load everything, except OtherDir/AnotherSub & Foo.php
            'Prototype/{%other_dir%/AnotherSub,Foo.php}'
        );

        $this->assertTrue($container->has(Bar::class));
        $this->assertTrue($container->has(Baz::class));
        $this->assertFalse($container->has(Foo::class));
        $this->assertFalse($container->has(DeeperBaz::class));

        $this->assertEquals(
            array(
                PsrContainerInterface::class,
                ContainerInterface::class,
                BarInterface::class,
            ),
            array_keys($container->getAliases())
        );
    }

    public function testNestedRegisterClasses()
    {
        $container = new ContainerBuilder();
        $loader = new TestFileLoader($container, new FileLocator(self::$fixturesPath.'/Fixtures'));

        $prototype = new Definition();
        $prototype->setPublic(true)->setPrivate(true);
        $loader->registerClasses($prototype, 'Symphony\Component\DependencyInjection\Tests\Fixtures\Prototype\\', 'Prototype/*');

        $this->assertTrue($container->has(Bar::class));
        $this->assertTrue($container->has(Baz::class));
        $this->assertTrue($container->has(Foo::class));

        $this->assertEquals(
            array(
                PsrContainerInterface::class,
                ContainerInterface::class,
                FooInterface::class,
            ),
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
            'Symphony\Component\DependencyInjection\Tests\Fixtures\Prototype\BadClasses\\',
            'Prototype/%bad_classes_dir%/*'
        );

        $this->assertTrue($container->has(MissingParent::class));

        $this->assertSame(
            array('While discovering services from namespace "Symphony\Component\DependencyInjection\Tests\Fixtures\Prototype\BadClasses\", an error was thrown when processing the class "Symphony\Component\DependencyInjection\Tests\Fixtures\Prototype\BadClasses\MissingParent": "Class Symphony\Component\DependencyInjection\Tests\Fixtures\Prototype\BadClasses\MissingClass not found".'),
            $container->getDefinition(MissingParent::class)->getErrors()
        );
    }

    /**
     * @expectedException \Symphony\Component\DependencyInjection\Exception\InvalidArgumentException
     * @expectedExceptionMessageRegExp /Expected to find class "Symphony\\Component\\DependencyInjection\\Tests\\Fixtures\\Prototype\\Bar" in file ".+" while importing services from resource "Prototype\/Sub\/\*", but it was not found\! Check the namespace prefix used with the resource/
     */
    public function testRegisterClassesWithBadPrefix()
    {
        $container = new ContainerBuilder();
        $loader = new TestFileLoader($container, new FileLocator(self::$fixturesPath.'/Fixtures'));

        // the Sub is missing from namespace prefix
        $loader->registerClasses(new Definition(), 'Symphony\Component\DependencyInjection\Tests\Fixtures\Prototype\\', 'Prototype/Sub/*');
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
                'Symphony\Component\DependencyInjection\Tests\Fixtures\Prototype\\',
                $resourcePattern,
                $excludePattern
            );
        } catch (InvalidArgumentException $e) {
            $this->assertEquals(
                sprintf('Invalid "exclude" pattern when importing classes for "Symphony\Component\DependencyInjection\Tests\Fixtures\Prototype\": make sure your "exclude" pattern (%s) is a subset of the "resource" pattern (%s)', $excludePattern, $resourcePattern),
                $e->getMessage()
            );
        }
    }

    public function getIncompatibleExcludeTests()
    {
        yield array('Prototype/*', 'yaml/*', false);
        yield array('Prototype/OtherDir/*', 'Prototype/*', false);
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
