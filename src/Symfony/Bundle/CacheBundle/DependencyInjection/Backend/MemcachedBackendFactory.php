<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\CacheBundle\DependencyInjection\Backend;

use Symfony\Component\Config\Definition\Builder\NodeBuilder;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\DefinitionDecorator;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\DependencyInjection\Alias;

/**
 * Memcached backend.
 *
 * @author Victor Berchet <victor@suumit.com>
 */
class MemcachedBackendFactory extends AbstractBackendFactory
{
    private $factories = array();

    public function addConfiguration(NodeBuilder $builder)
    {
        // TODO fix config
        $builder
            ->arrayNode($this->getConfigKey())
            ->fixXmlConfig('server')
            ->children()
                ->arrayNode('servers')
                    ->useAttributeAsKey('name')
                    ->prototype('array')
                        ->children()
                            ->scalarNode('host')
                                ->beforeNormalization()
                                    ->ifInArray(array('127.0.0.1', '::1', '0:0:0:0:0:0:0:1'))
                                    ->then(function($v) { return 'localhost'; })
                                ->end()
                                ->defaultValue('%cache.memcached.defaults.host%')
                            ->end()
                            ->scalarNode('port')->defaultValue('%cache.memcached.defaults.port%')->end()
                            ->scalarNode('weight')->defaultValue('%cache.memcached.defaults.weight%')->end()
        ;
    }

    public function createService($id, ContainerBuilder $container, $config)
    {
        $signature = $this->getSignature($config);
        $servers = array();

        if (isset($this->factories[$signature])) {
            $container->setAlias($id, $this->factories[$signature]);
        } else {
            $definition = new DefinitionDecorator('cache.backend.memcached');
            foreach ($config['servers'] as $server) {
                $servers[] = array($server['host'], $server['port'], $server['weight']);
            }

            if (count($servers) > 1) {
                $definition->addMethodCall('addServers', $servers);
            } else if (count($servers)) {
                // TODO remove the if above once the config gets fixed
                $definition->addMethodCall('addServer', $servers[0]);
            }

            $container->setDefinition($id, $definition);
            $this->factories[$signature] = $id;
        }
    }

    protected function getSignature(array $config)
    {
        // TODO check if the server ordering is significative
        $description = "";
        foreach ($config['servers'] as $server) {
            $description .= serialize($server);
        }
        return md5($description);
    }

}