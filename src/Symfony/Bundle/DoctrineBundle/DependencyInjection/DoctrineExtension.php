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

use Symfony\Component\DependencyInjection\Alias;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Config\Resource\FileResource;
use Symfony\Bundle\DoctrineAbstractBundle\DependencyInjection\AbstractDoctrineExtension;
use Symfony\Component\Config\FileLocator;

/**
 * DoctrineExtension is an extension for the Doctrine DBAL and ORM library.
 *
 * @author Jonathan H. Wage <jonwage@gmail.com>
 * @author Fabien Potencier <fabien.potencier@symfony-project.com>
 * @author Benjamin Eberlei <kontakt@beberlei.de>
 */
class DoctrineExtension extends AbstractDoctrineExtension
{
    public function load(array $configs, ContainerBuilder $container)
    {
        $dbal = $orm = array();
        foreach ($configs as $config) {
            if (isset($config['dbal'])) {
                $dbal[] = $config['dbal'];
            }

            if (isset($config['orm'])) {
                $orm[] = $config['orm'];
            }
        }

        if (!empty($dbal)) {
            $this->dbalLoad($dbal, $container);
        }

        if (!empty($orm)) {
            $this->ormLoad($orm, $container);
        }
    }

    /**
     * Loads the DBAL configuration.
     *
     * Usage example:
     *
     *      <doctrine:dbal id="myconn" dbname="sfweb" user="root" />
     *
     * @param array $config An array of configuration settings
     * @param ContainerBuilder $container A ContainerBuilder instance
     */
    protected function dbalLoad(array $configs, ContainerBuilder $container)
    {
        $loader = new XmlFileLoader($container, new FileLocator(__DIR__.'/../Resources/config'));
        $loader->load('dbal.xml');

        $config = $this->mergeDbalConfig($configs, $container);

        $container->setAlias('database_connection', sprintf('doctrine.dbal.%s_connection', $config['default_connection']));
        $container->setParameter('doctrine.dbal.default_connection', $config['default_connection']);
        $container->setParameter('doctrine.dbal.types', $config['types']);

        foreach ($config['connections'] as $name => $connection) {
            $this->loadDbalConnection($connection, $container);
        }
    }

    /**
     * Merges a set of exclusive independent DBAL configurations into another.
     *
     * Beginning from the default settings this method acts as incremental merge
     * of all the configurations that are passed through multiple environment
     * and fallbacks for example config.yml + config_dev.yml
     *
     * @param array $configs
     * @return array
     */
    protected function mergeDbalConfig(array $configs, $container)
    {
        $supportedConnectionParams = array(
            'dbname'                => 'dbname',
            'host'                  => 'host',
            'port'                  => 'port',
            'user'                  => 'user',
            'password'              => 'password',
            'driver'                => 'driver',
            'driver-class'          => 'driverClass', // doctrine conv.
            'options'               => 'driverOptions', // doctrine conv.
            'path'                  => 'path',
            'unix-socket'           => 'unix_socket',
            'memory'                => 'memory',
            'driver_class'          => 'driverClass', // doctrine conv.
            'unix_socket'           => 'unix_socket',
            'wrapper_class'         => 'wrapperClass', // doctrine conv.
            'wrapper-class'         => 'wrapperClass', // doctrine conv.
            'charset'               => 'charset',
        );
        $supportedContrainerParams = array(
            'platform-service'      => 'platform_service',
            'platform_service'      => 'platform_service',
            'logging'               => 'logging',
        );
        $mergedConfig = array(
            'default_connection'  => 'default',
            'types' => array(),
        );
        $connectionDefaults = array(
            'driver' => array(
                'host'                => 'localhost',
                'driver'              => 'pdo_mysql',
                'driverOptions'       => array(),
                'user'                => 'root',
                'password'            => null,
                'port'                => null,
            ),
            'container' => array(
                'logging'             => (bool)$container->getParameter('doctrine.dbal.logging')
            ),
        );

        foreach ($configs as $config) {
            if (isset($config['default-connection'])) {
                $mergedConfig['default_connection'] = $config['default-connection'];
            } else if (isset($config['default_connection'])) {
                $mergedConfig['default_connection'] = $config['default_connection'];
            }

            // Handle DBAL Types
            if (isset($config['types'])) {
                if (isset($config['types']['type'][0])) {
                    $config['types'] = $config['types']['type'];
                }
                foreach ($config['types'] AS $name => $type) {
                    if (is_array($type) && isset($type['name']) && isset($type['class'])) { // xml case
                        $mergedConfig['types'][$type['name']] = $type['class'];
                    } else { // yml case
                        $mergedConfig['types'][$name] = $type;
                    }
                }
            }
        }

        foreach ($configs as $config) {
            if (isset($config['connections'])) {
                $configConnections = $config['connections'];
                if (isset($config['connections']['connection']) && isset($config['connections']['connection'][0])) {
                    $configConnections = $config['connections']['connection'];
                }
            } else {
                $configConnections[$mergedConfig['default_connection']] = $config;
            }
            
            foreach ($configConnections as $name => $connection) {
                $connectionName = isset($connection['name']) ? $connection['name'] : $name;
                if (!isset($mergedConfig['connections'][$connectionName])) {
                    $mergedConfig['connections'][$connectionName] = $connectionDefaults;
                }
                $mergedConfig['connections'][$connectionName]['name'] = $connectionName;

                foreach ($connection as $k => $v) {
                    if (isset($supportedConnectionParams[$k])) {
                        $mergedConfig['connections'][$connectionName]['driver'][$supportedConnectionParams[$k]] = $v;
                    } else if (isset($supportedContrainerParams[$k])) {
                        $mergedConfig['connections'][$connectionName]['container'][$supportedContrainerParams[$k]] = $v;
                    }
                }
            }
        }

        return $mergedConfig;
    }

    /**
     * Loads a configured DBAL connection.
     *
     * @param array $connection A dbal connection configuration.
     * @param ContainerBuilder $container A ContainerBuilder instance
     */
    protected function loadDbalConnection(array $connection, ContainerBuilder $container)
    {
        $containerDef = new Definition($container->getParameter('doctrine.dbal.configuration_class'));
        $containerDef->setPublic(false);
        if (isset($connection['container']['logging']) && $connection['container']['logging']) {
            $containerDef->addMethodCall('setSQLLogger', array(new Reference('doctrine.dbal.logger')));
        }
        $container->setDefinition(sprintf('doctrine.dbal.%s_connection.configuration', $connection['name']), $containerDef);

        $driverOptions = $connection['driver'];

        $driverDef = new Definition('Doctrine\DBAL\Connection');
        $driverDef->setFactoryService('doctrine.dbal.connection_factory');
        $driverDef->setFactoryMethod('createConnection');
        $container->setDefinition(sprintf('doctrine.dbal.%s_connection', $connection['name']), $driverDef);

        // event manager
        $eventManagerId = sprintf('doctrine.dbal.%s_connection.event_manager', $connection['name']);
        $eventManagerDef = new Definition('%doctrine.dbal.event_manager_class%');
        $eventManagerDef->setPublic(false);
        $container->setDefinition($eventManagerId, $eventManagerDef);

        if ($container->getParameter('doctrine.dbal.default_connection') == $connection['name']) {
            $container->setAlias('doctrine.dbal.event_manager', new Alias(sprintf('doctrine.dbal.%s_connection.event_manager', $connection['name']), false));
        }

        if (isset($driverOptions['charset'])) {
            if ( (isset($driverOptions['driver']) && stripos($driverOptions['driver'], 'mysql') !== false) ||
                 (isset($driverOptions['driverClass']) && stripos($driverOptions['driverClass'], 'mysql') !== false)) {
                $mysqlSessionInit = new Definition('%doctrine.dbal.events.mysql_session_init.class%');
                $mysqlSessionInit->setArguments(array($driverOptions['charset']));
                $mysqlSessionInit->setPublic(false);
                $mysqlSessionInit->addTag(sprintf('doctrine.dbal.%s_event_subscriber', $connection['name']));

                $container->setDefinition(
                    sprintf('doctrine.dbal.%s_connection.events.mysqlsessioninit', $connection['name']),
                    $mysqlSessionInit
                );
                unset($driverOptions['charset']);
            }
        }

        if (isset($connection['container']['platform_service'])) {
            $driverOptions['platform'] = new Reference($connection['container']['platform_service']);
        }

        $driverDef->setArguments(array(
            $driverOptions,
            new Reference(sprintf('doctrine.dbal.%s_connection.configuration', $connection['name'])),
            new Reference(sprintf('doctrine.dbal.%s_connection.event_manager', $connection['name']))
        ));
    }

    /**
     * Loads the Doctrine ORM configuration.
     *
     * Usage example:
     *
     *     <doctrine:orm id="mydm" connection="myconn" />
     *
     * @param array $config An array of configuration settings
     * @param ContainerBuilder $container A ContainerBuilder instance
     */
    protected function ormLoad(array $configs, ContainerBuilder $container)
    {
        $loader = new XmlFileLoader($container, new FileLocator(__DIR__.'/../Resources/config'));
        $loader->load('orm.xml');

        $config = $this->mergeOrmConfig($configs, $container);
        
        $options = array('default_entity_manager', 'default_connection', 'auto_generate_proxy_classes');
        foreach ($options as $key) {
            $container->setParameter('doctrine.orm.'.$key, $config[$key]);
        }

        foreach ($config['entity_managers'] as $entityManager) {
            $this->loadOrmEntityManager($entityManager, $container);

            if ($entityManager['name'] == $config['default_entity_manager']) {
                $container->setAlias(
                    'doctrine.orm.entity_manager',
                    sprintf('doctrine.orm.%s_entity_manager', $entityManager['name'])
                );
            }
        }

        $container->setParameter('doctrine.orm.entity_managers', array_keys($config['entity_managers']));
    }

    protected function mergeOrmConfig(array $configs, $container)
    {
        $supportedEntityManagerOptions = array(
            'metadata_cache_driver'             => 'metadata_cache_driver',
            'query_cache_driver'                => 'query_cache_driver',
            'result_cache_driver'               => 'result_cache_driver',
            'class_metadata_factory_name'       => 'class_metadata_factory_name',
            'metadata-cache-driver'             => 'metadata_cache_driver',
            'query-cache-driver'                => 'query_cache_driver',
            'result-cache-driver'               => 'result_cache_driver',
            'class-metadata-factory-name'       => 'class_metadata_factory_name',
            'connection'                        => 'connection'
        );

        $mergedConfig = array(
            'default_entity_manager' => 'default',
            'default_connection' => 'default',
            'auto_generate_proxy_classes' => false,
            'entity_managers' => array(),
        );

        $defaultManagerOptions = array(
            'proxy_dir'                     => $container->getParameter('doctrine.orm.proxy_dir'),
            'proxy_namespace'               => $container->getParameter('doctrine.orm.proxy_namespace'),
            'auto_generate_proxy_classes'   => false,
            'metadata_cache_driver'         => $container->getParameter('doctrine.orm.metadata_cache_driver'),
            'query_cache_driver'            => $container->getParameter('doctrine.orm.query_cache_driver'),
            'result_cache_driver'           => $container->getParameter('doctrine.orm.result_cache_driver'),
            'configuration_class'           => $container->getParameter('doctrine.orm.configuration_class'),
            'entity_manager_class'          => $container->getParameter('doctrine.orm.entity_manager_class'),
            'class_metadata_factory_name'   => $container->getParameter('doctrine.orm.class_metadata_factory_name'),
        );
        
        foreach ($configs as $config) {
            if (isset($config['default-entity-manager'])) {
                $mergedConfig['default_entity_manager'] = $config['default-entity-manager'];
            } else if (isset($config['default_entity_manager'])) {
                $mergedConfig['default_entity_manager'] = $config['default_entity_manager'];
            }
            if (isset($config['default-connection'])) {
                $mergedConfig['default_connection'] = $config['default-connection'];
            } else if (isset($config['default_connection'])) {
                $mergedConfig['default_connection'] = $config['default_connection'];
            }
            if (isset($config['auto_generate_proxy_classes'])) {
                $defaultManagerOptions['auto_generate_proxy_classes'] = $config['auto_generate_proxy_classes'];
            }
            if (isset($config['auto-generate-proxy-classes'])) {
                $defaultManagerOptions['auto_generate_proxy_classes'] = $config['auto-generate-proxy-classes'];
            }
        }
        $defaultManagerOptions['connection'] = $mergedConfig['default_connection'];

        foreach ($configs as $config) {
            if (isset($config['entity-managers'])) {
                $config['entity_managers'] = $config['entity-managers'];
            }

            $entityManagers = array();
            if (isset($config['entity_managers'])) {
                $configEntityManagers = $config['entity_managers'];
                if (isset($config['entity_managers']['entity-manager'])) {
                    $config['entity_managers']['entity_manager'] = $config['entity_managers']['entity-manager'];
                }
                if (isset($config['entity_managers']['entity_manager']) && isset($config['entity_managers']['entity_manager'][0])) {
                    $configEntityManagers = $config['entity_managers']['entity_manager'];
                }
                
                foreach ($configEntityManagers as $name => $entityManager) {
                    $name = isset($entityManager['name']) ? $entityManager['name'] : $name;
                    $entityManagers[$name] = $entityManager;
                }
            } else {
                $entityManagers = array($mergedConfig['default_entity_manager'] => $config);
            }

            foreach ($entityManagers as $name => $managerConfig) {
                if (!isset($mergedConfig['entity_managers'][$name])) {
                    $mergedConfig['entity_managers'][$name] = $defaultManagerOptions;
                }

                foreach ($managerConfig as $k => $v) {
                    if (isset($supportedEntityManagerOptions[$k])) {
                        $k = $supportedEntityManagerOptions[$k];
                        $mergedConfig['entity_managers'][$name][$k] = $v;
                    }
                }
                $mergedConfig['entity_managers'][$name]['name'] = $name;

                if (isset($managerConfig['mappings'])) {
                    foreach ($managerConfig['mappings'] as $mappingName => $mappingConfig) {
                        if (!isset($mergedConfig['entity_managers'][$name]['mappings'][$mappingName])) {
                            $mergedConfig['entity_managers'][$name]['mappings'][$mappingName] = array();
                        }

                        if (is_array($mappingConfig)) {
                            foreach ($mappingConfig as $k => $v) {
                                $mergedConfig['entity_managers'][$name]['mappings'][$mappingName][$k] = $v;
                            }
                        }
                    }
                }
            }
        }

        return $mergedConfig;
    }

    /**
     * Loads a configured ORM entity manager.
     *
     * You need to be aware that ormLoad() can be called multiple times, which makes this method tricky to implement.
     * There are two possible runtime scenarios:
     *
     * 1. If the EntityManager was defined before, override only the new calls to Doctrine\ORM\Configuration
     * 2. If the EntityManager was not defined beforeefore, gather all the defaults for not specified options and set all the information.
     *
     * @param array $entityManager A configured ORM entity manager.
     * @param ContainerBuilder $container A ContainerBuilder instance
     */
    protected function loadOrmEntityManager(array $entityManager, ContainerBuilder $container)
    {
        $configServiceName = sprintf('doctrine.orm.%s_configuration', $entityManager['name']);

        $ormConfigDef = new Definition('Doctrine\ORM\Configuration');
        $ormConfigDef->setPublic(false);
        $container->setDefinition($configServiceName, $ormConfigDef);

        $this->loadOrmEntityManagerMappingInformation($entityManager, $ormConfigDef, $container);
        $this->loadOrmCacheDrivers($entityManager, $container);

        $uniqueMethods = array(
            'setMetadataCacheImpl'          => new Reference(sprintf('doctrine.orm.%s_metadata_cache', $entityManager['name'])),
            'setQueryCacheImpl'             => new Reference(sprintf('doctrine.orm.%s_query_cache', $entityManager['name'])),
            'setResultCacheImpl'            => new Reference(sprintf('doctrine.orm.%s_result_cache', $entityManager['name'])),
            'setMetadataDriverImpl'         => new Reference('doctrine.orm.'.$entityManager['name'].'_metadata_driver'),
            'setProxyDir'                   => $entityManager['proxy_dir'],
            'setProxyNamespace'             => $entityManager['proxy_namespace'],
            'setAutoGenerateProxyClasses'   => $entityManager['auto_generate_proxy_classes'],
            'setClassMetadataFactoryName'   => $entityManager['class_metadata_factory_name'],
        );
        foreach ($uniqueMethods as $method => $arg) {
            $ormConfigDef->addMethodCall($method, array($arg));
        }

        $entityManagerService = sprintf('doctrine.orm.%s_entity_manager', $entityManager['name']);
        $connectionName = isset($entityManager['connection']) ? $entityManager['connection'] : $entityManager['name'];

        $ormEmArgs = array(
            new Reference(sprintf('doctrine.dbal.%s_connection', $connectionName)),
            new Reference(sprintf('doctrine.orm.%s_configuration', $entityManager['name']))
        );
        $ormEmDef = new Definition('%doctrine.orm.entity_manager_class%', $ormEmArgs);
        $ormEmDef->setFactoryClass('%doctrine.orm.entity_manager_class%');
        $ormEmDef->setFactoryMethod('create');
        $ormEmDef->addTag('doctrine.orm.entity_manager');
        $container->setDefinition($entityManagerService, $ormEmDef);

        $container->setAlias(
            sprintf('doctrine.orm.%s_entity_manager.event_manager', $entityManager['name']),
            new Alias(sprintf('doctrine.dbal.%s_connection.event_manager', $connectionName), false)
        );
    }

    /**
     * Loads an ORM entity managers bundle mapping information.
     *
     * There are two distinct configuration possibilities for mapping information:
     *
     * 1. Specifiy a bundle and optionally details where the entity and mapping information reside.
     * 2. Specifiy an arbitrary mapping location.
     *
     * @example
     *
     *  doctrine.orm:
     *     mappings:
     *         MyBundle1: ~
     *         MyBundle2: yml
     *         MyBundle3: { type: annotation, dir: Entities/ }
     *         MyBundle4: { type: xml, dir: Resources/config/doctrine/mapping }
     *         MyBundle5:
     *             type: yml
     *             dir: [bundle-mappings1/, bundle-mappings2/]
     *             alias: BundleAlias
     *         arbitrary_key:
     *             type: xml
     *             dir: %kernel.dir%/../src/vendor/DoctrineExtensions/lib/DoctrineExtensions/Entities
     *             prefix: DoctrineExtensions\Entities\
     *             alias: DExt
     *
     * In the case of bundles everything is really optional (which leads to autodetection for this bundle) but
     * in the mappings key everything except alias is a required argument.
     *
     * @param array $entityManager A configured ORM entity manager.
     * @param ContainerBuilder $container A ContainerBuilder instance
     */
    protected function loadOrmEntityManagerMappingInformation(array $entityManager, Definition $ormConfigDef, ContainerBuilder $container)
    {
        // reset state of drivers and alias map. They are only used by this methods and children.
        $this->drivers = array();
        $this->aliasMap = array();

        $this->loadMappingInformation($entityManager, $container);
        $this->registerMappingDrivers($entityManager, $container);
        
        $ormConfigDef->addMethodCall('setEntityNamespaces', array($this->aliasMap));
    }

    protected function getObjectManagerElementName($name)
    {
        return 'doctrine.orm.' . $name;
    }

    protected function getMappingObjectDefaultName()
    {
        return 'Entity';
    }

    protected function getMappingResourceConfigDirectory()
    {
        return 'Resources/config/doctrine/metadata/orm';
    }

    /**
     * Loads a configured entity managers cache drivers.
     *
     * @param array $entityManager A configured ORM entity manager.
     * @param ContainerBuilder $container A ContainerBuilder instance
     */
    protected function loadOrmCacheDrivers(array $entityManager, ContainerBuilder $container)
    {
        $this->loadOrmEntityManagerCacheDriver($entityManager, $container, 'metadata_cache');
        $this->loadOrmEntityManagerCacheDriver($entityManager, $container, 'result_cache');
        $this->loadOrmEntityManagerCacheDriver($entityManager, $container, 'query_cache');
    }

    /**
     * Loads a configured entity managers metadata, query or result cache driver.
     *
     * @param array $entityManager A configured ORM entity manager.
     * @param ContainerBuilder $container A ContainerBuilder instance
     * @param string $cacheName
     */
    protected function loadOrmEntityManagerCacheDriver(array $entityManager, ContainerBuilder $container, $cacheName)
    {
        $cacheDriverService = sprintf('doctrine.orm.%s_%s', $entityManager['name'], $cacheName);

        $driver = $cacheName."_driver";
        $cacheDef = $this->getEntityManagerCacheDefinition($entityManager, $entityManager[$driver], $container);
        $container->setDefinition($cacheDriverService, $cacheDef);
    }

    /**
     * Gets an entity manager cache driver definition for metadata, query and result caches.
     *
     * @param array $entityManager The array configuring an entity manager.
     * @param string|array $cacheDriver The cache driver configuration.
     * @param ContainerBuilder $container
     * @return Definition $cacheDef
     */
    protected function getEntityManagerCacheDefinition(array $entityManager, $cacheDriver, ContainerBuilder $container)
    {
        $type = is_array($cacheDriver) && isset($cacheDriver['type']) ? $cacheDriver['type'] : $cacheDriver;
        if ('memcache' === $type) {
            $cacheDef = new Definition('%doctrine.orm.cache.memcache_class%');
            $memcacheInstance = new Definition('%doctrine.orm.cache.memcache_instance_class%');
            $memcacheInstance->addMethodCall('connect', array(
                '%doctrine.orm.cache.memcache_host%', '%doctrine.orm.cache.memcache_port%'
            ));
            $container->setDefinition(sprintf('doctrine.orm.%s_memcache_instance', $entityManager['name']), $memcacheInstance);
            $cacheDef->addMethodCall('setMemcache', array(new Reference(sprintf('doctrine.orm.%s_memcache_instance', $entityManager['name']))));
        } else if (in_array($type, array('apc', 'array', 'xcache'))) {
            $cacheDef = new Definition('%'.sprintf('doctrine.orm.cache.%s_class', $type).'%');
        }
        $cacheDef->setPublic(false);
        $cacheDef->addMethodCall('setNamespace', array('sf2orm_'.$entityManager['name']));
        return $cacheDef;
    }

    /**
     * Returns the base path for the XSD files.
     *
     * @return string The XSD base path
     */
    public function getXsdValidationBasePath()
    {
        return __DIR__.'/../Resources/config/schema';
    }

    /**
     * Returns the namespace to be used for this extension (XML namespace).
     *
     * @return string The XML namespace
     */
    public function getNamespace()
    {
        return 'http://www.symfony-project.org/schema/dic/doctrine';
    }
}
