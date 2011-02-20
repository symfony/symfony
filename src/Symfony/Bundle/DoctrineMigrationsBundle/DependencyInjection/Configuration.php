<?php

namespace Symfony\Bundle\DoctrineMigrationsBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\NodeBuilder;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;

/**
 * DoctrineMigrationsExtension configuration structure.
 *
 * @author Lukas Kahwe Smith <smith@pooteeweet.org>
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
        $rootNode = $treeBuilder->root('doctrine_migrations', 'array');

        $rootNode
            ->scalarNode('dir_name')->defaultValue('%kernel.root_dir%/DoctrineMigrations')->cannotBeEmpty()->end()
            ->scalarNode('namespace')->defaultValue('Application\Migrations')->cannotBeEmpty()->end()
            ->scalarNode('table_name')->defaultValue('migration_versions')->cannotBeEmpty()->end()
            ->scalarNode('name')->defaultValue('Application Migrations')->end();

        return $treeBuilder->buildTree();
    }
}
