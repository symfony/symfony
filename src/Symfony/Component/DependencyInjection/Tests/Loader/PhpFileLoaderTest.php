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

require_once __DIR__.'/../Fixtures/includes/AcmeExtension.php';

use PHPUnit\Framework\TestCase;
use Symfony\Bridge\PhpUnit\ExpectDeprecationTrait;
use Symfony\Component\Config\Builder\ConfigBuilderGenerator;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\Argument\ServiceLocatorArgument;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Dumper\PhpDumper;
use Symfony\Component\DependencyInjection\Dumper\YamlDumper;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\DependencyInjection\Tests\Fixtures\FooClassWithEnumAttribute;
use Symfony\Component\DependencyInjection\Tests\Fixtures\FooUnitEnum;

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

    public function testConfigServiceClosure()
    {
        $fixtures = realpath(__DIR__.'/../Fixtures');
        $loader = new PhpFileLoader($container = new ContainerBuilder(), new FileLocator());
        $loader->load($fixtures.'/config/services_closure_argument.php');

        $container->compile();
        $dumper = new PhpDumper($container);
        $this->assertStringEqualsFile($fixtures.'/php/services_closure_argument_compiled.php', $dumper->dump());
    }

    /**
     * @dataProvider provideConfig
     */
    public function testConfig($file)
    {
        $fixtures = realpath(__DIR__.'/../Fixtures');
        $container = new ContainerBuilder();
        $container->registerExtension(new \AcmeExtension());
        $loader = new PhpFileLoader($container, new FileLocator(), 'prod', new ConfigBuilderGenerator(sys_get_temp_dir()));
        $loader->load($fixtures.'/config/'.$file.'.php');

        $container->compile();

        $dumper = new YamlDumper($container);
        $this->assertStringMatchesFormatFile($fixtures.'/config/'.$file.'.expected.yml', $dumper->dump());
    }

    public static function provideConfig()
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
        yield ['inline_binding'];
        yield ['remove'];
        yield ['config_builder'];
        yield ['expression_factory'];
        yield ['static_constructor'];
        yield ['inline_static_constructor'];
        yield ['instanceof_static_constructor'];
        yield ['closure'];
        yield ['from_callable'];
        yield ['env_param'];
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

    public function testEnvConfigurator()
    {
        $container = new ContainerBuilder();
        $loader = new PhpFileLoader($container, new FileLocator(realpath(__DIR__.'/../Fixtures').'/config'), 'some-env');
        $loader->load('env_configurator.php');

        $this->assertSame('%env(int:CCC)%', $container->getDefinition('foo')->getArgument(0));
    }

    public function testEnumeration()
    {
        $fixtures = realpath(__DIR__.'/../Fixtures');
        $container = new ContainerBuilder();
        $loader = new PhpFileLoader($container, new FileLocator($fixtures.'/config'));
        $loader->load('services_with_enumeration.php');

        $container->compile();

        $definition = $container->getDefinition(FooClassWithEnumAttribute::class);
        $this->assertSame([FooUnitEnum::BAR], $definition->getArguments());
    }

    public function testNestedBundleConfigNotAllowed()
    {
        $fixtures = realpath(__DIR__.'/../Fixtures');
        $container = new ContainerBuilder();
        $loader = new PhpFileLoader($container, new FileLocator(), 'prod', new ConfigBuilderGenerator(sys_get_temp_dir()));

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/^'.preg_quote('Could not resolve argument "Symfony\\Config\\AcmeConfig\\NestedConfig $config"', '/').'/');

        $loader->load($fixtures.'/config/nested_bundle_config.php');
    }

    public function testWhenEnv()
    {
        $this->expectNotToPerformAssertions();

        $fixtures = realpath(__DIR__.'/../Fixtures');
        $container = new ContainerBuilder();
        $loader = new PhpFileLoader($container, new FileLocator(), 'dev', new ConfigBuilderGenerator(sys_get_temp_dir()));

        $loader->load($fixtures.'/config/when_env.php');
    }

    /**
     * @group legacy
     */
    public function testServiceWithServiceLocatorArgument()
    {
        $this->expectDeprecation('Since symfony/dependency-injection 6.3: Using integers as keys in a "service_locator()" argument is deprecated. The keys will default to the IDs of the original services in 7.0.');

        $fixtures = realpath(__DIR__.'/../Fixtures');
        $loader = new PhpFileLoader($container = new ContainerBuilder(), new FileLocator());
        $loader->load($fixtures.'/config/services_with_service_locator_argument.php');

        $values = ['foo' => new Reference('foo_service'), 'bar' => new Reference('bar_service')];
        $this->assertEquals([new ServiceLocatorArgument($values)], $container->getDefinition('locator_dependent_service_indexed')->getArguments());

        $values = [new Reference('foo_service'), new Reference('bar_service')];
        $this->assertEquals([new ServiceLocatorArgument($values)], $container->getDefinition('locator_dependent_service_not_indexed')->getArguments());

        $values = ['foo' => new Reference('foo_service'), 0 => new Reference('bar_service')];
        $this->assertEquals([new ServiceLocatorArgument($values)], $container->getDefinition('locator_dependent_service_mixed')->getArguments());
    }
}
