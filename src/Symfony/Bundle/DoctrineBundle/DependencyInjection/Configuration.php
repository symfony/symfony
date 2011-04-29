<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\DoctrineBundle\DependencyInjection;

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
     * Constructor
     *
     * @param Boolean $debug Whether to use the debug mode
     */
    public function  __construct($debug)
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
        $rootNode = $treeBuilder->root('doctrine');

        $this->addDbalSection($rootNode);
        $this->addOrmSection($rootNode);

        return $treeBuilder;
    }

    private function addDbalSection(ArrayNodeDefinition $node)
    {
        $node
            ->children()
            ->arrayNode('dbal')
                ->beforeNormalization()
                    ->ifTrue(function ($v) { return is_array($v) && !array_key_exists('connections', $v) && !array_key_exists('connection', $v); })
                    ->then(function ($v) {
                        $connection = array();
                        foreach (array(
                            'dbname',
                            'host',
                            'port',
                            'user',
                            'password',
                            'driver',
                            'driver_class',
                            'options',
                            'path',
                            'memory',
                            'unix_socket',
                            'wrapper_class', 'wrapper-class', 'wrapperClass',
                            'platform_service', 'platform-service', 'platform-service',
                            'charset',
                            'logging'
                        ) as $key) {
                            if (array_key_exists($key, $v)) {
                                $connection[$key] = $v[$key];
                                unset($v[$key]);
                            }
                        }
                        $v['default_connection'] = isset($v['default_connection']) ? (string) $v['default_connection'] : 'default';
                        $v['connections'] = array($v['default_connection'] => $connection);

                        return $v;
                    })
                ->end()
                ->children()
                    ->scalarNode('default_connection')->end()
                ->end()
                ->fixXmlConfig('type')
                ->children()
                    ->arrayNode('types')
                        ->useAttributeAsKey('name')
                        ->prototype('scalar')->end()
                    ->end()
                ->end()
                ->fixXmlConfig('connection')
                ->append($this->getDbalConnectionsNode())
            ->end()
        ;
    }

    private function getDbalConnectionsNode()
    {
        $treeBuilder = new TreeBuilder();
        $node = $treeBuilder->root('connections');

        $node
            ->requiresAtLeastOneElement()
            ->useAttributeAsKey('name')
            ->prototype('array')
                ->children()
                    ->scalarNode('dbname')->end()
                    ->scalarNode('host')->defaultValue('localhost')->end()
                    ->scalarNode('port')->defaultNull()->end()
                    ->scalarNode('user')->defaultValue('root')->end()
                    ->scalarNode('password')->defaultNull()->end()
                    ->scalarNode('driver')->defaultValue('pdo_mysql')->end()
                    ->scalarNode('path')->end()
                    ->booleanNode('memory')->end()
                    ->scalarNode('unix_socket')->end()
                    ->scalarNode('platform_service')->end()
                    ->scalarNode('charset')->end()
                    ->booleanNode('logging')->defaultValue($this->debug)->end()
                ->end()
                ->fixXmlConfig('driver_class', 'driverClass')
                ->children()
                    ->scalarNode('driverClass')->end()
                ->end()
                ->fixXmlConfig('options', 'driverOptions')
                ->children()
                    ->arrayNode('driverOptions')
                        ->useAttributeAsKey('key')
                        ->prototype('scalar')->end()
                    ->end()
                ->end()
                ->fixXmlConfig('wrapper_class', 'wrapperClass')
                ->children()
                    ->scalarNode('wrapperClass')->end()
                ->end()
            ->end()
        ;

        return $node;
    }

    private function addOrmSection(ArrayNodeDefinition $node)
    {
        $node
            ->children()
                ->arrayNode('orm')
                    ->beforeNormalization()
                        ->ifTrue(function ($v) { return null === $v || (is_array($v) && !array_key_exists('entity_managers', $v) && !array_key_exists('entity_manager', $v)); })
                        ->then(function ($v) {
                            $v = (array) $v;
                            $entityManager = array();
                            foreach (array(
                                'result_cache_driver', 'result-cache-driver',
                                'metadata_cache_driver', 'metadata-cache-driver',
                                'query_cache_driver', 'query-cache-driver',
                                'auto_mapping', 'auto-mapping',
                                'mappings', 'mapping',
                                'connection'
                            ) as $key) {
                                if (array_key_exists($key, $v)) {
                                    $entityManager[$key] = $v[$key];
                                    unset($v[$key]);
                                }
                            }
                            $v['default_entity_manager'] = isset($v['default_entity_manager']) ? (string) $v['default_entity_manager'] : 'default';
                            $v['entity_managers'] = array($v['default_entity_manager'] => $entityManager);

                            return $v;
                        })
                    ->end()
                    ->children()
                        ->scalarNode('default_entity_manager')->end()
                        ->booleanNode('auto_generate_proxy_classes')->defaultFalse()->end()
                        ->scalarNode('proxy_dir')->defaultValue('%kernel.cache_dir%/doctrine/orm/Proxies')->end()
                        ->scalarNode('proxy_namespace')->defaultValue('Proxies')->end()
                    ->end()
                    ->fixXmlConfig('entity_manager')
                    ->append($this->getOrmEntityManagersNode())
                ->end()
            ->end()
        ;
    }

    private function getOrmEntityManagersNode()
    {
        $treeBuilder = new TreeBuilder();
        $node = $treeBuilder->root('entity_managers');

        $node
            ->requiresAtLeastOneElement()
            ->useAttributeAsKey('name')
            ->prototype('array')
                ->addDefaultsIfNotSet()
                ->append($this->getOrmCacheDriverNode('query_cache_driver'))
                ->append($this->getOrmCacheDriverNode('metadata_cache_driver'))
                ->append($this->getOrmCacheDriverNode('result_cache_driver'))
                ->children()
                    ->scalarNode('connection')->end()
                    ->scalarNode('class_metadata_factory_name')->defaultValue('%doctrine.orm.class_metadata_factory_name%')->end()
                    ->scalarNode('auto_mapping')->defaultFalse()->end()
                ->end()
                ->fixXmlConfig('hydrator')
                ->children()
                    ->arrayNode('hydrators')
                        ->useAttributeAsKey('name')
                        ->prototype('scalar')->end()
                    ->end()
                ->end()
                ->fixXmlConfig('mapping')
                ->children()
                    ->arrayNode('mappings')
                        ->useAttributeAsKey('name')
                        ->prototype('array')
                            ->beforeNormalization()
                                ->ifString()
                                ->then(function($v) { return array ('type' => $v); })
                            ->end()
                            ->treatNullLike(array ())
                            ->performNoDeepMerging()
                            ->children()
                                ->scalarNode('type')->end()
                                ->scalarNode('dir')->end()
                                ->scalarNode('alias')->end()
                                ->scalarNode('prefix')->end()
                                ->booleanNode('is_bundle')->end()
                            ->end()
                        ->end()
                    ->end()
                    ->arrayNode('dql')
                        ->fixXmlConfig('string_function')
                        ->fixXmlConfig('numeric_function')
                        ->fixXmlConfig('datetime_function')
                        ->children()
                            ->arrayNode('string_functions')
                                ->useAttributeAsKey('name')
                                ->prototype('scalar')->end()
                            ->end()
                            ->arrayNode('numeric_functions')
                                ->useAttributeAsKey('name')
                                ->prototype('scalar')->end()
                            ->end()
                            ->arrayNode('datetime_functions')
                                ->useAttributeAsKey('name')
                                ->prototype('scalar')->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()
            ->end()
        ;

        return $node;
    }

    private function getOrmCacheDriverNode($name)
    {
        $treeBuilder = new TreeBuilder();
        $node = $treeBuilder->root($name);

        $node
            ->addDefaultsIfNotSet()
            ->beforeNormalization()
                ->ifString()
                ->then(function($v) { return array ('type' => $v); })
            ->end()
            ->children()
                ->scalarNode('type')->defaultValue('array')->isRequired()->end()
                ->scalarNode('host')->end()
                ->scalarNode('port')->end()
                ->scalarNode('instance_class')->end()
                ->scalarNode('class')->end()
            ->end()
        ;

        return $node;
    }
}
