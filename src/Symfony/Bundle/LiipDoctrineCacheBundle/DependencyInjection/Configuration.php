<?php

namespace Liip\DoctrineCacheBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder,
    Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition,
    Symfony\Component\Config\Definition\ConfigurationInterface;

/**
 * This class contains the configuration information for the bundle
 *
 * This information is solely responsible for how the different configuration
 * sections are normalized, and merged.
 *
 * @author Lukas Kahwe Smith <smith@pooteeweet.org>
 */
class Configuration implements ConfigurationInterface
{
    /**
     * Generates the configuration tree.
     *
     * @return TreeBuilder
     */
    public function getConfigTreeBuilder()
    {
        $treeBuilder = new TreeBuilder();
        $rootNode = $treeBuilder->root('liip_doctrine_cache', 'array');

        $rootNode
            ->fixXmlConfig('namespace', 'namespaces')
            ->children()
                ->arrayNode('namespaces')
                ->useAttributeAsKey('name')
                    ->prototype('array')
                        ->children()
                            ->scalarNode('namespace')->defaultNull()->end()
                            ->scalarNode('type')->isRequired()->cannotBeEmpty()->end()
                            ->scalarNode('id')->defaultNull()->end()
                        ->end()
                    ->end()
                ->end()
            ->end();
        ;

        return $treeBuilder;
    }
}
