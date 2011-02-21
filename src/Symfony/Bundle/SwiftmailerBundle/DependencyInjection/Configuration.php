<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\SwiftmailerBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\NodeBuilder;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;

/**
 * This class contains the configuration information for the bundle
 *
 * This information is solely responsible for how the different configuration
 * sections are normalized, and merged.
 *
 * @author Christophe Coevoet <stof@notk.org>
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
        $rootNode = $treeBuilder->root('swiftmailer', 'array');

        $rootNode
            ->scalarNode('transport')
                ->defaultValue('smtp')
                ->validate()
                    ->ifNotInArray(array ('smtp', 'mail', 'sendmail', 'gmail'))
                    ->thenInvalid('The %s transport is not supported')
                ->end()
            ->end()
            ->scalarNode('username')->defaultNull()->end()
            ->scalarNode('password')->defaultNull()->end()
            ->scalarNode('host')->defaultValue('localhost')->end()
            ->scalarNode('port')->defaultValue(false)->end()
            ->scalarNode('encryption')
                ->defaultNull()
                ->validate()
                    ->ifNotInArray(array ('tls', 'ssl', null))
                    ->thenInvalid('The %s encryption is not supported')
                ->end()
            ->end()
            ->scalarNode('auth_mode')
                ->defaultNull()
                ->validate()
                    ->ifNotInArray(array ('plain', 'login', 'cram-md5', null))
                    ->thenInvalid('The %s authentication mode is not supported')
                ->end()
            ->end()
            ->arrayNode('spool')
                ->scalarNode('type')->defaultValue('file')->end()
                ->scalarNode('path')->defaultValue('%kernel.cache_dir%/swiftmailer/spool')->end()
            ->end()
            ->scalarNode('delivery_adress')->end()
            ->booleanNode('disable_delivery')->end();

        return $treeBuilder->buildTree();
    }
}
