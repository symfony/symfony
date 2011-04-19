<?php

namespace Symfony\Bundle\DoctrineMongoDBBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

/**
 * FrameworkExtension configuration structure.
 *
 * @author Ryan Weaver <ryan@thatsquality.com>
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
        $rootNode = $treeBuilder->root('doctrine_mongo_db');

        $this->addDocumentManagersSection($rootNode);
        $this->addConnectionsSection($rootNode);

        $rootNode
            ->children()
                ->scalarNode('proxy_namespace')->defaultValue('Proxies')->end()
                ->scalarNode('proxy_dir')->defaultValue('%kernel.cache_dir%/doctrine/odm/mongodb/Proxies')->end()
                ->scalarNode('auto_generate_proxy_classes')->defaultValue(false)->end()
                ->scalarNode('hydrator_namespace')->defaultValue('Hydrators')->end()
                ->scalarNode('hydrator_dir')->defaultValue('%kernel.cache_dir%/doctrine/odm/mongodb/Hydrators')->end()
                ->scalarNode('auto_generate_hydrator_classes')->defaultValue(false)->end()
                ->scalarNode('default_document_manager')->end()
                ->scalarNode('default_connection')->end()
                ->scalarNode('default_database')->defaultValue('default')->end()
            ->end()
        ;

        return $treeBuilder;
    }

    /**
     * Configures the "document_managers" section
     */
    private function addDocumentManagersSection(ArrayNodeDefinition $rootNode)
    {
        $rootNode
            ->fixXmlConfig('document_manager')
            ->children()
                ->arrayNode('document_managers')
                    ->useAttributeAsKey('id')
                    ->prototype('array')
                        //->performNoDeepMerging()
                        ->treatNullLike(array())
                        ->append($this->getMetadataCacheDriverNode())
                        ->children()
                            ->scalarNode('connection')->end()
                            ->scalarNode('database')->end()
                            ->booleanNode('logging')->defaultValue($this->debug)->end()
                        ->end()
                        ->fixXmlConfig('mapping')
                        ->append($this->getMappingsNode())
                    ->end()
                ->end()
            ->end()
        ;
    }

    /**
     * Adds the configuration for the "connections" key
     */
    private function addConnectionsSection(ArrayNodeDefinition $rootNode)
    {
        $rootNode
            ->fixXmlConfig('connection')
            ->children()
                ->arrayNode('connections')
                    ->useAttributeAsKey('id')
                    ->prototype('array')
                        ->performNoDeepMerging()
                        ->children()
                            ->scalarNode('server')->defaultNull()->end()
                        ->end()
                        ->append($this->addConnectionOptionsNode())
                    ->end()
                ->end()
            ->end()
        ;
    }

    /**
     * Returns the array node used for "mappings".
     *
     * This is used in two different parts of the tree.
     *
     * @param NodeBuilder $rootNode The parent node
     * @return NodeBuilder
     */
    protected function getMappingsNode()
    {
        $builder = new TreeBuilder();
        $node = $builder->root('mappings');

        $node
            ->useAttributeAsKey('name')
            ->prototype('array')
                ->beforeNormalization()
                    // if it's not an array, then the scalar is the type key
                    ->ifString()
                    ->then(function($v) { return array ('type' => $v); })
                ->end()
                // I believe that "null" should *not* set the type
                // it's guessed in AbstractDoctrineExtension::detectMetadataDriver
                ->treatNullLike(array())
                ->children()
                    ->scalarNode('type')->end()
                    ->scalarNode('dir')->end()
                    ->scalarNode('prefix')->end()
                    ->scalarNode('alias')->end()
                    ->booleanNode('is_bundle')->end()
                ->end()
                ->performNoDeepMerging()
            ->end()
        ;

        return $node;
    }

    /**
     * Adds the NodeBuilder for the "options" key of a connection.
     */
    private function addConnectionOptionsNode()
    {
        $builder = new TreeBuilder();
        $node = $builder->root('options');

        $node
            ->performNoDeepMerging()
            ->addDefaultsIfNotSet() // adds an empty array of omitted
            // options go into the Mongo constructor
            // http://www.php.net/manual/en/mongo.construct.php
            ->children()
                ->booleanNode('connect')->end()
                ->scalarNode('persist')->end()
                ->scalarNode('timeout')->end()
                ->booleanNode('replicaSet')->end()
                ->scalarNode('username')->end()
                ->scalarNode('password')->end()
            ->end()
        ->end();

        return $node;
    }

    private function getMetadataCacheDriverNode()
    {
        $builder = new TreeBuilder();
        $node = $builder->root('metadata_cache_driver');

        $node
            ->beforeNormalization()
                // if scalar
                ->ifTrue(function($v) { return !is_array($v); })
                ->then(function($v) { return array('type' => $v); })
            ->end()
            ->children()
                ->scalarNode('type')->end()
                ->scalarNode('class')->end()
                ->scalarNode('host')->end()
                ->scalarNode('port')->end()
                ->scalarNode('instance_class')->end()
            ->end()
        ->end();

        return $node;
    }
}
