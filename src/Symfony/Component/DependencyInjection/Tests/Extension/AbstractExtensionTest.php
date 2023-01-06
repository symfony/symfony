<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DependencyInjection\Tests\Extension;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\ConfigurableInterface;
use Symfony\Component\Config\Definition\Configuration;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\AbstractExtension;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;

class AbstractExtensionTest extends TestCase
{
    public function testConfiguration()
    {
        $extension = new class() extends AbstractExtension {
            public function configure(DefinitionConfigurator $definition): void
            {
                // load one
                $definition->import('../Fixtures/config/definition/foo.php');

                // load multiples
                $definition->import('../Fixtures/config/definition/multiple/*.php');

                // inline
                $definition->rootNode()
                    ->children()
                        ->scalarNode('ping')->defaultValue('inline')->end()
                    ->end();
            }
        };

        $expected = [
            'foo' => 'one',
            'bar' => 'multi',
            'baz' => 'multi',
            'ping' => 'inline',
        ];

        self::assertSame($expected, $this->processConfiguration($extension));
    }

    public function testPrependAppendExtensionConfig()
    {
        $extension = new class() extends AbstractExtension {
            public function prependExtension(ContainerConfigurator $container, ContainerBuilder $builder): void
            {
                // append config
                $container->extension('third', ['foo' => 'append']);

                // prepend config
                $builder->prependExtensionConfig('third', ['foo' => 'prepend']);
            }
        };

        $container = $this->processPrependExtension($extension);

        $expected = [
            ['foo' => 'prepend'],
            ['foo' => 'bar'],
            ['foo' => 'append'],
        ];

        self::assertSame($expected, $container->getExtensionConfig('third'));
    }

    public function testLoadExtension()
    {
        $extension = new class() extends AbstractExtension {
            public function configure(DefinitionConfigurator $definition): void
            {
                $definition->import('../Fixtures/config/definition/foo.php');
            }

            public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
            {
                $container->parameters()
                    ->set('foo_param', $config)
                ;

                $container->services()
                    ->set('foo_service', \stdClass::class)
                ;

                $container->import('../Fixtures/config/services.php');
            }

            public function getAlias(): string
            {
                return 'micro';
            }
        };

        $container = $this->processLoadExtension($extension, [['foo' => 'bar']]);

        self::assertSame(['foo' => 'bar'], $container->getParameter('foo_param'));
        self::assertTrue($container->hasDefinition('foo_service'));
        self::assertTrue($container->hasDefinition('bar_service'));
    }

    protected function processConfiguration(ConfigurableInterface $configurable): array
    {
        $configuration = new Configuration($configurable, null, 'micro');

        return (new Processor())->process($configuration->getConfigTreeBuilder()->buildTree(), []);
    }

    protected function processPrependExtension(PrependExtensionInterface $extension): ContainerBuilder
    {
        $thirdExtension = new class() extends AbstractExtension {
            public function configure(DefinitionConfigurator $definition): void
            {
                $definition->import('../Fixtures/config/definition/foo.php');
            }

            public function getAlias(): string
            {
                return 'third';
            }
        };

        $container = $this->createContainerBuilder();
        $container->registerExtension($thirdExtension);
        $container->loadFromExtension('third', ['foo' => 'bar']);

        $extension->prepend($container);

        return $container;
    }

    protected function processLoadExtension(ExtensionInterface $extension, array $configs): ContainerBuilder
    {
        $container = $this->createContainerBuilder();

        $extension->load($configs, $container);

        return $container;
    }

    protected function createContainerBuilder(): ContainerBuilder
    {
        return new ContainerBuilder(new ParameterBag([
            'kernel.environment' => 'test',
            'kernel.build_dir' => 'test',
        ]));
    }
}
