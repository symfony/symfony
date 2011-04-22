<?php

namespace Symfony\Bundle\DoctrineMigrationsBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

/**
 * DoctrineMigrationsExtension configuration structure.
 *
 * @author Lukas Kahwe Smith <smith@pooteeweet.org>
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
        $rootNode = $treeBuilder->root('doctrine_migrations', 'array');

        $rootNode
            ->children()
                ->scalarNode('dir_name')->defaultValue('%kernel.root_dir%/DoctrineMigrations')->cannotBeEmpty()->end()
                ->scalarNode('namespace')->defaultValue('Application\Migrations')->cannotBeEmpty()->end()
                ->scalarNode('table_name')->defaultValue('migration_versions')->cannotBeEmpty()->end()
                ->scalarNode('name')->defaultValue('Application Migrations')->end()
            ->end()
        ;

        return $treeBuilder;
    }
}
