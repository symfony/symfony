<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\CacheBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

/**
 * Cache extension configuration structure.
 *
 * @author Florin Patan <florinpatan@gmail.com>
 */
class Configuration implements ConfigurationInterface
{

    /**
     * Generates the configuration tree builder.
     *
     * @return \Symfony\Component\Config\Definition\Builder\TreeBuilder The tree builder
     */
    public function getConfigTreeBuilder()
    {
        $treeBuilder = new TreeBuilder();
        $rootNode = $treeBuilder->root('cache');

        $rootNode
            ->children()
                ->arrayNode('driver')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('class')
                            ->info('This holds our cache driver class name')
                            ->defaultValue('Symfony\\Component\\Cache\\CacheDriver')
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('drivers')
                    ->cannotBeEmpty()
                    ->isRequired()
                    ->info('This holds cache drivers definitions')
                    ->append($this->addApcNode())
                    ->append($this->addMemcachedNode())
                ->end()
                ->append($this->addCacheInstances())
            ->end()
        ;

        return $treeBuilder;
    }

    /**
     * Add APC driver definition section
     *
     * @return \Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition|\Symfony\Component\Config\Definition\Builder\NodeDefinition
     */
    private function addApcNode()
    {
        $node = new TreeBuilder();
        $node = $node->root('apc');

        $node
            ->children()
                ->booleanNode('enabled')
                    ->defaultTrue()
                    ->info('Enable APC driver')
                ->end()
                ->scalarNode('class')
                    ->defaultValue('Symfony\\Component\\Cache\\Driver\\Apc')
                    ->info('The APC cache driver class')
                ->end()
                ->arrayNode('config')
                    ->info('Configuration for APC cache driver, applies to all instances unless specified in the instance')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('ttl')
                            ->info('Default TTL for APC entries')
                            ->defaultValue(600)
                        ->end()
                    ->end()
                ->end()
        ->end();

        return $node;
    }

    /**
     * Add Memcached driver definition section
     *
     * @return \Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition|\Symfony\Component\Config\Definition\Builder\NodeDefinition
     */
    private function addMemcachedNode()
    {
        $node = new TreeBuilder();
        $node = $node->root('memcached');

        $node
            ->fixXmlConfig('server', 'servers')
            ->children()
                ->booleanNode('enabled')
                    ->defaultTrue()
                    ->info('Enable Memcached driver')
                ->end()
                ->scalarNode('class')
                    ->defaultValue('Symfony\\Component\\Cache\\Driver\\Memcached')
                    ->info('The Memcached cache driver class')
                ->end()
                ->scalarNode('instance')
                    ->defaultValue('Memcached')
                    ->info('The Memcached class to be instantiated if there is no service specified')
                ->end()
                ->scalarNode('service')
                    ->defaultValue(null)
                    ->info('If you specify a service name here, it will be used as a connection to memcached and it must be an instance of \Memcached')
                ->end()
                ->arrayNode('servers')
                    ->info('The memcache driver requires at least one server to be configured if there is no service specified')
                    ->example(array('host' => '127.0.0.1', 'port' => 11211, 'weight' => 100))
                    ->requiresAtLeastOneElement()
                    ->prototype('array')
                        ->children()
                            ->scalarNode('host')
                                ->isRequired()
                            ->end()
                            ->scalarNode('port')
                                ->defaultValue(11211)
                            ->end()
                            ->scalarNode('weight')
                                ->defaultValue(100)
                            ->end()
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('config')
                    ->info('Configuration for Memcached cache driver, applies to all instances unless specified in the instance')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('ttl')
                            ->info('Default TTL for Memcached entries')
                            ->defaultValue(600)
                        ->end()
                    ->end()
                ->end()
        ->end();

        return $node;
    }

    /**
     * Add driver instances
     *
     * @return \Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition|\Symfony\Component\Config\Definition\Builder\NodeDefinition
     */
    private function addCacheInstances()
    {
        $node = new TreeBuilder();
        $node = $node->root('instances');

        $node
            ->isRequired()
            ->requiresAtLeastOneElement()
            ->prototype('array')
                ->fixXmlConfig('server', 'servers')
                ->children()
                    ->scalarNode('type')
                        ->info('Type of instance')
                        ->example('type: apc')
                    ->end()
                    ->booleanNode('enabled')
                        ->info('Is this driver instance enabled or not')
                        ->defaultTrue()
                    ->end()
                    ->scalarNode('service')
                        ->info('If specified, this service will be injected to the cache driver that supports it at construction time, else the default instance will be used, see memcached for example')
                        ->example('service: doctrine.common.cache.instance')
                    ->end()
                    ->arrayNode('config')
                        ->info('Configuration information particular to this driver instance')
                        ->beforeNormalization()
                            ->always()
                            ->then(function ($options) { return $options; })
                        ->end()
                        ->prototype('variable')->end()
                    ->end()
                    ->arrayNode('servers')
                        ->info('Server configuration particular to this driver instance')
                        ->beforeNormalization()
                            ->always()
                            ->then(function ($options) { return $options; })
                        ->end()
                        ->prototype('variable')->end()
                    ->end()
                ->end()
            ->end()
        ->end();

        return $node;
    }

}
