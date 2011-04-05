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
                    ->performNoDeepMerging()
                    ->useAttributeAsKey('name')
                    ->prototype('array')
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
                            ->scalarNode('level')->defaultValue('DEBUG')->end()
                            ->booleanNode('bubble')->defaultFalse()->end()
                            ->scalarNode('path')->end() // stream specific
                            ->scalarNode('ident')->end() // syslog specific
                            ->scalarNode('facility')->end() // syslog specific
                            ->scalarNode('action_level')->end() // fingerscrossed specific
                            ->scalarNode('buffer_size')->end() // fingerscrossed specific
                            ->scalarNode('handler')->end() // fingerscrossed specific
                            ->scalarNode('formatter')->end()
                        ->end()
                        ->append($this->getProcessorsNode())
                        ->validate()
                            ->ifTrue(function($v) { return 'fingerscrossed' === $v['type'] && !isset($v['handler']); })
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
            ->prototype('scalar')
                ->beforeNormalization()
                    ->ifTrue(function($v) { return is_array($v) && isset($v['callback']); })
                    ->then(function($v){ return $v['callback']; })
                ->end()
            ->end()
        ;

        return $node;
    }
}
