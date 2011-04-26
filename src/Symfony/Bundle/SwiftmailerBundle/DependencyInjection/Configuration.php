<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\SwiftmailerBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

/**
 * This class contains the configuration information for the bundle
 *
 * This information is solely responsible for how the different configuration
 * sections are normalized, and merged.
 *
 * @author Christophe Coevoet <stof@notk.org>
 */
class Configuration implements ConfigurationInterface
{
    private $debug;

    /**
     * Constructor.
     *
     * @param Boolean $debug The kernel.debug value
     */
    public function __construct($debug)
    {
        $this->debug = (Boolean) $debug;
    }

    /**
     * Generates the configuration tree builder.
     *
     * @return \Symfony\Component\Config\Definition\Builder\TreeBuilder The tree builder
     */
    public function getConfigTreeBuilder()
    {
        $treeBuilder = new TreeBuilder();
        $rootNode = $treeBuilder->root('swiftmailer');

        $rootNode
            ->children()
                ->scalarNode('transport')
                    ->defaultValue('smtp')
                    ->validate()
                        ->ifNotInArray(array('smtp', 'mail', 'sendmail', 'gmail', null))
                        ->thenInvalid('The %s transport is not supported')
                    ->end()
                ->end()
                ->scalarNode('username')->defaultNull()->end()
                ->scalarNode('password')->defaultNull()->end()
                ->scalarNode('host')->defaultValue('localhost')->end()
                ->scalarNode('port')->defaultFalse()->end()
                ->scalarNode('encryption')
                    ->defaultNull()
                    ->validate()
                        ->ifNotInArray(array('tls', 'ssl', null))
                        ->thenInvalid('The %s encryption is not supported')
                    ->end()
                ->end()
                ->scalarNode('auth_mode')
                    ->defaultNull()
                    ->validate()
                        ->ifNotInArray(array('plain', 'login', 'cram-md5', null))
                        ->thenInvalid('The %s authentication mode is not supported')
                    ->end()
                ->end()
                ->arrayNode('spool')
                    ->children()
                        ->scalarNode('type')->defaultValue('file')->end()
                        ->scalarNode('path')->defaultValue('%kernel.cache_dir%/swiftmailer/spool')->end()
                    ->end()
                ->end()
                ->scalarNode('delivery_address')->end()
                ->booleanNode('disable_delivery')->end()
                ->booleanNode('logging')->defaultValue($this->debug)->end()
            ->end()
        ;

        return $treeBuilder;
    }
}
