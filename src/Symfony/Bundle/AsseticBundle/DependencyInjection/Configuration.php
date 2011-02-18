<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\AsseticBundle\DependencyInjection;

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
    public function getConfigTree($kernelDebug)
    {
        $treeBuilder = new TreeBuilder();
        $rootNode = $treeBuilder->root('assetic', 'array');

        $rootNode
            ->booleanNode('debug')->defaultValue($kernelDebug)->end()
            ->booleanNode('use_controller')->defaultValue($kernelDebug)->end()
            ->scalarNode('document_root')->defaultValue('%kernel.root_dir%/../web')->end()
            ->scalarNode('closure')->end()
            ->scalarNode('yui')->end()
            ->scalarNode('default_javascripts_output')->defaultValue('js/*.js')->end()
            ->scalarNode('default_stylesheets_output')->defaultValue('css/*.css')->end();

        return $treeBuilder->buildTree();
    }
}
