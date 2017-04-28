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
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\ConfigurationInterface;
use Symfony\Component\Config\Definition\Builder\NodeBuilder;

/**
 * Configuration for the Cache Bundle.
 *
 * @author Lukas Kahwe Smith <smith@pooteeweet.org>
 * @author Victor Berchet <victor@suumit.com>
 */
class Configuration implements ConfigurationInterface
{
    private $debug;
    private $beFactories;
    private $providerFactories;

    public function __construct(array $beFactories = array(), array $providerFactories = array())
    {
        $this->beFactories = $beFactories;
        $this->providerFactories = $providerFactories;
    }

    /**
     * Generates the configuration tree.
     *
     * @return TreeBuilder
     */
    public function getConfigTreeBuilder()
    {
        $treeBuilder = new TreeBuilder();
        $rootNode = $treeBuilder->root('cache', 'array');

        // main config
        $rootNode
            ->children()
                ->scalarNode('debug')->defaultValue('%kernel.debug%')->end()
        ;

        // backends
        $beNode = $rootNode
            ->fixXmlConfig('backend')
            ->children()
                ->arrayNode('backends')
                    ->useAttributeAsKey('type')
                    ->prototype('array')
                        ->validate()
                            ->ifTrue(function($v) { return 1 !== count($v); })
                            ->thenInvalid('You must specify exactly one backend configuration')
                        ->end()
                        ->children()
        ;

        $this->addBackendConfiguration($beNode);

        // providers
//        $rootNode
//            ->useAttributeAsKey('name')
//            ->fixXmlConfig('provider')
//            ->children()
//                ->arrayNode('providers')
//                    ->useAttributeAsKey('name')
//                    ->prototype('array')
//                    ->children()
//                        ->scalarNode('type')->isRequired()->end()
//                        ->scalarNode('backend')->isRequired()->end()
//                        ->scalarNode('namespace')->defaultValue('')->end()
//        ;

        return $treeBuilder;
    }

    private function addBackendConfiguration(NodeBuilder $beNode)
    {
        foreach ($this->beFactories as $factory) {
            $factory->addConfiguration($beNode);
        }
    }

}
