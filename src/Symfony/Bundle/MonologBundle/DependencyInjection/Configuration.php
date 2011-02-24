<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\MonologBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\NodeBuilder;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;

/**
 * This class contains the configuration information for the bundle
 *
 * This information is solely responsible for how the different configuration
 * sections are normalized, and merged.
 *
 * @author Jordi Boggiano <j.boggiano@seld.be>
 */
class Configuration
{
    /**
     * Generates the configuration tree.
     *
     * @return \Symfony\Component\Config\Definition\NodeInterface
     */
    public function getConfigTree()
    {
        $treeBuilder = new TreeBuilder();
        $rootNode = $treeBuilder->root('monolog', 'array');

        // TODO update XSD to match this
        $rootNode
            ->arrayNode('handlers')
                ->fixXmlConfig('handler')
                ->canBeUnset()
                ->performNoDeepMerging()
                ->prototype('array')
                    ->performNoDeepMerging()
                    // TODO lowercase the type always
                    ->scalarNode('type')->isRequired()->end()
                    ->scalarNode('action_level')->end()
                    ->scalarNode('level')->defaultValue('INFO')->end()
                    ->scalarNode('path')->end()
                    ->scalarNode('bubble')->end()
                    ->scalarNode('buffer_size')->end()
                    ->arrayNode('handler')
                        ->performNoDeepMerging()
                        ->scalarNode('type')->isRequired()->end()
                        ->scalarNode('level')->defaultValue('DEBUG')->end()
                        ->scalarNode('path')->end()
                    ->end()
                ->end()
            ->end()
        ;

        return $treeBuilder->buildTree();
    }
}
