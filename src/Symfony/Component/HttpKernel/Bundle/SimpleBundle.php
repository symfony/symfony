<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\Bundle;

use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\Config\Loader\DelegatingLoader;
use Symfony\Component\Config\Loader\LoaderResolver;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\DependencyInjection\Loader\IniFileLoader;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;
use Symfony\Component\DependencyInjection\Loader\DirectoryLoader;
use Symfony\Component\DependencyInjection\Loader\ClosureLoader;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;

/**
 * An implementation of a simple all-in-one {@link BundleInterface} and
 * configurable {@link Extension}.
 *
 * @author Guilhem N. <egetick@gmail.com>
 */
abstract class SimpleBundle extends Bundle implements ConfigurationInterface
{
    /**
     * {@inheritdoc}
     */
    final public function getContainerExtension()
    {
        if (null === $this->extension) {
            $this->extension = new SimpleExtension($this);
        }

        return $this->extension;
    }

    /**
     * {@inheritdoc}
     */
    final public function getConfigTreeBuilder()
    {
        $treeBuilder = new TreeBuilder();
        $rootNode = $treeBuilder->root($this->getContainerExtension()->getAlias());

        $this->buildConfiguration($rootNode);

        return $treeBuilder;
    }

    protected function buildConfiguration(ArrayNodeDefinition $rootNode)
    {
    }

    protected function load(array $config, ContainerBuilder $container, LoaderInterface $loader)
    {
    }
}

/**
 * Simple {@link Extension} supporting {@link SimpleBundle}.
 *
 * @internal
 */
final class SimpleExtension extends Extension
{
    private $bundle;

    public function __construct(SimpleBundle $bundle)
    {
        $this->bundle = $bundle;
    }

    /**
     * {@inheritdoc}
     */
    public function getAlias()
    {
        // check naming convention
        $basename = preg_replace('/Bundle$/', '', $this->bundle->getName());

        return Container::underscore($basename);
    }

    /**
     * {@inheritdoc}
     */
    public function load(array $configs, ContainerBuilder $container)
    {
        $config = $this->processConfiguration($this->bundle, $configs);

        // Loader
        $locator = new FileLocator($this->bundle->getPath().'/Resources/config');
        $resolver = new LoaderResolver(array(
            new XmlFileLoader($container, $locator),
            new YamlFileLoader($container, $locator),
            new IniFileLoader($container, $locator),
            new PhpFileLoader($container, $locator),
            new DirectoryLoader($container, $locator),
            new ClosureLoader($container),
        ));
        $loader = new DelegatingLoader($resolver);

        call_user_func(\Closure::bind(function () use ($config, $container, $loader) {
            $this->load($config, $container, $loader);
        }, $this->bundle, SimpleBundle::class));
    }

    /**
     * {@inheritdoc}
     */
    public function getConfiguration(array $config, ContainerBuilder $container)
    {
        return $this->bundle;
    }
}
