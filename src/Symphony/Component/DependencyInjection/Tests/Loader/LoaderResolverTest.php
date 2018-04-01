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

use PHPUnit\Framework\TestCase;
use Symphony\Component\Config\FileLocator;
use Symphony\Component\Config\Loader\LoaderResolver;
use Symphony\Component\DependencyInjection\ContainerBuilder;
use Symphony\Component\DependencyInjection\Loader\ClosureLoader;
use Symphony\Component\DependencyInjection\Loader\IniFileLoader;
use Symphony\Component\DependencyInjection\Loader\PhpFileLoader;
use Symphony\Component\DependencyInjection\Loader\XmlFileLoader;
use Symphony\Component\DependencyInjection\Loader\YamlFileLoader;

class LoaderResolverTest extends TestCase
{
    private static $fixturesPath;

    /** @var LoaderResolver */
    private $resolver;

    protected function setUp()
    {
        self::$fixturesPath = realpath(__DIR__.'/../Fixtures/');

        $container = new ContainerBuilder();
        $this->resolver = new LoaderResolver(array(
            new XmlFileLoader($container, new FileLocator(self::$fixturesPath.'/xml')),
            new YamlFileLoader($container, new FileLocator(self::$fixturesPath.'/yaml')),
            new IniFileLoader($container, new FileLocator(self::$fixturesPath.'/ini')),
            new PhpFileLoader($container, new FileLocator(self::$fixturesPath.'/php')),
            new ClosureLoader($container),
        ));
    }

    public function provideResourcesToLoad()
    {
        return array(
            array('ini_with_wrong_ext.xml', 'ini', IniFileLoader::class),
            array('xml_with_wrong_ext.php', 'xml', XmlFileLoader::class),
            array('php_with_wrong_ext.yml', 'php', PhpFileLoader::class),
            array('yaml_with_wrong_ext.ini', 'yaml', YamlFileLoader::class),
        );
    }

    /**
     * @dataProvider provideResourcesToLoad
     */
    public function testResolvesForcedType($resource, $type, $expectedClass)
    {
        $this->assertInstanceOf($expectedClass, $this->resolver->resolve($resource, $type));
    }
}
