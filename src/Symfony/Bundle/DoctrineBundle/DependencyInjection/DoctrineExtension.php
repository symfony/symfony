<?php

namespace Symfony\Bundle\DoctrineBundle\DependencyInjection;

use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\DependencyInjection\Resource\FileResource;

/*
 * This file is part of the Symfony framework.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

/**
 * DoctrineExtension is an extension for the Doctrine DBAL and ORM library.
 *
 * @author     Jonathan H. Wage <jonwage@gmail.com>
 * @author     Fabien Potencier <fabien.potencier@symfony-project.com>
 */
class DoctrineExtension extends Extension
{
    protected $resources = array(
        'dbal' => 'dbal.xml',
        'orm'  => 'orm.xml',
    );

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
    public function dbalLoad($config, ContainerBuilder $container)
    {
        $this->loadDbalDefaults($config, $container);
        $this->loadDbalConnections($config, $container);
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
    public function ormLoad($config, ContainerBuilder $container)
    {
        $this->createOrmProxyDirectory($container->getParameter('kernel.cache_dir'));
        $this->loadOrmDefaults($config, $container);
        $this->loadOrmEntityManagers($config, $container);
    }

    /**
     * Loads the DBAL configuration defaults.
     *
     * @param array $config An array of configuration settings
     * @param ContainerBuilder $container A ContainerBuilder instance
     */
    protected function loadDbalDefaults(array $config, ContainerBuilder $container)
    {
        if (!$container->hasDefinition('doctrine.dbal.logger')) {
            $loader = new XmlFileLoader($container, __DIR__.'/../Resources/config');
            $loader->load($this->resources['dbal']);
        }

        $defaultConnectionName = isset($config['default_connection']) ? $config['default_connection'] : $container->getParameter('doctrine.dbal.default_connection');
        $container->setAlias('database_connection', sprintf('doctrine.dbal.%s_connection', $defaultConnectionName));
        $container->setParameter('doctrine.dbal.default_connection', $defaultConnectionName);
    }

    /**
     * Loads the configured DBAL connections.
     *
     * @param array $config An array of configuration settings
     * @param ContainerBuilder $container A ContainerBuilder instance
     */
    protected function loadDbalConnections(array $config, ContainerBuilder $container)
    {
        $connections = $this->getDbalConnections($config, $container);
        foreach ($connections as $name => $connection) {
            $connection['name'] = $name;
            $this->loadDbalConnection($connection, $container);
        }
    }

    /**
     * Loads a configured DBAL connection.
     *
     * @param array $connection A dbal connection configuration.
     * @param ContainerBuilder $container A ContainerBuilder instance
     */
    protected function loadDbalConnection(array $connection, ContainerBuilder $container)
    {
        // previously registered?
        if ($container->hasDefinition(sprintf('doctrine.dbal.%s_connection', $connection['name']))) {
            $driverDef = $container->getDefinition(sprintf('doctrine.dbal.%s_connection', $connection['name']));
            $arguments = $driverDef->getArguments();
            $driverOptions = $arguments[0];
        } else {
            $containerClass = isset($connection['configuration_class']) ? $connection['configuration_class'] : 'Doctrine\DBAL\Configuration';
            $containerDef = new Definition($containerClass);
            $containerDef->addMethodCall('setSqlLogger', array(new Reference('doctrine.dbal.logger')));
            $container->setDefinition(sprintf('doctrine.dbal.%s_connection.configuration', $connection['name']), $containerDef);

            $eventManagerDef = new Definition($connection['event_manager_class']);
            $container->setDefinition(sprintf('doctrine.dbal.%s_connection.event_manager', $connection['name']), $eventManagerDef);

            $driverOptions = array();
            $driverDef = new Definition('Doctrine\DBAL\DriverManager');
            $driverDef->setFactoryMethod('getConnection');
            $container->setDefinition(sprintf('doctrine.dbal.%s_connection', $connection['name']), $driverDef);
        }

        if (isset($connection['driver'])) {
            $driverOptions['driverClass'] = sprintf('Doctrine\\DBAL\\Driver\\%s\\Driver', $connection['driver']);
        }
        if (isset($connection['wrapper_class'])) {
            $driverOptions['wrapperClass'] = $connection['wrapper_class'];
        }
        if (isset($connection['options'])) {
            $driverOptions['driverOptions'] = $connection['options'];
        }
        foreach (array('dbname', 'host', 'user', 'password', 'path', 'memory', 'port', 'unix_socket', 'charset') as $key) {
            if (isset($connection[$key])) {
                $driverOptions[$key] = $connection[$key];
            }
        }

        $driverDef->setArguments(array(
            $driverOptions,
            new Reference(sprintf('doctrine.dbal.%s_connection.configuration', $connection['name'])),
            new Reference(sprintf('doctrine.dbal.%s_connection.event_manager', $connection['name']))
        ));
    }

    /**
     * Gets the configured DBAL connections.
     *
     * @param array $config An array of configuration settings
     * @param ContainerBuilder $container A ContainerBuilder instance
     */
    protected function getDbalConnections(array $config, ContainerBuilder $container)
    {
        $defaultConnectionName = $container->getParameter('doctrine.dbal.default_connection');
        $defaultConnection = array(
            'driver'              => 'PDOMySql',
            'user'                => 'root',
            'password'            => null,
            'host'                => 'localhost',
            'port'                => null,
            'event_manager_class' => 'Doctrine\Common\EventManager',
            'configuration_class' => 'Doctrine\DBAL\Configuration',
            'wrapper_class'       => null,
            'options'             => array()
        );
        $connections = array();
        if (isset($config['connections'])) {
            $configConnections = $config['connections'];
            if(isset($config['connections']['connection']) && isset($config['connections']['connection'][0])) {
                // Multiple connections
                $configConnections = $config['connections']['connection'];
            }
            foreach ($configConnections as $name => $connection) {
                $connections[isset($connection['id']) ? $connection['id'] : $name] = array_merge($defaultConnection, $connection);
            }
        } else {
            $connections = array($defaultConnectionName => array_merge($defaultConnection, $config));
        }
        return $connections;
    }

    /**
     * Create the Doctrine ORM Entity proxy directory
     */
    protected function createOrmProxyDirectory($tmpDir)
    {
        // Create entity proxy directory
        $proxyCacheDir = $tmpDir.'/doctrine/orm/Proxies';
        if (!is_dir($proxyCacheDir)) {
            if (false === @mkdir($proxyCacheDir, 0777, true)) {
                die(sprintf('Unable to create the Doctrine Proxy directory (%s)', dirname($proxyCacheDir)));
            }
        } elseif (!is_writable($proxyCacheDir)) {
            die(sprintf('Unable to write in the Doctrine Proxy directory (%s)', $proxyCacheDir));
        }
    }

    /**
     * Loads the ORM default configuration.
     *
     * @param array $config An array of configuration settings
     * @param ContainerBuilder $container A ContainerBuilder instance
     */
    protected function loadOrmDefaults(array $config, ContainerBuilder $container)
    {
        if (!$container->hasDefinition('doctrine.orm.metadata_driver.annotation')) {
            $loader = new XmlFileLoader($container, __DIR__.'/../Resources/config');
            $loader->load($this->resources['orm']);
        }

        // Allow these application configuration options to override the defaults
        $options = array(
            'default_entity_manager',
            'default_connection',
            'metadata_cache_driver',
            'query_cache_driver',
            'result_cache_driver',
            'proxy_namespace',
            'auto_generate_proxy_classes'
        );
        foreach ($options as $key) {
            if (isset($config[$key])) {
                $container->setParameter('doctrine.orm.'.$key, $config[$key]);
            }
        }
        $container->setParameter('doctrine.orm.metadata_driver.mapping_dirs', $this->findBundleSubpaths('Resources/config/doctrine/metadata', $container));
        $container->setParameter('doctrine.orm.metadata_driver.entity_dirs', $this->findBundleSubpaths('Entity', $container));
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
     * @param array $entityManager A configured ORM entity manager.
     * @param ContainerBuilder $container A ContainerBuilder instance
     */
    protected function loadOrmEntityManager(array $entityManager, ContainerBuilder $container)
    {
        $defaultEntityManager = $container->getParameter('doctrine.orm.default_entity_manager');
        $proxyCacheDir = $container->getParameter('kernel.cache_dir').'/doctrine/orm/Proxies';

        $ormConfigDef = new Definition('Doctrine\ORM\Configuration');
        $container->setDefinition(sprintf('doctrine.orm.%s_configuration', $entityManager['name']), $ormConfigDef);

        $this->loadOrmEntityManagerBundlesMappingInformation($entityManager, $ormConfigDef, $container);
        $this->loadOrmCacheDrivers($entityManager, $container);

        $methods = array(
            'setMetadataCacheImpl' => new Reference(sprintf('doctrine.orm.%s_metadata_cache', $entityManager['name'])),
            'setQueryCacheImpl' => new Reference(sprintf('doctrine.orm.%s_query_cache', $entityManager['name'])),
            'setResultCacheImpl' => new Reference(sprintf('doctrine.orm.%s_result_cache', $entityManager['name'])),
            'setMetadataDriverImpl' => new Reference('doctrine.orm.metadata_driver'),
            'setProxyDir' => $proxyCacheDir,
            'setProxyNamespace' => $container->getParameter('doctrine.orm.proxy_namespace'),
            'setAutoGenerateProxyClasses' => $container->getParameter('doctrine.orm.auto_generate_proxy_classes')
        );
        foreach ($methods as $method => $arg) {
            $ormConfigDef->addMethodCall($method, array($arg));
        }

        $ormEmArgs = array(
            new Reference(sprintf('doctrine.dbal.%s_connection', isset($entityManager['connection']) ? $entityManager['connection'] : $entityManager['name'])),
            new Reference(sprintf('doctrine.orm.%s_configuration', $entityManager['name']))
        );
        $ormEmDef = new Definition('%doctrine.orm.entity_manager_class%', $ormEmArgs);
        $ormEmDef->setFactoryMethod('create');
        $container->setDefinition(sprintf('doctrine.orm.%s_entity_manager', $entityManager['name']), $ormEmDef);

        if ($entityManager['name'] == $defaultEntityManager) {
            $container->setAlias(
                'doctrine.orm.entity_manager',
                sprintf('doctrine.orm.%s_entity_manager', $entityManager['name'])
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
        if (isset($config['entity_managers'])) {
            $configEntityManagers = $config['entity_managers'];
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
     * @param array $entityManager A configured ORM entity manager.
     * @param ContainerBuilder $container A ContainerBuilder instance
     */
    protected function loadOrmEntityManagerBundlesMappingInformation(array $entityManager, Definition $ormConfigDef, ContainerBuilder $container)
    {
        // configure metadata driver for each bundle based on the type of mapping files found
        $mappingDriverDef = new Definition('%doctrine.orm.metadata.driver_chain_class%');
        $bundleEntityMappings = array();
        $bundleDirs = $container->getParameter('kernel.bundle_dirs');
        $aliasMap = array();
        foreach ($container->getParameter('kernel.bundles') as $className) {
            $tmp = dirname(str_replace('\\', '/', $className));
            $namespace = str_replace('/', '\\', dirname($tmp));
            $class = basename($tmp);

            if (!isset($bundleDirs[$namespace])) {
                continue;
            }

            $type = $this->detectMetadataDriver($bundleDirs[$namespace].'/'.$class, $container);

            if (is_dir($dir = $bundleDirs[$namespace].'/'.$class.'/Entity')) {
                if ($type === null) {
                    $type = 'annotation';
                }
                $aliasMap[$class] = $namespace.'\\'.$class.'\\Entity';
            }

            if ($type !== null) {
                $mappingDriverDef->addMethodCall('addDriver', array(
                        new Reference(sprintf('doctrine.orm.metadata_driver.%s', $type)),
                        $namespace.'\\'.$class.'\\Entity'
                    )
                );
            }
        }
        $ormConfigDef->addMethodCall('setEntityNamespaces', array($aliasMap));

        $container->setDefinition('doctrine.orm.metadata_driver', $mappingDriverDef);
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
        $cacheDriver = $container->getParameter('doctrine.orm.metadata_cache_driver');
        $cacheDriver = isset($entityManager['metadata_cache_driver']) ? $entityManager['metadata_cache_driver'] : $cacheDriver;
        $cacheDef = $this->getEntityManagerCacheDefinition($entityManager, $cacheDriver, $container);
        $container->setDefinition(sprintf('doctrine.orm.%s_metadata_cache', $entityManager['name']), $cacheDef);
    }

    /**
     * Loads a configured entity managers query cache driver.
     *
     * @param array $entityManager A configured ORM entity manager.
     * @param ContainerBuilder $container A ContainerBuilder instance
     */
    protected function loadOrmEntityManagerQueryCacheDriver(array $entityManager, ContainerBuilder $container)
    {
        $cacheDriver = $container->getParameter('doctrine.orm.query_cache_driver');
        $cacheDriver = isset($entityManager['query_cache_driver']) ? $entityManager['query_cache_driver'] : $cacheDriver;
        $cacheDef = $this->getEntityManagerCacheDefinition($entityManager, $cacheDriver, $container);
        $container->setDefinition(sprintf('doctrine.orm.%s_query_cache', $entityManager['name']), $cacheDef);
    }

    /**
     * Loads a configured entity managers result cache driver.
     *
     * @param array $entityManager A configured ORM entity manager.
     * @param ContainerBuilder $container A ContainerBuilder instance
     */
    protected function loadOrmEntityManagerResultCacheDriver(array $entityManager, ContainerBuilder $container)
    {
        $cacheDriver = $container->getParameter('doctrine.orm.result_cache_driver');
        $cacheDriver = isset($entityManager['result_cache_driver']) ? $entityManager['result_cache_driver'] : $cacheDriver;
        $cacheDef = $this->getEntityManagerCacheDefinition($entityManager, $cacheDriver, $container);
        $container->setDefinition(sprintf('doctrine.orm.%s_result_cache', $entityManager['name']), $cacheDef);
    }

    /**
     * Gets an entity manager cache driver definition for metadata, query and result caches.
     *
     * @param array $entityManager
     * @param string $cacheDriver
     * @param ContainerBuilder $container
     * @return Definition $cacheDef
     */
    protected function getEntityManagerCacheDefinition(array $entityManager, $cacheDriver, ContainerBuilder $container)
    {
        $type = is_array($cacheDriver) && isset($cacheDriver['type']) ? $cacheDriver['type'] : $cacheDriver;
        if ($type === 'memcache') {
            $memcacheClass = isset($cacheDriver['class']) ? $cacheDriver['class'] : '%'.sprintf('doctrine.orm.cache.%s_class', $type).'%';
            $cacheDef = new Definition($memcacheClass);
            $memcacheHost = isset($cacheDriver['host']) ? $cacheDriver['host'] : '%doctrine.orm.cache.memcache_host%';
            $memcachePort = isset($cacheDriver['port']) ? $cacheDriver['port'] : '%doctrine.orm.cache.memcache_port%';
            $memcacheInstanceClass = isset($cacheDriver['instance_class']) ? $cacheDriver['instance_class'] : '%doctrine.orm.cache.memcache_instance_class%';
            $memcacheInstance = new Definition($memcacheInstanceClass);
            $memcacheInstance->addMethodCall('connect', array($memcacheHost, $memcachePort));
            $container->setDefinition(sprintf('doctrine.orm.%s_memcache_instance', $entityManager['name']), $memcacheInstance);
            $cacheDef->addMethodCall('setMemcache', array(new Reference(sprintf('doctrine.orm.%s_memcache_instance', $entityManager['name']))));
        } else {
            $cacheDef = new Definition('%'.sprintf('doctrine.orm.cache.%s_class', $type).'%');
        }
        return $cacheDef;
    }

    /**
     * Finds existing bundle subpaths.
     *
     * @param string $path A subpath to check for
     * @param ContainerBuilder $container A ContainerBuilder configuration
     *
     * @return array An array of absolute directory paths
     */
    protected function findBundleSubpaths($path, ContainerBuilder $container)
    {
        $dirs = array();
        foreach ($container->getParameter('kernel.bundles') as $bundle) {
            $reflection = new \ReflectionClass($bundle);
            if (is_dir($dir = dirname($reflection->getFilename()).'/'.$path)) {
                $dirs[] = $dir;
                $container->addResource(new FileResource($dir));
            } else {
                // add the closest existing parent directory as a file resource
                do {
                    $dir = dirname($dir);
                } while (!is_dir($dir));
                $container->addResource(new FileResource($dir));
            }
        }
        return $dirs;
    }

    /**
     * Detects what metadata driver to use for the supplied directory.
     *
     * @param string $dir A directory path
     * @param ContainerBuilder $container A ContainerBuilder configuration
     *
     * @return string|null A metadata driver short name, if one can be detected
     */
    static protected function detectMetadataDriver($dir, ContainerBuilder $container)
    {
        // add the closest existing directory as a resource
        $resource = $dir.'/Resources/config/doctrine/metadata';
        while (!is_dir($resource)) {
            $resource = dirname($resource);
        }
        $container->addResource(new FileResource($resource));

        if (count(glob($dir.'/Resources/config/doctrine/metadata/*.xml'))) {
            return 'xml';
        } elseif (count(glob($dir.'/Resources/config/doctrine/metadata/*.yml'))) {
            return 'yml';
        }

        // add the directory itself as a resource
        $container->addResource(new FileResource($dir));

        if (is_dir($dir.'/Entity')) {
            return 'annotation';
        }
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