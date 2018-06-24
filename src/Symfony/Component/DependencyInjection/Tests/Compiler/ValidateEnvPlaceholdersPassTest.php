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
use Symfony\Component\Config\Definition\Exception\TreeWithoutRootNodeException;
use Symfony\Component\DependencyInjection\Compiler\MergeExtensionConfigurationPass;
use Symfony\Component\DependencyInjection\Compiler\RegisterEnvVarProcessorsPass;
use Symfony\Component\DependencyInjection\Compiler\ValidateEnvPlaceholdersPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;

class ValidateEnvPlaceholdersPassTest extends TestCase
{
    public function testEnvsAreValidatedInConfig()
    {
        $container = new ContainerBuilder();
        $container->setParameter('env(NULLED)', null);
        $container->setParameter('env(FLOATISH)', 3.2);
        $container->registerExtension($ext = new EnvExtension());
        $container->prependExtensionConfig('env_extension', $expected = array(
            'scalar_node' => '%env(NULLED)%',
            'scalar_node_not_empty' => '%env(FLOATISH)%',
            'int_node' => '%env(int:FOO)%',
            'float_node' => '%env(float:BAR)%',
        ));

        $this->doProcess($container);

        $this->assertSame($expected, $container->resolveEnvPlaceholders($ext->getConfig()));
    }

    public function testDefaultEnvWithoutPrefixIsValidatedInConfig()
    {
        $container = new ContainerBuilder();
        $container->setParameter('env(FLOATISH)', 3.2);
        $container->registerExtension($ext = new EnvExtension());
        $container->prependExtensionConfig('env_extension', $expected = array(
            'float_node' => '%env(FLOATISH)%',
        ));

        $this->doProcess($container);

        $this->assertSame($expected, $container->resolveEnvPlaceholders($ext->getConfig()));
    }

    /**
     * @expectedException \Symfony\Component\Config\Definition\Exception\InvalidTypeException
     * @expectedExceptionMessage Invalid type for path "env_extension.bool_node". Expected "bool", but got one of "bool", "int", "float", "string", "array".
     */
    public function testEnvsAreValidatedInConfigWithInvalidPlaceholder()
    {
        $container = new ContainerBuilder();
        $container->registerExtension($ext = new EnvExtension());
        $container->prependExtensionConfig('env_extension', $expected = array(
            'bool_node' => '%env(const:BAZ)%',
        ));

        $this->doProcess($container);

        $this->assertSame($expected, $container->resolveEnvPlaceholders($ext->getConfig()));
    }

    /**
     * @expectedException \Symfony\Component\Config\Definition\Exception\InvalidTypeException
     * @expectedExceptionMessage Invalid type for path "env_extension.int_node". Expected "int", but got "array".
     */
    public function testInvalidEnvInConfig()
    {
        $container = new ContainerBuilder();
        $container->registerExtension(new EnvExtension());
        $container->prependExtensionConfig('env_extension', array(
            'int_node' => '%env(json:FOO)%',
        ));

        $this->doProcess($container);
    }

    /**
     * @expectedException \Symfony\Component\Config\Definition\Exception\InvalidTypeException
     * @expectedExceptionMessage Invalid type for path "env_extension.int_node". Expected int, but got NULL.
     */
    public function testNulledEnvInConfig()
    {
        $container = new ContainerBuilder();
        $container->setParameter('env(NULLED)', null);
        $container->registerExtension(new EnvExtension());
        $container->prependExtensionConfig('env_extension', array(
            'int_node' => '%env(NULLED)%',
        ));

        $this->doProcess($container);
    }

    public function testValidateEnvOnMerge()
    {
        $container = new ContainerBuilder();
        $container->registerExtension($ext = new EnvExtension());
        $container->prependExtensionConfig('env_extension', array(
            'int_node' => '%env(int:const:FOO)%',
            'bool_node' => true,
        ));
        $container->prependExtensionConfig('env_extension', array(
            'int_node' => '%env(int:BAR)%',
            'bool_node' => '%env(bool:int:BAZ)%',
            'scalar_node' => '%env(BAZ)%',
        ));

        $this->doProcess($container);

        $expected = array(
            'int_node' => '%env(int:const:FOO)%',
            'bool_node' => true,
            'scalar_node' => '%env(BAZ)%',
        );

        $this->assertSame($expected, $container->resolveEnvPlaceholders($ext->getConfig()));
    }

    public function testConcatenatedEnvInConfig()
    {
        $container = new ContainerBuilder();
        $container->registerExtension($ext = new EnvExtension());
        $container->prependExtensionConfig('env_extension', array(
            'scalar_node' => $expected = 'foo %env(BAR)% baz',
        ));

        $this->doProcess($container);

        $this->assertSame(array('scalar_node' => $expected), $container->resolveEnvPlaceholders($ext->getConfig()));
    }

    /**
     * @expectedException \Symfony\Component\Config\Definition\Exception\InvalidConfigurationException
     * @expectedExceptionMessage A dynamic value is not compatible with a "Symfony\Component\Config\Definition\EnumNode" node type at path "env_extension.enum_node".
     */
    public function testEnvIsIncompatibleWithEnumNode()
    {
        $container = new ContainerBuilder();
        $container->registerExtension(new EnvExtension());
        $container->prependExtensionConfig('env_extension', array(
            'enum_node' => '%env(FOO)%',
        ));

        $this->doProcess($container);
    }

    /**
     * @expectedException \Symfony\Component\Config\Definition\Exception\InvalidConfigurationException
     * @expectedExceptionMessage A dynamic value is not compatible with a "Symfony\Component\Config\Definition\ArrayNode" node type at path "env_extension.simple_array_node".
     */
    public function testEnvIsIncompatibleWithArrayNode()
    {
        $container = new ContainerBuilder();
        $container->registerExtension(new EnvExtension());
        $container->prependExtensionConfig('env_extension', array(
            'simple_array_node' => '%env(json:FOO)%',
        ));

        $this->doProcess($container);
    }

    public function testNormalizedEnvIsCompatibleWithArrayNode()
    {
        $container = new ContainerBuilder();
        $container->registerExtension($ext = new EnvExtension());
        $container->prependExtensionConfig('env_extension', array(
            'array_node' => $expected = '%env(CHILD)%',
        ));

        $this->doProcess($container);

        $this->assertSame(array('array_node' => array('child_node' => $expected)), $container->resolveEnvPlaceholders($ext->getConfig()));
    }

    public function testEnvIsNotUnset()
    {
        $container = new ContainerBuilder();
        $container->registerExtension($ext = new EnvExtension());
        $container->prependExtensionConfig('env_extension', $expected = array(
            'array_node' => array('int_unset_at_zero' => '%env(int:CHILD)%'),
        ));

        $this->doProcess($container);

        $this->assertSame($expected, $container->resolveEnvPlaceholders($ext->getConfig()));
    }

    public function testEmptyEnvWhichCannotBeEmptyForScalarNode(): void
    {
        $container = new ContainerBuilder();
        $container->registerExtension($ext = new EnvExtension());
        $container->prependExtensionConfig('env_extension', $expected = array(
            'scalar_node_not_empty' => '%env(SOME)%',
        ));

        $this->doProcess($container);

        $this->assertSame($expected, $container->resolveEnvPlaceholders($ext->getConfig()));
    }

    public function testEnvWithVariableNode(): void
    {
        $container = new ContainerBuilder();
        $container->registerExtension($ext = new EnvExtension());
        $container->prependExtensionConfig('env_extension', $expected = array(
            'variable_node' => '%env(SOME)%',
        ));

        $this->doProcess($container);

        $this->assertSame($expected, $container->resolveEnvPlaceholders($ext->getConfig()));
    }

    public function testConfigurationWithoutRootNode(): void
    {
        $container = new ContainerBuilder();
        $container->registerExtension(new EnvExtension(new EnvConfigurationWithoutRootNode()));
        $container->loadFromExtension('env_extension');

        $this->doProcess($container);

        $this->addToAssertionCount(1);
    }

    public function testDiscardedEnvInConfig(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter('env(BOOLISH)', '1');
        $container->setParameter('boolish', '%env(BOOLISH)%');
        $container->registerExtension(new EnvExtension());
        $container->prependExtensionConfig('env_extension', array(
            'array_node' => array('bool_force_cast' => '%boolish%'),
        ));

        $container->compile(true);

        $this->assertSame('1', $container->getParameter('boolish'));
    }

    private function doProcess(ContainerBuilder $container): void
    {
        (new MergeExtensionConfigurationPass())->process($container);
        (new RegisterEnvVarProcessorsPass())->process($container);
        (new ValidateEnvPlaceholdersPass())->process($container);
    }
}

class EnvConfiguration implements ConfigurationInterface
{
    public function getConfigTreeBuilder()
    {
        $treeBuilder = new TreeBuilder();
        $rootNode = $treeBuilder->root('env_extension');
        $rootNode
            ->children()
                ->scalarNode('scalar_node')->end()
                ->scalarNode('scalar_node_not_empty')->cannotBeEmpty()->end()
                ->integerNode('int_node')->end()
                ->floatNode('float_node')->end()
                ->booleanNode('bool_node')->end()
                ->arrayNode('array_node')
                    ->beforeNormalization()
                        ->ifTrue(function ($value) { return !is_array($value); })
                        ->then(function ($value) { return array('child_node' => $value); })
                    ->end()
                    ->beforeNormalization()
                        ->ifArray()
                        ->then(function (array $v) {
                            if (isset($v['bool_force_cast'])) {
                                $v['bool_force_cast'] = (bool) $v['bool_force_cast'];
                            }

                            return $v;
                        })
                    ->end()
                    ->children()
                        ->scalarNode('child_node')->end()
                        ->booleanNode('bool_force_cast')->end()
                        ->integerNode('int_unset_at_zero')
                            ->validate()
                                ->ifTrue(function ($value) { return 0 === $value; })
                                ->thenUnset()
                            ->end()
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('simple_array_node')->end()
                ->enumNode('enum_node')->values(array('a', 'b'))->end()
                ->variableNode('variable_node')->end()
            ->end();

        return $treeBuilder;
    }
}

class EnvConfigurationWithoutRootNode implements ConfigurationInterface
{
    public function getConfigTreeBuilder()
    {
        return new TreeBuilder();
    }
}

class EnvExtension extends Extension
{
    private $configuration;
    private $config;

    public function __construct(ConfigurationInterface $configuration = null)
    {
        $this->configuration = $configuration ?? new EnvConfiguration();
    }

    public function getAlias()
    {
        return 'env_extension';
    }

    public function getConfiguration(array $config, ContainerBuilder $container)
    {
        return $this->configuration;
    }

    public function load(array $configs, ContainerBuilder $container)
    {
        try {
            $this->config = $this->processConfiguration($this->getConfiguration($configs, $container), $configs);
        } catch (TreeWithoutRootNodeException $e) {
            $this->config = null;
        }
    }

    public function getConfig()
    {
        return $this->config;
    }
}
