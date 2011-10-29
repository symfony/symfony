<?php

/*
 * This file is part of the Symfony framework.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Symfony\Bundle\SecurityBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

/**
 * This class contains the configuration information for the following tags:
 *
 *   * security.config
 *   * security.acl
 *
 * This information is solely responsible for how the different configuration
 * sections are normalized, and merged.
 *
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 */
class FactoryConfiguration implements ConfigurationInterface
{
    /**
     * Generates the configuration tree builder.
     *
     * @return \Symfony\Component\Config\Definition\Builder\TreeBuilder The tree builder
     */
    public function getConfigTreeBuilder()
    {
        $tb = new TreeBuilder();

        $tb
            ->root('security')
                ->ignoreExtraKeys()
                ->fixXmlConfig('factory', 'factories')
                ->children()
                    ->arrayNode('factories')
                        ->prototype('scalar')->end()
                    ->end()
                ->end()
            ->end()
        ;

        return $tb;
    }
}
