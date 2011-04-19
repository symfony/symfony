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
use Symfony\Component\Config\Definition\ConfigurationInterface;

/**
 * This class contains the configuration information for the bundle
 *
 * This information is solely responsible for how the different configuration
 * sections are normalized, and merged.
 *
 * @author Christophe Coevoet <stof@notk.org>
 * @author Kris Wallsmith <kris@symfony.com>
 */
class Configuration implements ConfigurationInterface
{
    private $bundles;
    private $debug;

    /**
     * Constructor
     *
     * @param Boolean $debug    Wether to use the debug mode
     * @param array   $bundles  An array of bundle names
     */
    public function __construct($debug, array $bundles)
    {
        $this->debug = (Boolean) $debug;
        $this->bundles = $bundles;
    }

    /**
     * Generates the configuration tree builder.
     *
     * @return \Symfony\Component\Config\Definition\Builder\TreeBuilder The tree builder
     */
    public function getConfigTreeBuilder()
    {
        $builder = new TreeBuilder();

        $builder->root('assetic')
            ->children()
                ->booleanNode('debug')->defaultValue($this->debug)->end()
                ->booleanNode('use_controller')->defaultValue($this->debug)->end()
                ->scalarNode('read_from')->defaultValue('%kernel.root_dir%/../web')->end()
                ->scalarNode('write_to')->defaultValue('%assetic.read_from%')->end()
                ->scalarNode('java')->defaultValue('/usr/bin/java')->end()
                ->scalarNode('node')->defaultValue('/usr/bin/node')->end()
                ->scalarNode('sass')->defaultValue('/usr/bin/sass')->end()
            ->end()

            // bundles
            ->fixXmlConfig('bundle')
            ->children()
                ->arrayNode('bundles')
                    ->defaultValue($this->bundles)
                    ->requiresAtLeastOneElement()
                    ->prototype('scalar')
                        ->validate()
                            ->ifNotInArray($this->bundles)
                            ->thenInvalid('%s is not a valid bundle.')
                        ->end()
                    ->end()
                ->end()
            ->end()

            // filters
            ->fixXmlConfig('filter')
            ->children()
                ->arrayNode('filters')
                    ->addDefaultsIfNotSet()
                    ->requiresAtLeastOneElement()
                    ->useAttributeAsKey('name')
                    ->prototype('variable')
                        ->treatNullLike(array())
                        ->validate()
                            ->ifTrue(function($v) { return !is_array($v); })
                            ->thenInvalid('The assetic.filters config %s must be either null or an array.')
                        ->end()
                    ->end()
                ->end()
            ->end()
        ;

        return $builder;
    }
}
