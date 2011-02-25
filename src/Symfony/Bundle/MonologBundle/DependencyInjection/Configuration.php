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

        $handlersPrototype = $rootNode
            ->fixXmlConfig('handler')
            ->arrayNode('handlers')
                ->canBeUnset()
                ->performNoDeepMerging()
                ->useAttributeAsKey('name')
                ->prototype('array')
                    ->scalarNode('action_level')->end() // fingerscrossed specific
                    ->scalarNode('buffer_size')->end() // fingerscrossed specific
                    ->builder($this->getHandlerSubnode())
                    ->validate()
                        ->ifTrue(function($v) { return 'fingerscrossed' === $v['type'] && !isset($v['handler']); })
                        ->thenInvalid('The handler has to be specified to use a FingersCrossedHandler')
                    ->end()
        ;
        $this->addHandlerSection($handlersPrototype);

        return $treeBuilder->buildTree();
    }

    private function addHandlerSection(NodeBuilder $node)
    {
        $node
            ->performNoDeepMerging()
            ->scalarNode('type')
                ->isRequired()
                ->treatNullLike('null')
                ->beforeNormalization()
                    ->always()
                    ->then(function($v) { return strtolower($v); })
                ->end()
            ->end()
            ->scalarNode('level')->defaultValue('DEBUG')->end()
            ->booleanNode('bubble')->defaultFalse()->end()
            ->scalarNode('path')->end() // stream specific
        ;
    }

    private function getHandlerSubnode()
    {
        $node = new NodeBuilder('handler', 'array');
        $this->addHandlerSection($node);

        return $node;
    }
}
