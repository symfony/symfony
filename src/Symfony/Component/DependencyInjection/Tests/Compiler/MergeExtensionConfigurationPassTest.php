<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DependencyInjection\Tests\Compiler;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;
use Symfony\Component\Config\Resource\FileResource;
use Symfony\Component\DependencyInjection\Compiler\MergeExtensionConfigurationContainerBuilder;
use Symfony\Component\DependencyInjection\Compiler\MergeExtensionConfigurationPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Exception\RuntimeException;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;
use Symfony\Component\ExpressionLanguage\ExpressionFunctionProviderInterface;

class MergeExtensionConfigurationPassTest extends TestCase
{
    public function testExpressionLanguageProviderForwarding()
    {
        $tmpProviders = [];

        $extension = $this->createMock(ExtensionInterface::class);
        $extension->expects($this->any())
            ->method('getXsdValidationBasePath')
            ->willReturn(false);
        $extension->expects($this->any())
            ->method('getNamespace')
            ->willReturn('http://example.org/schema/dic/foo');
        $extension->expects($this->any())
            ->method('getAlias')
            ->willReturn('foo');
        $extension->expects($this->once())
            ->method('load')
            ->willReturnCallback(function (array $config, ContainerBuilder $container) use (&$tmpProviders) {
                $tmpProviders = $container->getExpressionLanguageProviders();
            });

        $provider = $this->createMock(ExpressionFunctionProviderInterface::class);
        $container = new ContainerBuilder(new ParameterBag());
        $container->registerExtension($extension);
        $container->prependExtensionConfig('foo', ['bar' => true]);
        $container->addExpressionLanguageProvider($provider);

        $pass = new MergeExtensionConfigurationPass();
        $pass->process($container);

        $this->assertEquals([$provider], $tmpProviders);
    }

    public function testExtensionLoadGetAMergeExtensionConfigurationContainerBuilderInstance()
    {
        $extension = $this->getMockBuilder(FooExtension::class)->onlyMethods(['load'])->getMock();
        $extension->expects($this->once())
            ->method('load')
            ->with($this->isType('array'), $this->isInstanceOf(MergeExtensionConfigurationContainerBuilder::class))
        ;

        $container = new ContainerBuilder(new ParameterBag());
        $container->registerExtension($extension);
        $container->prependExtensionConfig('foo', []);

        $pass = new MergeExtensionConfigurationPass();
        $pass->process($container);
    }

    public function testExtensionConfigurationIsTrackedByDefault()
    {
        $extension = $this->getMockBuilder(FooExtension::class)->onlyMethods(['getConfiguration'])->getMock();
        $extension->expects($this->exactly(2))
            ->method('getConfiguration')
            ->willReturn(new FooConfiguration());

        $container = new ContainerBuilder(new ParameterBag());
        $container->registerExtension($extension);
        $container->prependExtensionConfig('foo', ['bar' => true]);

        $pass = new MergeExtensionConfigurationPass();
        $pass->process($container);

        $this->assertContainsEquals(new FileResource(__FILE__), $container->getResources());
    }

    public function testOverriddenEnvsAreMerged()
    {
        $container = new ContainerBuilder();
        $container->registerExtension(new FooExtension());
        $container->prependExtensionConfig('foo', ['bar' => '%env(FOO)%']);
        $container->prependExtensionConfig('foo', ['bar' => '%env(BAR)%', 'baz' => '%env(BAZ)%']);

        $pass = new MergeExtensionConfigurationPass();
        $pass->process($container);

        $this->assertSame(['BAZ', 'FOO'], array_keys($container->getParameterBag()->getEnvPlaceholders()));
        $this->assertSame(['BAZ' => 1, 'FOO' => 0], $container->getEnvCounters());
    }

    public function testProcessedEnvsAreIncompatibleWithResolve()
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Using a cast in "env(int:FOO)" is incompatible with resolution at compile time in "Symfony\Component\DependencyInjection\Tests\Compiler\BarExtension". The logic in the extension should be moved to a compiler pass, or an env parameter with no cast should be used instead.');
        $container = new ContainerBuilder();
        $container->registerExtension(new BarExtension());
        $container->prependExtensionConfig('bar', []);

        (new MergeExtensionConfigurationPass())->process($container);
    }

    public function testThrowingExtensionsGetMergedBag()
    {
        $container = new ContainerBuilder();
        $container->registerExtension(new ThrowingExtension());
        $container->prependExtensionConfig('throwing', ['bar' => '%env(FOO)%']);

        try {
            $pass = new MergeExtensionConfigurationPass();
            $pass->process($container);
            $this->fail('An exception should have been thrown.');
        } catch (\Exception $e) {
        }

        $this->assertSame(['FOO'], array_keys($container->getParameterBag()->getEnvPlaceholders()));
    }

    public function testReuseEnvPlaceholderGeneratedByPreviousExtension()
    {
        $container = new ContainerBuilder();
        $container->registerExtension(new FooExtension());
        $container->registerExtension(new TestCccExtension());
        $container->prependExtensionConfig('foo', ['bool_node' => '%env(bool:MY_ENV_VAR)%']);
        $container->prependExtensionConfig('test_ccc', ['bool_node' => '%env(bool:MY_ENV_VAR)%']);

        (new MergeExtensionConfigurationPass())->process($container);

        $this->addToAssertionCount(1);
    }
}

class FooConfiguration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('foo');
        $treeBuilder->getRootNode()
            ->children()
                ->scalarNode('bar')->end()
                ->scalarNode('baz')->end()
                ->booleanNode('bool_node')->end()
            ->end();

        return $treeBuilder;
    }
}

class FooExtension extends Extension
{
    public function getAlias(): string
    {
        return 'foo';
    }

    public function getConfiguration(array $config, ContainerBuilder $container): ?ConfigurationInterface
    {
        return new FooConfiguration();
    }

    public function load(array $configs, ContainerBuilder $container): void
    {
        $configuration = $this->getConfiguration($configs, $container);
        $config = $this->processConfiguration($configuration, $configs);

        if (isset($config['baz'])) {
            $container->getParameterBag()->get('env(BOZ)');
            $container->resolveEnvPlaceholders($config['baz']);
        }

        $container->setParameter('foo.param', 'ccc');
    }
}

class BarExtension extends Extension
{
    public function load(array $configs, ContainerBuilder $container): void
    {
        $container->resolveEnvPlaceholders('%env(int:FOO)%', true);
    }
}

class ThrowingExtension extends Extension
{
    public function getAlias(): string
    {
        return 'throwing';
    }

    public function getConfiguration(array $config, ContainerBuilder $container): ?ConfigurationInterface
    {
        return new FooConfiguration();
    }

    public function load(array $configs, ContainerBuilder $container): void
    {
        throw new \Exception();
    }
}

final class TestCccConfiguration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('test_ccc');
        $treeBuilder->getRootNode()
            ->children()
                ->booleanNode('bool_node')->end()
            ->end();

        return $treeBuilder;
    }
}

final class TestCccExtension extends Extension
{
    public function getAlias(): string
    {
        return 'test_ccc';
    }

    public function getConfiguration(array $config, ContainerBuilder $container): ?ConfigurationInterface
    {
        return new TestCccConfiguration();
    }

    public function load(array $configs, ContainerBuilder $container): void
    {
        $configuration = $this->getConfiguration($configs, $container);
        $this->processConfiguration($configuration, $configs);
    }
}
