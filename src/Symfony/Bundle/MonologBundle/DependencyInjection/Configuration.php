<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\MonologBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

/**
 * This class contains the configuration information for the bundle
 *
 * This information is solely responsible for how the different configuration
 * sections are normalized, and merged.
 *
 * @author Jordi Boggiano <j.boggiano@seld.be>
 * @author Christophe Coevoet <stof@notk.org>
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
        $rootNode = $treeBuilder->root('monolog');

        $rootNode
            ->fixXmlConfig('handler')
            ->fixXmlConfig('processor')
            ->children()
                ->arrayNode('handlers')
                    ->canBeUnset()
                    ->useAttributeAsKey('name')
                    ->prototype('array')
                        ->canBeUnset()
                        ->children()
                            ->scalarNode('type')
                                ->isRequired()
                                ->treatNullLike('null')
                                ->beforeNormalization()
                                    ->always()
                                    ->then(function($v) { return strtolower($v); })
                                ->end()
                            ->end()
                            ->scalarNode('id')->end()
                            ->scalarNode('priority')->defaultValue(0)->end()
                            ->scalarNode('level')->defaultValue('DEBUG')->end()
                            ->booleanNode('bubble')->defaultFalse()->end()
                            ->scalarNode('path')->end() // stream and rotating
                            ->scalarNode('ident')->end() // syslog
                            ->scalarNode('facility')->end() // syslog
                            ->scalarNode('max_files')->end() // rotating
                            ->scalarNode('action_level')->end() // fingers_crossed
                            ->scalarNode('buffer_size')->end() // fingers_crossed and buffer
                            ->scalarNode('handler')->end() // fingers_crossed and buffer
                            ->scalarNode('formatter')->end()
                        ->end()
                        ->append($this->getProcessorsNode())
                        ->validate()
                            ->ifTrue(function($v) { return ('fingers_crossed' === $v['type'] || 'buffer' === $v['type']) && 1 !== count($v['handler']); })
                            ->thenInvalid('The handler has to be specified to use a FingersCrossedHandler')
                        ->end()
                        ->validate()
                            ->ifTrue(function($v) { return 'service' === $v['type'] && !isset($v['id']); })
                            ->thenInvalid('The id has to be specified to use a service as handler')
                        ->end()
                    ->end()
                    ->validate()
                        ->ifTrue(function($v) { return isset($v['debug']); })
                        ->thenInvalid('The "debug" name cannot be used as it is reserved for the handler of the profiler')
                    ->end()
                ->end()
            ->end()
            ->append($this->getProcessorsNode())
        ;

        return $treeBuilder;
    }

    private function getProcessorsNode()
    {
        $treeBuilder = new TreeBuilder();
        $node = $treeBuilder->root('processors');

        $node
            ->canBeUnset()
            ->performNoDeepMerging()
            ->prototype('scalar')->end()
        ;

        return $node;
    }
}
