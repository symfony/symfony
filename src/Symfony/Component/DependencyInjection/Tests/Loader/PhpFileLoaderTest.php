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
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Dumper\PhpDumper;
use Symfony\Component\DependencyInjection\Dumper\YamlDumper;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;

class PhpFileLoaderTest extends TestCase
{
    public function testSupports()
    {
        $loader = new PhpFileLoader(new ContainerBuilder(), new FileLocator());

        $this->assertTrue($loader->supports('foo.php'), '->supports() returns true if the resource is loadable');
        $this->assertFalse($loader->supports('foo.foo'), '->supports() returns false if the resource is not loadable');
        $this->assertTrue($loader->supports('with_wrong_ext.yml', 'php'), '->supports() returns true if the resource with forced type is loadable');
    }

    public function testLoad()
    {
        $loader = new PhpFileLoader($container = new ContainerBuilder(), new FileLocator());

        $loader->load(__DIR__.'/../Fixtures/php/simple.php');

        $this->assertEquals('foo', $container->getParameter('foo'), '->load() loads a PHP file resource');
    }

    public function testConfigServices()
    {
        $fixtures = realpath(__DIR__.'/../Fixtures');
        $loader = new PhpFileLoader($container = new ContainerBuilder(), new FileLocator());
        $loader->load($fixtures.'/config/services9.php');

        $container->compile();
        $dumper = new PhpDumper($container);
        $this->assertStringEqualsFile($fixtures.'/php/services9_compiled.php', str_replace(str_replace('\\', '\\\\', $fixtures.\DIRECTORY_SEPARATOR.'includes'.\DIRECTORY_SEPARATOR), '%path%', $dumper->dump()));
    }

    /**
     * @dataProvider provideConfig
     */
    public function testConfig($file)
    {
        $fixtures = realpath(__DIR__.'/../Fixtures');
        $loader = new PhpFileLoader($container = new ContainerBuilder(), new FileLocator());
        $loader->load($fixtures.'/config/'.$file.'.php');

        $container->compile();

        $dumper = new YamlDumper($container);
        $this->assertStringEqualsFile($fixtures.'/config/'.$file.'.expected.yml', $dumper->dump());
    }

    public function provideConfig()
    {
        yield array('basic');
        yield array('defaults');
        yield array('instanceof');
        yield array('prototype');
        yield array('child');

        if (\PHP_VERSION_ID >= 70000) {
            yield array('php7');
        }
    }

    /**
     * @expectedException \Symfony\Component\DependencyInjection\Exception\InvalidArgumentException
     * @expectedExceptionMessage The service "child_service" cannot have a "parent" and also have "autoconfigure". Try disabling autoconfiguration for the service.
     */
    public function testAutoConfigureAndChildDefinitionNotAllowed()
    {
        $fixtures = realpath(__DIR__.'/../Fixtures');
        $container = new ContainerBuilder();
        $loader = new PhpFileLoader($container, new FileLocator());
        $loader->load($fixtures.'/config/services_autoconfigure_with_parent.php');
        $container->compile();
    }

    /**
     * @expectedException \Symfony\Component\DependencyInjection\Exception\InvalidArgumentException
     * @expectedExceptionMessage Invalid factory "factory:method": the `service:method` notation is not available when using PHP-based DI configuration. Use "[ref('factory'), 'method']" instead.
     */
    public function testFactoryShortNotationNotAllowed()
    {
        $fixtures = realpath(__DIR__.'/../Fixtures');
        $container = new ContainerBuilder();
        $loader = new PhpFileLoader($container, new FileLocator());
        $loader->load($fixtures.'/config/factory_short_notation.php');
        $container->compile();
    }
}
