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
require_once __DIR__.'/../Fixtures/includes/fixture_app_services.php';

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\IgnoreDeprecations;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Builder\ConfigBuilderGenerator;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\Argument\ServiceLocatorArgument;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Dumper\PhpDumper;
use Symfony\Component\DependencyInjection\Dumper\YamlDumper;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;
use Symfony\Component\DependencyInjection\Exception\LogicException;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\DependencyInjection\Tests\Fixtures\FooClassWithEnumAttribute;
use Symfony\Component\DependencyInjection\Tests\Fixtures\FooUnitEnum;
use Symfony\Config\ServicesConfig;

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

        $this->assertTrue(class_exists(ServicesConfig::class));
        $this->assertTrue(\function_exists('Symfony\Config\service'));
        $this->assertTrue(\function_exists('Symfony\Component\DependencyInjection\Loader\Configurator\service'));

        $configCode = explode("\n/**", file_get_contents(\dirname(__DIR__, 2).'/Loader/Config/functions.php'), 2);
        $configuratorCode = explode("\n/**", file_get_contents(\dirname(__DIR__, 2).'/Loader/Configurator/functions.php'), 2);

        $this->assertStringEqualsFile(\dirname(__DIR__, 2).'/Loader/Config/functions.php', $configCode[0]."\n/**".$configuratorCode[1]);
        $this->assertStringEqualsFile(\dirname(__DIR__, 2).'/Loader/Configurator/functions.php', $configuratorCode[0]."\n/**".$configCode[1]);
    }

    public function testPrependExtensionConfigWithLoadMethod()
    {
        $container = new ContainerBuilder();
        $container->registerExtension(new \AcmeExtension());
        $container->prependExtensionConfig('acme', ['foo' => 'bar']);
        $loader = new PhpFileLoader($container, new FileLocator(\dirname(__DIR__).'/Fixtures'), 'prod', new ConfigBuilderGenerator(sys_get_temp_dir()), true);
        $loader->load('config/config_builder.php');

        $expected = [
            ['color' => 'red'],
            ['color' => 'blue'],
            ['foo' => 'bar'],
        ];
        $this->assertSame($expected, $container->getExtensionConfig('acme'));
    }

    public function testPrependExtensionConfigWithImportMethod()
    {
        $container = new ContainerBuilder();
        $container->registerExtension(new \AcmeExtension());
        $container->prependExtensionConfig('acme', ['foo' => 'bar']);
        $loader = new PhpFileLoader($container, new FileLocator(\dirname(__DIR__).'/Fixtures'), 'prod', new ConfigBuilderGenerator(sys_get_temp_dir()), true);
        $loader->import('config/config_builder.php');

        $expected = [
            ['color' => 'red'],
            ['color' => 'blue'],
            ['foo' => 'bar'],
        ];
        $this->assertSame($expected, $container->getExtensionConfig('acme'));
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

    #[DataProvider('provideConfig')]
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
        yield ['array_config'];
        yield ['object_array_config'];
        yield ['return_when_env'];
    }

    public function testResourceTags()
    {
        $fixtures = realpath(__DIR__.'/../Fixtures');
        $loader = new PhpFileLoader($container = new ContainerBuilder(), new FileLocator());
        $loader->load($fixtures.'/config/resource_tags.php');

        $def = $container->getDefinition('foo');
        $this->assertTrue($def->hasTag('container.excluded'));
        $this->assertTrue($def->hasTag('my.tag'));
        $this->assertTrue($def->hasTag('another.tag'));
        $this->assertSame([['foo' => 'bar']], $def->getTag('my.tag'));
        $this->assertSame([[]], $def->getTag('another.tag'));
        $this->assertFalse($def->isAbstract());
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

    public function testNotWhenEnv()
    {
        $this->expectNotToPerformAssertions();

        $fixtures = realpath(__DIR__.'/../Fixtures');
        $container = new ContainerBuilder();
        $loader = new PhpFileLoader($container, new FileLocator(), 'prod', new ConfigBuilderGenerator(sys_get_temp_dir()));

        $loader->load($fixtures.'/config/not_when_env.php');
    }

    public function testUsingBothWhenAndNotWhenEnv()
    {
        $fixtures = realpath(__DIR__.'/../Fixtures');
        $container = new ContainerBuilder();
        $loader = new PhpFileLoader($container, new FileLocator(), 'prod', new ConfigBuilderGenerator(sys_get_temp_dir()));

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Using both #[When] and #[WhenNot] attributes on the same target is not allowed.');

        $loader->load($fixtures.'/config/when_not_when_env.php');
    }

    public function testServiceWithServiceLocatorArgument()
    {
        $fixtures = realpath(__DIR__.'/../Fixtures');
        $loader = new PhpFileLoader($container = new ContainerBuilder(), new FileLocator());
        $loader->load($fixtures.'/config/services_with_service_locator_argument.php');

        $values = ['foo' => new Reference('foo_service'), 'bar' => new Reference('bar_service')];
        $this->assertEquals([new ServiceLocatorArgument($values)], $container->getDefinition('locator_dependent_service_indexed')->getArguments());

        $values = [new Reference('foo_service'), new Reference('bar_service')];
        $this->assertEquals([new ServiceLocatorArgument($values)], $container->getDefinition('locator_dependent_service_not_indexed')->getArguments());

        $values = ['foo' => new Reference('foo_service'), 0 => new Reference('bar_service')];
        $this->assertEquals([new ServiceLocatorArgument($values)], $container->getDefinition('locator_dependent_service_mixed')->getArguments());

        $values = ['foo' => new Definition(\stdClass::class), 'bar' => new Definition(\stdClass::class)];
        $this->assertEquals([new ServiceLocatorArgument($values)], $container->getDefinition('locator_dependent_inline_service')->getArguments());
    }

    public function testConfigBuilderEnvConfigurator()
    {
        $container = new ContainerBuilder();
        $container->registerExtension(new \AcmeExtension());
        $loader = new PhpFileLoader($container, new FileLocator(\dirname(__DIR__).'/Fixtures'), 'prod', new ConfigBuilderGenerator(sys_get_temp_dir()), true);
        $loader->load('config/config_builder_env_configurator.php');

        $this->assertIsString($container->getExtensionConfig('acme')[0]['color']);
    }

    public function testNamedClosure()
    {
        $container = new ContainerBuilder();
        $loader = new PhpFileLoader($container, new FileLocator(\dirname(__DIR__).'/Fixtures/config'), 'some-env');
        $loader->load('named_closure.php');
        $container->compile();
        $dumper = new PhpDumper($container);
        $this->assertStringEqualsFile(\dirname(__DIR__).'/Fixtures/php/named_closure_compiled.php', $dumper->dump());
    }

    public function testReturnsConfigBuilderObject()
    {
        $container = new ContainerBuilder();
        $container->registerExtension(new \AcmeExtension());
        $loader = new PhpFileLoader($container, new FileLocator(\dirname(__DIR__).'/Fixtures/config'), 'prod', new ConfigBuilderGenerator(sys_get_temp_dir()));

        $loader->load('return_config_builder.php');

        $this->assertSame([['color' => 'red']], $container->getExtensionConfig('acme'));
    }

    public function testReturnsIterableOfArraysAndBuilders()
    {
        $container = new ContainerBuilder();
        $container->registerExtension(new \AcmeExtension());
        $loader = new PhpFileLoader($container, new FileLocator(\dirname(__DIR__).'/Fixtures/config'), 'prod', new ConfigBuilderGenerator(sys_get_temp_dir()));

        $loader->load('return_iterable_configs.php');

        $configs = $container->getExtensionConfig('acme');
        $this->assertCount(2, $configs);
        $this->assertSame('red', $configs[0]['color']);
        $this->assertArrayHasKey('color', $configs[1]);
    }

    public function testThrowsOnInvalidReturnType()
    {
        $container = new ContainerBuilder();
        $loader = new PhpFileLoader($container, new FileLocator(\dirname(__DIR__).'/Fixtures/config'), 'prod', new ConfigBuilderGenerator(sys_get_temp_dir()));

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/The return value in config file/');

        $loader->load('return_invalid_types.php');
    }

    public function testReturnsGenerator()
    {
        $container = new ContainerBuilder();
        $container->registerExtension(new \AcmeExtension());
        $loader = new PhpFileLoader($container, new FileLocator(\dirname(__DIR__).'/Fixtures/config'), 'prod', new ConfigBuilderGenerator(sys_get_temp_dir()));

        $loader->load('return_generator.php');
        $this->assertSame([['color' => 'red']], $container->getExtensionConfig('acme'));
    }

    #[IgnoreDeprecations]
    #[Group('legacy')]
    public function testTriggersDeprecationWhenAccessingLoaderInternalScope()
    {
        $fixtures = realpath(__DIR__.'/../Fixtures');
        $loader = new PhpFileLoader(new ContainerBuilder(), new FileLocator($fixtures.'/config'));

        $this->expectUserDeprecationMessageMatches('{^Since symfony/dependency-injection 7.4: Using \`\$this\` or its internal scope in config files is deprecated, use the \`\$loader\` variable instead in ".+" on line \d+\.$}');

        $loader->load('legacy_internal_scope.php');
    }
}
