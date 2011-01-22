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
use Symfony\Component\DependencyInjection\Resource\FileResource;
use Symfony\Bundle\DoctrineAbstractBundle\DependencyInjection\AbstractDoctrineExtension;

/**
 * DoctrineExtension is an extension for the Doctrine DBAL and ORM library.
 *
 * @author Jonathan H. Wage <jonwage@gmail.com>
 * @author Fabien Potencier <fabien.potencier@symfony-project.com>
 * @author Benjamin Eberlei <kontakt@beberlei.de>
 */
class DoctrineExtension extends AbstractDoctrineExtension
{
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
    public function dbalLoad(array $configs, ContainerBuilder $container)
    {
        $config = $this->mergeDbalConfig($configs);
        
        $loader = new XmlFileLoader($container, __DIR__.'/../Resources/config');
        $loader->load('dbal.xml');

        $container->setAlias('database_connection', sprintf('doctrine.dbal.%s_connection', $config['default_connection']));
        $container->setParameter('doctrine.dbal.default_connection', $config['default_connection']);

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
    protected function mergeDbalConfig(array $configs)
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
        $supportedConfigParams = array(
            'configuration-class'   => 'configuration_class',
            'platform-service'      => 'platform_service',
            'configuration_class'   => 'configuration_class',
            'platform_service'      => 'platform_service'
        );
        $mergedConfig = array(
            'default_connection'  => 'default',
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
                'configuration_class' => 'Doctrine\DBAL\Configuration',
                'wrapper_class'       => null,
            ),
        );

        foreach ($configs AS $config) {
            if (isset($config['default-connection'])) {
                $mergedConfig['default_connection'] = $config['default-connection'];
            } else if (isset($config['default_connection'])) {
                $mergedConfig['default_connection'] = $config['default_connection'];
            }
        }

        foreach ($configs AS $config) {
            if (isset($config['connections'])) {
                $configConnections = $config['connections'];
                if (isset($config['connections']['connection']) && isset($config['connections']['connection'][0])) {
                    $configConnections = $config['connections']['connection'];
                }
            } else {
                $configConnections[$mergedConfig['default_connection']] = $config;
            }
            
            foreach ($configConnections as $name => $connection) {
                $connectionName = isset($connection['id']) ? $connection['id'] : $name;
                if (!isset($mergedConfig['connections'][$connectionName])) {
                    $mergedConfig['connections'][$connectionName] = $connectionDefaults;
                }
                $mergedConfig['connections'][$connectionName]['name'] = $connectionName;

                foreach ($connection AS $k => $v) {
                    if (isset($supportedConnectionParams[$k])) {
                        $mergedConfig['connections'][$connectionName]['driver'][$supportedConnectionParams[$k]] = $v;
                    } else if (isset($supportedConfigParams[$k])) {
                        $mergedConfig['connections'][$connectionName]['container'][$supportedConfigParams[$k]] = $v;
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
        $containerDef = new Definition($connection['container']['configuration_class']);
        $containerDef->setPublic(false);
        $containerDef->addMethodCall('setSQLLogger', array(new Reference('doctrine.dbal.logger')));
        $container->setDefinition(sprintf('doctrine.dbal.%s_connection.configuration', $connection['name']), $containerDef);

        $driverOptions = $connection['driver'];

        $driverDef = new Definition('Doctrine\DBAL\DriverManager');
        $driverDef->setFactoryMethod('getConnection');
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

    public function ormLoad(array $configs, ContainerBuilder $container)
    {
        foreach ($configs as $config) {
            $this->doOrmLoad($config, $container);
        }
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
    protected function doOrmLoad($config, ContainerBuilder $container)
    {
        $this->loadOrmDefaults($config, $container);
        $this->loadOrmEntityManagers($config, $container);
    }

    /**
     * Loads the ORM default configuration.
     *
     * @param array $config An array of configuration settings
     * @param ContainerBuilder $container A ContainerBuilder instance
     */
    protected function loadOrmDefaults(array $config, ContainerBuilder $container)
    {
        // arbitrary service that is always part of the "orm" services. Its used to check if the
        // defaults have to applied (first time run) or ignored (second or n-th run due to imports)
        if (!$container->hasDefinition('doctrine.orm.metadata.annotation_reader')) {
            $loader = new XmlFileLoader($container, __DIR__.'/../Resources/config');
            $loader->load('orm.xml');
        }

        // Allow these application configuration options to override the defaults
        $options = array(
            'default_entity_manager',
            'default_connection',
            'metadata_cache_driver',
            'query_cache_driver',
            'result_cache_driver',
            'proxy_namespace',
            'proxy_dir',
            'auto_generate_proxy_classes',
            'class_metadata_factory_name',
        );
        foreach ($options as $key) {
            if (isset($config[$key])) {
                $container->setParameter('doctrine.orm.'.$key, $config[$key]);
            }

            $nKey = str_replace('_', '-', $key);
            if (isset($config[$nKey])) {
                $container->setParameter('doctrine.orm.'.$key, $config[$nKey]);
            }
        }
    }

    /**
     * Loads the configured ORM entity managers.
     *
     * @param array $config An array of configuration settings
     * @param ContainerBuilder $container A ContainerBuilder instance
     */
    protected function loadOrmEntityManagers(array $config, ContainerBuilder $container)
    {
        $entityManagers = $this->getOrmEntityManagers($config, $container);
        foreach ($entityManagers as $name => $entityManager) {
            $entityManager['name'] = $name;
            $this->loadOrmEntityManager($entityManager, $container);
        }
    }

    /**
     * Loads a configured ORM entity manager.
     *
     * You need to be aware that ormLoad() can be called multiple times, which makes this method tricky to implement.
     * There are two possible runtime scenarios:
     *
     * 1. If the EntityManager was defined before, override only the new calls to Doctrine\ORM\Configuration
     * 2. If the EntityManager was not defined before, gather all the defaults for not specified options and set all the information.
     *
     * @param array $entityManager A configured ORM entity manager.
     * @param ContainerBuilder $container A ContainerBuilder instance
     */
    protected function loadOrmEntityManager(array $entityManager, ContainerBuilder $container)
    {
        $defaultEntityManager = $container->getParameter('doctrine.orm.default_entity_manager');
        $configServiceName = sprintf('doctrine.orm.%s_configuration', $entityManager['name']);

        if ($container->hasDefinition($configServiceName)) {
            $ormConfigDef = $container->getDefinition($configServiceName);
        } else {
            $ormConfigDef = new Definition('Doctrine\ORM\Configuration');
            $ormConfigDef->setPublic(false);
            $container->setDefinition($configServiceName, $ormConfigDef);
        }

        $this->loadOrmEntityManagerMappingInformation($entityManager, $ormConfigDef, $container);
        $this->loadOrmCacheDrivers($entityManager, $container);

        $uniqueMethods = array(
            'setMetadataCacheImpl'          => new Reference(sprintf('doctrine.orm.%s_metadata_cache', $entityManager['name'])),
            'setQueryCacheImpl'             => new Reference(sprintf('doctrine.orm.%s_query_cache', $entityManager['name'])),
            'setResultCacheImpl'            => new Reference(sprintf('doctrine.orm.%s_result_cache', $entityManager['name'])),
            'setMetadataDriverImpl'         => new Reference('doctrine.orm.'.$entityManager['name'].'_metadata_driver'),
            'setProxyDir'                   => $container->getParameter('doctrine.orm.proxy_dir'),
            'setProxyNamespace'             => $container->getParameter('doctrine.orm.proxy_namespace'),
            'setAutoGenerateProxyClasses'   => $container->getParameter('doctrine.orm.auto_generate_proxy_classes'),
            'setClassMetadataFactoryName'   => $container->getParameter('doctrine.orm.class_metadata_factory_name'),
        );
        foreach ($uniqueMethods as $method => $arg) {
            if ($ormConfigDef->hasMethodCall($method)) {
                $ormConfigDef->removeMethodCall($method);
            }
            $ormConfigDef->addMethodCall($method, array($arg));
        }

        $entityManagerService = sprintf('doctrine.orm.%s_entity_manager', $entityManager['name']);

        if (!$container->hasDefinition($entityManagerService) || isset($entityManager['connection'])) {
            $connectionName = isset($entityManager['connection']) ? $entityManager['connection'] : $entityManager['name'];

            $ormEmArgs = array(
                new Reference(sprintf('doctrine.dbal.%s_connection', $connectionName)),
                new Reference(sprintf('doctrine.orm.%s_configuration', $entityManager['name']))
            );
            $ormEmDef = new Definition('%doctrine.orm.entity_manager_class%', $ormEmArgs);
            $ormEmDef->setFactoryMethod('create');
            $ormEmDef->addTag('doctrine.orm.entity_manager');
            $container->setDefinition($entityManagerService, $ormEmDef);

            if ($entityManager['name'] == $defaultEntityManager) {
                $container->setAlias(
                    'doctrine.orm.entity_manager',
                    sprintf('doctrine.orm.%s_entity_manager', $entityManager['name'])
                );
            }
            $container->setAlias(
                sprintf('doctrine.orm.%s_entity_manager.event_manager', $entityManager['name']),
                new Alias(sprintf('doctrine.dbal.%s_connection.event_manager', $connectionName), false)
            );
        }
    }

    /**
     * Gets the configured entity managers.
     *
     * @param array $config An array of configuration settings
     * @param ContainerBuilder $container A ContainerBuilder instance
     */
    protected function getOrmEntityManagers(array $config, ContainerBuilder $container)
    {
        $defaultEntityManager = $container->getParameter('doctrine.orm.default_entity_manager');
        $entityManagers = array();
        if (isset($config['entity-managers'])) {
            $config['entity_managers'] = $config['entity-managers'];
        }

        if (isset($config['entity_managers'])) {
            $configEntityManagers = $config['entity_managers'];
            if (isset($config['entity_managers']['entity-manager'])) {
                $config['entity_managers']['entity_manager'] = $config['entity_managers']['entity-manager'];
            }

            if (isset($config['entity_managers']['entity_manager']) && isset($config['entity_managers']['entity_manager'][0])) {
                // Multiple entity managers
                $configEntityManagers = $config['entity_managers']['entity_manager'];
            }
            foreach ($configEntityManagers as $name => $entityManager) {
                $entityManagers[isset($entityManager['id']) ? $entityManager['id'] : $name] = $entityManager;
            }
        } else {
            $entityManagers = array($defaultEntityManager => $config);
        }
        return $entityManagers;
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

        if ($ormConfigDef->hasMethodCall('setEntityNamespaces')) {
            // TODO: Can we make a method out of it on Definition? replaceMethodArguments() or something.
            $calls = $ormConfigDef->getMethodCalls();
            foreach ($calls AS $call) {
                if ($call[0] == 'setEntityNamespaces') {
                    $this->aliasMap = array_merge($call[1][0], $this->aliasMap);
                }
            }
            $method = $ormConfigDef->removeMethodCall('setEntityNamespaces');
        }
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
        $this->loadOrmEntityManagerMetadataCacheDriver($entityManager, $container);
        $this->loadOrmEntityManagerQueryCacheDriver($entityManager, $container);
        $this->loadOrmEntityManagerResultCacheDriver($entityManager, $container);
    }

    /**
     * Loads a configured entity managers metadata cache driver.
     *
     * @param array $entityManager A configured ORM entity manager.
     * @param ContainerBuilder $container A ContainerBuilder instance
     */
    protected function loadOrmEntityManagerMetadataCacheDriver(array $entityManager, ContainerBuilder $container)
    {
        $metadataCacheDriverService = sprintf('doctrine.orm.%s_metadata_cache', $entityManager['name']);
        if (!$container->hasDefinition($metadataCacheDriverService) || isset($entityManager['metadata-cache-driver']) || (isset($entityManager['metadata_cache_driver']))) {
            $cacheDriver = $container->getParameter('doctrine.orm.metadata_cache_driver');
            $cacheDriver = isset($entityManager['metadata-cache-driver']) ? $entityManager['metadata-cache-driver'] : (isset($entityManager['metadata_cache_driver']) ? $entityManager['metadata_cache_driver'] : $cacheDriver);
            $cacheDef = $this->getEntityManagerCacheDefinition($entityManager, $cacheDriver, $container);
            $container->setDefinition($metadataCacheDriverService, $cacheDef);
        }
    }

    /**
     * Loads a configured entity managers query cache driver.
     *
     * @param array $entityManager A configured ORM entity manager.
     * @param ContainerBuilder $container A ContainerBuilder instance
     */
    protected function loadOrmEntityManagerQueryCacheDriver(array $entityManager, ContainerBuilder $container)
    {
        $queryCacheDriverService = sprintf('doctrine.orm.%s_query_cache', $entityManager['name']);
        if (!$container->hasDefinition($queryCacheDriverService) || isset($entityManager['query-cache-driver']) || isset($entityManager['query_cache_driver'])) {
            $cacheDriver = $container->getParameter('doctrine.orm.query_cache_driver');
            $cacheDriver = isset($entityManager['query-cache-driver']) ? $entityManager['query-cache-driver'] : (isset($entityManager['query_cache_driver']) ? $entityManager['query_cache_driver'] : $cacheDriver);
            $cacheDef = $this->getEntityManagerCacheDefinition($entityManager, $cacheDriver, $container);
            $container->setDefinition($queryCacheDriverService, $cacheDef);
        }
    }

    /**
     * Loads a configured entity managers result cache driver.
     *
     * @param array $entityManager A configured ORM entity manager.
     * @param ContainerBuilder $container A ContainerBuilder instance
     */
    protected function loadOrmEntityManagerResultCacheDriver(array $entityManager, ContainerBuilder $container)
    {
        $resultCacheDriverService = sprintf('doctrine.orm.%s_result_cache', $entityManager['name']);
        if (!$container->hasDefinition($resultCacheDriverService) || isset($entityManager['result-cache-driver']) || isset($entityManager['result_cache_driver'])) {
            $cacheDriver = $container->getParameter('doctrine.orm.result_cache_driver');
            $cacheDriver = isset($entityManager['result-cache-driver']) ? $entityManager['result-cache-driver'] : (isset($entityManager['result_cache_driver']) ? $entityManager['result_cache_driver'] : $cacheDriver);
            $cacheDef = $this->getEntityManagerCacheDefinition($entityManager, $cacheDriver, $container);
            $container->setDefinition($resultCacheDriverService, $cacheDef);
        }
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
            $memcacheClass = isset($cacheDriver['class']) ? $cacheDriver['class'] : '%'.sprintf('doctrine.orm.cache.%s_class', $type).'%';
            $cacheDef = new Definition($memcacheClass);
            $cacheDef->setPublic(false);
            $memcacheHost = is_array($cacheDriver) && isset($cacheDriver['host']) ? $cacheDriver['host'] : '%doctrine.orm.cache.memcache_host%';
            $memcachePort = is_array($cacheDriver) && isset($cacheDriver['port']) ? $cacheDriver['port'] : '%doctrine.orm.cache.memcache_port%';
            $memcacheInstanceClass = is_array($cacheDriver) && isset($cacheDriver['instance-class']) ? $cacheDriver['instance-class'] : (is_array($cacheDriver) && isset($cacheDriver['instance_class']) ? $cacheDriver['instance_class'] : '%doctrine.orm.cache.memcache_instance_class%');
            $memcacheInstance = new Definition($memcacheInstanceClass);
            $memcacheInstance->addMethodCall('connect', array($memcacheHost, $memcachePort));
            $container->setDefinition(sprintf('doctrine.orm.%s_memcache_instance', $entityManager['name']), $memcacheInstance);
            $cacheDef->addMethodCall('setMemcache', array(new Reference(sprintf('doctrine.orm.%s_memcache_instance', $entityManager['name']))));
        } else {
            $cacheDef = new Definition('%'.sprintf('doctrine.orm.cache.%s_class', $type).'%');
            $cacheDef->setPublic(false);
        }
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

    /**
     * Returns the recommended alias to use in XML.
     *
     * This alias is also the mandatory prefix to use when using YAML.
     *
     * @return string The alias
     */
    public function getAlias()
    {
        return 'doctrine';
    }
}
