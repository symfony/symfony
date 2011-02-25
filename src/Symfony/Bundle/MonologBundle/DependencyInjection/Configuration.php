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

        $rootNode
            ->fixXmlConfig('handler')
            ->arrayNode('handlers')
                ->canBeUnset()
                ->performNoDeepMerging()
                ->useAttributeAsKey('name')
                ->prototype('array')
                    ->performNoDeepMerging()
                    ->scalarNode('type')
                        ->isRequired()
                        ->beforeNormalization()
                            ->always()
                            ->then(function($v) { return strtolower($v); })
                        ->end()
                    ->end()
                    ->scalarNode('level')->defaultValue('INFO')->end()
                    ->booleanNode('bubble')->defaultFalse()->end()
                    ->scalarNode('path')->end() // stream specific
                    ->scalarNode('action_level')->end() // fingerscrossed specific
                    ->scalarNode('buffer_size')->end() // fingerscrossed specific
                    ->arrayNode('handler')
                        ->performNoDeepMerging()
                        ->scalarNode('type')
                            ->isRequired()
                            ->beforeNormalization()
                                ->always()
                                ->then(function($v) { return strtolower($v); })
                            ->end()
                        ->end()
                        ->scalarNode('level')->defaultValue('DEBUG')->end()
                        ->booleanNode('bubble')->defaultFalse()->end()
                        ->scalarNode('path')->end() // stream specific
                    ->end()
                    ->validate()
                        ->ifTrue(function($v) { return 'fingerscrossed' === $v['type'] && !isset($v['handler']); })
                        ->thenInvalid('The handler has to be specified to use a FingersCrossedHandler')
                    ->end()
                ->end()
            ->end()
        ;

        return $treeBuilder->buildTree();
    }
}
