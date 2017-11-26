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
use Symfony\Component\DependencyInjection\Compiler\MergeExtensionConfigurationPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;

class MergeExtensionConfigurationPassTest extends TestCase
{
    public function testExpressionLanguageProviderForwarding()
    {
        $tmpProviders = array();

        $extension = $this->getMockBuilder('Symfony\\Component\\DependencyInjection\\Extension\\ExtensionInterface')->getMock();
        $extension->expects($this->any())
            ->method('getXsdValidationBasePath')
            ->will($this->returnValue(false));
        $extension->expects($this->any())
            ->method('getNamespace')
            ->will($this->returnValue('http://example.org/schema/dic/foo'));
        $extension->expects($this->any())
            ->method('getAlias')
            ->will($this->returnValue('foo'));
        $extension->expects($this->once())
            ->method('load')
            ->will($this->returnCallback(function (array $config, ContainerBuilder $container) use (&$tmpProviders) {
                $tmpProviders = $container->getExpressionLanguageProviders();
            }));

        $provider = $this->getMockBuilder('Symfony\\Component\\ExpressionLanguage\\ExpressionFunctionProviderInterface')->getMock();
        $container = new ContainerBuilder(new ParameterBag());
        $container->registerExtension($extension);
        $container->prependExtensionConfig('foo', array('bar' => true));
        $container->addExpressionLanguageProvider($provider);

        $pass = new MergeExtensionConfigurationPass();
        $pass->process($container);

        $this->assertEquals(array($provider), $tmpProviders);
    }

    public function testExtensionConfigurationIsTrackedByDefault()
    {
        $extension = $this->getMockBuilder(FooExtension::class)->setMethods(array('getConfiguration'))->getMock();
        $extension->expects($this->exactly(2))
            ->method('getConfiguration')
            ->will($this->returnValue(new FooConfiguration()));

        $container = new ContainerBuilder(new ParameterBag());
        $container->registerExtension($extension);
        $container->prependExtensionConfig('foo', array('bar' => true));

        $pass = new MergeExtensionConfigurationPass();
        $pass->process($container);

        $this->assertContains(new FileResource(__FILE__), $container->getResources(), '', false, false);
    }

    public function testOverriddenEnvsAreMerged()
    {
        $container = new ContainerBuilder();
        $container->registerExtension(new FooExtension());
        $container->prependExtensionConfig('foo', array('bar' => '%env(FOO)%'));
        $container->prependExtensionConfig('foo', array('bar' => '%env(BAR)%', 'baz' => '%env(BAZ)%'));

        $pass = new MergeExtensionConfigurationPass();
        $pass->process($container);

        $this->assertSame(array('BAZ', 'FOO'), array_keys($container->getParameterBag()->getEnvPlaceholders()));
        $this->assertSame(array('BAZ' => 1, 'FOO' => 0), $container->getEnvCounters());
    }

    public function testThrowingExtensionsGetMergedBag()
    {
        $container = new ContainerBuilder();
        $container->registerExtension(new ThrowingExtension());
        $container->prependExtensionConfig('throwing', array('bar' => '%env(FOO)%'));

        try {
            $pass = new MergeExtensionConfigurationPass();
            $pass->process($container);
            $this->fail('An exception should have been thrown.');
        } catch (\Exception $e) {
        }

        $this->assertSame(array('FOO'), array_keys($container->getParameterBag()->getEnvPlaceholders()));
    }
}

class FooConfiguration implements ConfigurationInterface
{
    public function getConfigTreeBuilder()
    {
        $treeBuilder = new TreeBuilder();
        $rootNode = $treeBuilder->root('foo');
        $rootNode
            ->children()
                ->scalarNode('bar')->end()
                ->scalarNode('baz')->end()
            ->end();

        return $treeBuilder;
    }
}

class FooExtension extends Extension
{
    public function getAlias()
    {
        return 'foo';
    }

    public function getConfiguration(array $config, ContainerBuilder $container)
    {
        return new FooConfiguration();
    }

    public function load(array $configs, ContainerBuilder $container)
    {
        $configuration = $this->getConfiguration($configs, $container);
        $config = $this->processConfiguration($configuration, $configs);

        if (isset($config['baz'])) {
            $container->getParameterBag()->get('env(BOZ)');
            $container->resolveEnvPlaceholders($config['baz']);
        }
    }
}

class ThrowingExtension extends Extension
{
    public function getAlias()
    {
        return 'throwing';
    }

    public function getConfiguration(array $config, ContainerBuilder $container)
    {
        return new FooConfiguration();
    }

    public function load(array $configs, ContainerBuilder $container)
    {
        throw new \Exception();
    }
}
