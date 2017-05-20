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
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;
use Symfony\Component\DependencyInjection\Loader\FileLoader;
use Symfony\Component\DependencyInjection\Loader\IniFileLoader;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\DependencyInjection\Tests\Fixtures\Prototype\OtherDir\AnotherSub\DeeperBaz;
use Symfony\Component\DependencyInjection\Tests\Fixtures\Prototype\OtherDir\Baz;
use Symfony\Component\DependencyInjection\Tests\Fixtures\Prototype\Sub\Bar;

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

        $loader->registerClasses(new Definition(), 'Symfony\Component\DependencyInjection\Tests\Fixtures\Prototype\Sub\\', 'Prototype/%sub_dir%/*');

        $this->assertEquals(
            array('service_container', Bar::class),
            array_keys($container->getDefinitions())
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
    }

    /**
     * @expectedException \Symfony\Component\DependencyInjection\Exception\InvalidArgumentException
     * @expectedExceptionMessageRegExp /Expected to find class "Symfony\\Component\\DependencyInjection\\Tests\\Fixtures\\Prototype\\Bar" in file ".+" while importing services from resource "Prototype\/Sub\/\*", but it was not found\! Check the namespace prefix used with the resource/
     */
    public function testRegisterClassesWithBadPrefix()
    {
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
