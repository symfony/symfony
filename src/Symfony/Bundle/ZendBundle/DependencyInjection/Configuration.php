<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\ZendBundle\DependencyInjection;

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
        $rootNode = $treeBuilder->root('zend', 'array');

        $rootNode
            ->arrayNode('logger')
                ->canBeUnset()
                ->scalarNode('priority')->defaultValue('INFO')->end()
                ->scalarNode('path')->defaultValue('%kernel.logs_dir%/%kernel.environment%.log')->end()
                ->booleanNode('log_errors')->defaultFalse()->end()
            ->end()
        ;

        return $treeBuilder->buildTree();
    }
}
