<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\DoctrineBundle\DependencyInjection;

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
    private $kernelDebug;

    /**
     * Generates the configuration tree.
     *
     * @param Boolean $kernelDebug
     * @return \Symfony\Component\Config\Definition\NodeInterface
     */
    public function getConfigTree($kernelDebug)
    {
        $this->kernelDebug = (bool) $kernelDebug;

        $treeBuilder = new TreeBuilder();
        $rootNode = $treeBuilder->root('doctrine', 'array');

        $this->addDbalSection($rootNode);
        $this->addOrmSection($rootNode);

        return $treeBuilder->buildTree();
    }

    private function addDbalSection(NodeBuilder $node)
    {
        $node
            ->arrayNode('dbal')
                ->beforeNormalization()
                    ->ifNull()
                    ->then(function($v) { return array (); }) // Let use the default values with the subsequent closure.
                ->end()
                ->beforeNormalization()
                    ->ifTrue(function($v){ return is_array($v) && !array_key_exists('connections', $v) && !array_key_exists('connection', $v); })
                    ->then(function($v) {
                        $connection = array ();
                        $keys = array ('dbname', 'host', 'port', 'user', 'password', 'driver', 'driver_class', 'options', 'path', 'memory', 'unix_socket', 'wrapper_class', 'platform_service', 'charset', 'logging');
                        foreach ($keys as $key) {
                            if (array_key_exists($key, $v)) {
                                $connection[$key] = $v[$key];
                                unset($v[$key]);
                            }
                        }
                        $defaultConnection = isset($v['default_connection']) ? (string) $v['default_connection'] : 'default';
                        $v['connections'] = array ($defaultConnection => $connection);
                        $v['default_connection'] = $defaultConnection;
                        return $v;
                    })
                ->end()
                ->scalarNode('default_connection')->isRequired()->cannotBeEmpty()->end()
                ->fixXmlConfig('type')
                ->arrayNode('types')
                    ->useAttributeAsKey('name')
                    ->prototype('scalar')
                        ->beforeNormalization()
                            ->ifTrue(function($v) { return is_array($v) && isset($v['class']); })
                            ->then(function($v) { return $v['class']; })
                        ->end()
                    ->end()
                ->end()
                ->fixXmlConfig('connection')
                ->builder($this->getDbalConnectionsNode())
            ->end()
        ;
    }

    private function getDbalConnectionsNode()
    {
        $node = new NodeBuilder('connections', 'array');
        $node
            ->requiresAtLeastOneElement()
            ->useAttributeAsKey('name')
            ->prototype('array')
                ->scalarNode('dbname')->end()
                ->scalarNode('host')->defaultValue('localhost')->end()
                ->scalarNode('port')->defaultNull()->end()
                ->scalarNode('user')->defaultValue('root')->end()
                ->scalarNode('password')->defaultNull()->end()
                ->scalarNode('driver')->defaultValue('pdo_mysql')->end()
                ->fixXmlConfig('driver_class', 'driverClass')
                ->scalarNode('driver_class')->end()
                ->fixXmlConfig('options', 'driverOptions')
                ->arrayNode('driverOptions')
                    ->useAttributeAsKey('key')
                    ->prototype('scalar')->end()
                ->end()
                ->scalarNode('path')->end()
                ->booleanNode('memory')->end()
                ->scalarNode('unix_socket')->end()
                ->fixXmlConfig('wrapper_class', 'wrapperClass')
                ->scalarNode('wrapper_class')->end()
                ->scalarNode('platform_service')->end()
                ->scalarNode('charset')->defaultValue('UTF-8')->end()
                ->booleanNode('logging')->defaultValue($this->kernelDebug)->end()
            ->end()
        ;

        return $node;
    }

    private function addOrmSection(NodeBuilder $node)
    {
        $node
            ->arrayNode('orm')
                ->beforeNormalization()
                    ->ifTrue(function($v){ return is_array($v) && !array_key_exists('entity_managers', $v) && !array_key_exists('entity_manager', $v); })
                    ->then(function($v) {
                        $entityManager = array ();
                        $keys = array ('result_cache_driver', 'result-cache-driver', 'metadata_cache_driver', 'metadata-cache-driver', 'query_cache_driver', 'query-cache-driver', 'mappings', 'mapping', 'connection');
                        foreach ($keys as $key) {
                            if (array_key_exists($key, $v)) {
                                $entityManager[$key] = $v[$key];
                                unset($v[$key]);
                            }
                        }
                        $defaultEntityManager = isset($v['default_entity_manager']) ? (string) $v['default_entity_manager'] : 'default';
                        $v['entity_managers'] = array ($defaultEntityManager => $entityManager);
                        $v['default_entity_manager'] = $defaultEntityManager;
                        return $v;
                    })
                ->end()
                ->scalarNode('default_entity_manager')->isRequired()->cannotBeEmpty()->end()
                ->booleanNode('auto_generate_proxy_classes')->defaultFalse()->end()
                ->scalarNode('proxy_dir')->defaultValue('%kernel.cache_dir%/doctrine/orm/Proxies')->end()
                ->scalarNode('proxy_namespace')->defaultValue('Proxies')->end()
                ->fixXmlConfig('entity_manager')
                ->builder($this->getOrmEntityManagersNode())
            ->end()
        ;
    }

    private function getOrmEntityManagersNode()
    {
        $node = new NodeBuilder('entity_managers', 'array');
        $node
            ->requiresAtLeastOneElement()
            ->useAttributeAsKey('name')
            ->prototype('array')
                ->addDefaultsIfNotSet()
                ->builder($this->getOrmCacheDriverNode('query_cache_driver'))
                ->builder($this->getOrmCacheDriverNode('metadata_cache_driver'))
                ->builder($this->getOrmCacheDriverNode('result_cache_driver'))
                ->scalarNode('connection')->end()
                ->scalarNode('class_metadata_factory_name')->defaultValue('%doctrine.orm.class_metadata_factory_name%')->end()
                ->fixXmlConfig('mapping')
                ->arrayNode('mappings')
                    ->isRequired()
                    ->requiresAtLeastOneElement()
                    ->useAttributeAsKey('name')
                    ->prototype('array')
                        ->beforeNormalization()
                            ->ifString()
                            ->then(function($v) { return array ('type' => $v); })
                        ->end()
                        ->treatNullLike(array ())
                        ->scalarNode('type')->end()
                        ->scalarNode('dir')->end()
                        ->scalarNode('alias')->end()
                        ->scalarNode('prefix')->end()
                        ->booleanNode('is_bundle')->end()
                        ->performNoDeepMerging()
                    ->end()
                ->end()
            ->end()
        ;

        return $node;
    }

    private function getOrmCacheDriverNode($name)
    {
        $node = new NodeBuilder($name, 'array');
        $node
            ->addDefaultsIfNotSet()
            ->beforeNormalization()
                ->ifString()
                ->then(function($v) { return array ('type' => $v); })
            ->end()
            ->scalarNode('type')->defaultValue('array')->isRequired()->end()
            ->scalarNode('host')->end()
            ->scalarNode('port')->end()
            ->scalarNode('instance_class')->end()
            ->scalarNode('class')->end()
        ;

        return $node;
    }
}
