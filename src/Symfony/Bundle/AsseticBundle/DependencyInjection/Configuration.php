<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\AsseticBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;

/**
 * This class contains the configuration information for the bundle
 *
 * This information is solely responsible for how the different configuration
 * sections are normalized, and merged.
 *
 * @author Christophe Coevoet <stof@notk.org>
 * @author Kris Wallsmith <kris@symfony.com>
 */
class Configuration
{
    /**
     * Generates the configuration tree.
     *
     * @param Boolean $debug    Wether to use the debug mode
     * @param array   $bundles  An array of bundle names
     * 
     * @return \Symfony\Component\Config\Definition\ArrayNode The config tree
     */
    public function getConfigTree($debug, array $bundles)
    {
        $tree = new TreeBuilder();

        $tree->root('assetic')
            ->children()
                ->booleanNode('debug')->defaultValue($debug)->end()
                ->booleanNode('use_controller')->defaultValue($debug)->end()
                ->scalarNode('read_from')->defaultValue('%kernel.root_dir%/../web')->end()
                ->scalarNode('write_to')->defaultValue('%assetic.read_from%')->end()
                ->scalarNode('closure')->defaultNull()->end()
                ->scalarNode('java')->defaultNull()->end()
                ->scalarNode('node')->defaultNull()->end()
                ->scalarNode('sass')->defaultNull()->end()
                ->scalarNode('yui')->defaultNull()->end()
            ->end()
            ->fixXmlConfig('bundle')
            ->children()
                ->arrayNode('bundles')
                    ->defaultValue($bundles)
                    ->requiresAtLeastOneElement()
                    ->beforeNormalization()
                        ->ifTrue(function($v) { return !is_array($v); })
                        ->then(function($v) { return array($v); })
                    ->end()
                    ->prototype('scalar')
                        ->beforeNormalization()
                            ->ifTrue(function($v) { return is_array($v) && isset($v['name']); })
                            ->then(function($v) { return $v['name']; })
                        ->end()
                    ->end()
                ->end()
            ->end()
            ->fixXmlConfig('filter')
            ->children()
                ->arrayNode('filters')
                    ->addDefaultsIfNotSet()
                    ->requiresAtLeastOneElement()
                    ->useAttributeAsKey('name')
                    ->prototype('array')->end()
                ->end()
            ->end()
        ;

        return $tree->buildTree();
    }
}
