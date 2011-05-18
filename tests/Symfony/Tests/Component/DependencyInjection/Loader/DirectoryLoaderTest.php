<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Tests\Component\DependencyInjection\Loader;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\Config\Loader\Loader;
use Symfony\Component\DependencyInjection\Loader\DirectoryLoader;
use Symfony\Component\DependencyInjection\Loader\IniFileLoader;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\Config\Loader\LoaderResolver;
use Symfony\Component\Config\FileLocator;

class DirectoryLoaderTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @covers Symfony\Component\DependencyInjection\Loader\DirectoryLoader::supports
     */
    public function testSupports()
    {
        $loader = new DirectoryLoader(new ContainerBuilder(), new FileLocator());

        $this->assertTrue($loader->supports('conf.d' . DIRECTORY_SEPARATOR), '->supports() returns true if the resource is loadable');
        $this->assertFalse($loader->supports('config.xml'), '->supports() returns true if the resource is loadable');
    }

    /**
     * @covers Symfony\Component\DependencyInjection\Loader\DirectoryLoader::load
     */
    public function testLoad()
    {
        $fixturesPath = __DIR__.'/../Fixtures';
        
        $container = new ContainerBuilder();
        
        $resolver = new LoaderResolver(array(
            new IniFileLoader($container, new FileLocator($fixturesPath)),
            new PhpFileLoader($container, new FileLocator($fixturesPath)),
            new YamlFileLoader($container, new FileLocator($fixturesPath)),
            $loader = new DirectoryLoader($container, new FileLocator($fixturesPath)),
        ));
        
        $loader->setResolver($resolver);
        
        $loader->load('mixed/');

        $this->assertEquals('ok', $container->getParameter('php'), '->load() loads a PHP file resource from directory');
        $this->assertEquals('ok', $container->getParameter('ini'), '->load() loads a INI file resource from directory');
        $this->assertEquals('ok', $container->getParameter('yaml'), '->load() loads a YML file resource from subdirectory');
    }
}
