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
use Symfony\Component\DependencyInjection\Loader\ClosureLoader;
use Symfony\Component\DependencyInjection\Loader\IniFileLoader;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;

class LoaderResolverTest extends TestCase
{
    private static $fixturesPath;

    /** @var LoaderResolver */
    private $resolver;

    protected function setUp(): void
    {
        self::$fixturesPath = realpath(__DIR__.'/../Fixtures/');

        $container = new ContainerBuilder();
        $this->resolver = new LoaderResolver([
            new XmlFileLoader($container, new FileLocator(self::$fixturesPath.'/xml')),
            new YamlFileLoader($container, new FileLocator(self::$fixturesPath.'/yaml')),
            new IniFileLoader($container, new FileLocator(self::$fixturesPath.'/ini')),
            new PhpFileLoader($container, new FileLocator(self::$fixturesPath.'/php')),
            new ClosureLoader($container),
        ]);
    }

    public function provideResourcesToLoad()
    {
        return [
            ['ini_with_wrong_ext.xml', 'ini', IniFileLoader::class],
            ['xml_with_wrong_ext.php', 'xml', XmlFileLoader::class],
            ['php_with_wrong_ext.yml', 'php', PhpFileLoader::class],
            ['yaml_with_wrong_ext.ini', 'yaml', YamlFileLoader::class],
        ];
    }

    /**
     * @dataProvider provideResourcesToLoad
     */
    public function testResolvesForcedType($resource, $type, $expectedClass)
    {
        $this->assertInstanceOf($expectedClass, $this->resolver->resolve($resource, $type));
    }
}
