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
use Symfony\Component\DependencyInjection\Loader\FileLoader;
use Symfony\Component\DependencyInjection\Loader\IniFileLoader;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\DependencyInjection\Reference;
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

        $this->assertTrue($container->has(Bar::class));
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
