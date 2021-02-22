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
use Symfony\Bridge\PhpUnit\ExpectDeprecationTrait;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Dumper\PhpDumper;
use Symfony\Component\DependencyInjection\Dumper\YamlDumper;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;

class PhpFileLoaderTest extends TestCase
{
    use ExpectDeprecationTrait;

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
        $container->getDefinition('errored_definition')->addError('Service "errored_definition" is broken.');

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
        $this->assertStringMatchesFormatFile($fixtures.'/config/'.$file.'.expected.yml', $dumper->dump());
    }

    public function provideConfig()
    {
        yield ['basic'];
        yield ['object'];
        yield ['defaults'];
        yield ['instanceof'];
        yield ['prototype'];
        yield ['prototype_array'];
        yield ['child'];
        yield ['php7'];
        yield ['anonymous'];
        yield ['lazy_fqcn'];
        yield ['remove'];
    }

    public function testAutoConfigureAndChildDefinition()
    {
        $fixtures = realpath(__DIR__.'/../Fixtures');
        $container = new ContainerBuilder();
        $loader = new PhpFileLoader($container, new FileLocator());
        $loader->load($fixtures.'/config/services_autoconfigure_with_parent.php');
        $container->compile();

        $this->assertTrue($container->getDefinition('child_service')->isAutoconfigured());
    }

    public function testFactoryShortNotationNotAllowed()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid factory "factory:method": the "service:method" notation is not available when using PHP-based DI configuration. Use "[service(\'factory\'), \'method\']" instead.');
        $fixtures = realpath(__DIR__.'/../Fixtures');
        $container = new ContainerBuilder();
        $loader = new PhpFileLoader($container, new FileLocator());
        $loader->load($fixtures.'/config/factory_short_notation.php');
        $container->compile();
    }

    public function testStack()
    {
        $container = new ContainerBuilder();

        $loader = new PhpFileLoader($container, new FileLocator(realpath(__DIR__.'/../Fixtures').'/config'));
        $loader->load('stack.php');

        $container->compile();

        $expected = (object) [
            'label' => 'A',
            'inner' => (object) [
                'label' => 'B',
                'inner' => (object) [
                    'label' => 'C',
                ],
            ],
        ];
        $this->assertEquals($expected, $container->get('stack_a'));
        $this->assertEquals($expected, $container->get('stack_b'));

        $expected = (object) [
            'label' => 'Z',
            'inner' => $expected,
        ];
        $this->assertEquals($expected, $container->get('stack_c'));

        $expected = $expected->inner;
        $expected->label = 'Z';
        $this->assertEquals($expected, $container->get('stack_d'));
    }

    public function testWhenEnv()
    {
        $container = new ContainerBuilder();
        $loader = new PhpFileLoader($container, new FileLocator(realpath(__DIR__.'/../Fixtures').'/config'), 'some-env');
        $loader->load('when-env.php');

        $this->assertSame(['foo' => 234, 'bar' => 345], $container->getParameterBag()->all());
    }

    /**
     * @group legacy
     */
    public function testDeprecatedWithoutPackageAndVersion()
    {
        $this->expectDeprecation('Since symfony/dependency-injection 5.1: The signature of method "Symfony\Component\DependencyInjection\Loader\Configurator\Traits\DeprecateTrait::deprecate()" requires 3 arguments: "string $package, string $version, string $message", not defining them is deprecated.');

        $fixtures = realpath(__DIR__.'/../Fixtures');
        $loader = new PhpFileLoader($container = new ContainerBuilder(), new FileLocator());
        $loader->load($fixtures.'/config/deprecated_without_package_version.php');
    }
}
