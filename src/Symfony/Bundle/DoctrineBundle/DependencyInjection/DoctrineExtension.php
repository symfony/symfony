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
 * @author Jonathan H. Wage <jonwage@gmail.com>
 * @author Fabien Potencier <fabien.potencier@symfony-project.com>
 */
class DoctrineExtension extends Extension
{
    /**
     * Used inside metadata driver method to simplify aggregation of data.
     * 
     * @var array
     */
    private $aliasMap = array();

    /**
     * Used inside metadata driver method to simplify aggregation of data.
     *
     * @var array
     */
    private $drivers = array();

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
        $this->loadOrmDefaults($config, $container);
        $this->createOrmProxyDirectory($container);
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
        // arbitrary service that is always part of the "dbal" services. Its used to check if the
        // defaults have to applied (first time run) or ignored (second or n-th run due to imports)
        if (!$container->hasDefinition('doctrine.dbal.logger')) {
            $loader = new XmlFileLoader($container, __DIR__.'/../Resources/config');
            $loader->load('dbal.xml');
        }

        $defaultConnectionName = isset($config['default-connection']) ? $config['default-connection'] : (isset($config['default_connection']) ? $config['default_connection'] : $container->getParameter('doctrine.dbal.default_connection'));
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
            $containerClass = isset($connection['configuration-class']) ? $connection['configuration-class'] : (isset($connection['configuration_class']) ? $connection['configuration_class'] : 'Doctrine\DBAL\Configuration');
            $containerDef = new Definition($containerClass);
            $containerDef->addMethodCall('setSqlLogger', array(new Reference('doctrine.dbal.logger')));
            $container->setDefinition(sprintf('doctrine.dbal.%s_connection.configuration', $connection['name']), $containerDef);

            $eventManagerDef = new Definition(isset($connection['event-manager-class']) ? $connection['event-manager-class'] : $connection['event_manager_class']);
            $container->setDefinition(sprintf('doctrine.dbal.%s_connection.event_manager', $connection['name']), $eventManagerDef);

            $driverOptions = array();
            $driverDef = new Definition('Doctrine\DBAL\DriverManager');
            $driverDef->setFactoryMethod('getConnection');
            $container->setDefinition(sprintf('doctrine.dbal.%s_connection', $connection['name']), $driverDef);
        }

        if (isset($connection['driver'])) {
            $driverOptions['driverClass'] = sprintf('Doctrine\\DBAL\\Driver\\%s\\Driver', $connection['driver']);
        }
        if (isset($connection['wrapper-class'])) {
            $driverOptions['wrapperClass'] = $connection['wrapper-class'];
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

            $nKey = str_replace('_', '-', $key);
            if (isset($connection[$nKey])) {
                $driverOptions[$key] = $connection[$nKey];
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
    protected function createOrmProxyDirectory(ContainerBuilder $container)
    {
        $proxyCacheDir = $container->getParameterBag()->resolveValue($container->getParameter('doctrine.orm.proxy_dir'));
        // Create entity proxy directory
        if (!is_dir($proxyCacheDir)) {
            if (false === @mkdir($proxyCacheDir, 0777, true)) {
                throw new \RuntimeException(sprintf('Unable to create the Doctrine Proxy directory (%s)', dirname($proxyCacheDir)));
            }
        } elseif (!is_writable($proxyCacheDir) && $container->getParameter('auto_generate_proxy_classes') == true) {
            throw new \RuntimeException(sprintf('Unable to write in the Doctrine Proxy directory (%s)', $proxyCacheDir));
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
        // arbitrary service that is always part of the "orm" services. Its used to check if the
        // defaults have to applied (first time run) or ignored (second or n-th run due to imports)
        if (!$container->hasDefinition('doctrine.orm.metadata_driver.annotation.reader')) {
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
            'auto_generate_proxy_classes'
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
            'setAutoGenerateProxyClasses'   => $container->getParameter('doctrine.orm.auto_generate_proxy_classes')
        );
        foreach ($uniqueMethods as $method => $arg) {
            if ($ormConfigDef->hasMethodCall($method)) {
                $ormConfigDef->removeMethodCall($method);
            }
            $ormConfigDef->addMethodCall($method, array($arg));
        }

        $entityManagerService = sprintf('doctrine.orm.%s_entity_manager', $entityManager['name']);

        if (!$container->hasDefinition($entityManagerService) || isset($entityManager['connection'])) {
            $ormEmArgs = array(
                new Reference(sprintf('doctrine.dbal.%s_connection', isset($entityManager['connection']) ? $entityManager['connection'] : $entityManager['name'])),
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

    /*
     * @param array $entityManager A configured ORM entity manager.
     * @param ContainerBuilder $container A ContainerBuilder instance
     */
    protected function loadMappingInformation(array $objectManager, $container)
    {
        if (isset($objectManager['mappings'])) {
            // fix inconsistency between yaml and xml naming
            if (isset($objectManager['mappings']['mapping'])) {
                if (isset($objectManager['mappings']['mapping'][0])) {
                    $objectManager['mappings'] = $objectManager['mappings']['mapping'];
                } else {
                    $objectManager['mappings'] = array($objectManager['mappings']['mapping']);
                }
            }

            foreach ($objectManager['mappings'] as $mappingName => $mappingConfig) {
                if (is_string($mappingConfig)) {
                    $mappingConfig['type'] = $mappingConfig;
                }
                if (!isset($mappingConfig['dir'])) {
                    $mappingConfig['dir'] = false;
                }
                if (!isset($mappingConfig['type'])) {
                    $mappingConfig['type'] = false;
                }
                if (!isset($mappingConfig['prefix'])) {
                    $mappingConfig['prefix'] = false;
                }

                $mappingConfig['dir'] = $container->getParameterBag()->resolveValue($mappingConfig['dir']);
                // a bundle configuration is detected by realizing that the specified dir is not absolute and existing
                if (isset($mappingConfig['is-bundle'])) {
                    $mappingConfig['is_bundle'] = $mappingConfig['is-bundle'];
                }
                if (!isset($mappingConfig['is_bundle'])) {
                    $mappingConfig['is_bundle'] = !file_exists($mappingConfig['dir']);
                }

                if (isset($mappingConfig['name'])) {
                    $mappingName = $mappingConfig['name'];
                } else if ($mappingConfig === null) {
                    $mappingConfig = array();
                }

                if ($mappingConfig['is_bundle']) {
                    $namespace = $this->getBundleNamespace($mappingName, $container);
                    $mappingConfig = $this->getMappingDriverBundleConfigDefaults($mappingConfig, $namespace, $mappingName, $container);
                    if (!$mappingConfig) {
                        continue;
                    }
                }

                $this->assertValidMappingConfiguration($mappingConfig, $objectManager['name']);
                $this->setMappingDriverConfig($mappingConfig, $mappingName);
                $this->setMappingDriverAlias($mappingConfig, $mappingName);
            }
        }
    }

    /**
     * Register the alias for this mapping driver.
     *
     * Aliases can be used in the Query languages of all the Doctrine object managers to simplify writing tasks.
     *
     * @param array $mappingConfig
     * @param string $mappingName
     * @return void
     */
    protected function setMappingDriverAlias($mappingConfig, $mappingName)
    {
        if (isset($mappingConfig['alias'])) {
            $this->aliasMap[$mappingConfig['alias']] = $mappingConfig['prefix'];
        } else {
            $this->aliasMap[$mappingName] = $mappingConfig['prefix'];
        }
    }

    /**
     * Registter the mapping driver configuration for later use with the object managers metadata driver chain.
     *
     * @param array $mappingConfig
     * @param string $mappingName
     * @return void
     */
    protected function setMappingDriverConfig(array $mappingConfig, $mappingName)
    {
        if (is_dir($mappingConfig['dir'])) {
            if (!isset($this->drivers[$mappingConfig['type']])) {
                $this->drivers[$mappingConfig['type']] = array();
            }
            $this->drivers[$mappingConfig['type']][$mappingConfig['prefix']] = realpath($mappingConfig['dir']);
        } else {
            throw new \InvalidArgumentException("Invalid mapping path given. ".
                "Cannot load mapping/bundle named '" . $mappingName . "'.");
        }
    }

     /**
     * Finds the bundle directory for a namespace.
     *
     * If the namespace does not yield a direct match, this method will attempt
     * to match parent namespaces exhaustively.
     *
     * @param string           $namespace A bundle namespace omitting the bundle name part
     * @param ContainerBuilder $container A ContainerBuilder instance
     *
     * @return string|false The bundle directory if found, false otherwise
     */
    protected function findBundleDirForNamespace($namespace, ContainerBuilder $container)
    {
        $bundleDirs = $container->getParameter('kernel.bundle_dirs');

        $segment = $namespace;
        do {
            if (isset($bundleDirs[$segment])) {
                return $bundleDirs[$segment] . str_replace('\\', '/', substr($namespace, strlen($segment)));
            }
        } while ($segment = substr($segment, 0, ($pos = strrpos($segment, '\\'))));

        return false;
    }

    /**
     * Get the namespace a bundle resides into.
     *
     * @param string $bundleName
     * @param ContainerBuilder $container
     * @return string
     */
    private function getBundleNamespace($bundleName, $container)
    {
        foreach ($container->getParameter('kernel.bundles') AS $bundleClassName) {
            $tmp = dirname(str_replace('\\', '/', $bundleClassName));
            $namespace = str_replace('/', '\\', dirname($tmp));
            $actualBundleName = basename($tmp);

            if ($actualBundleName == $bundleName) {
                return $namespace;
            }
        }
        return null;
    }

    /**
     * If this is a bundle controlled mapping all the missing information can be autodetected by this method.
     *
     * Returns false when autodetection failed, an array of the completed information otherwise.
     *
     * @param array $bundleConfig
     * @param string $namespace
     * @param string $bundleName
     * @param Container $container
     * @return array|false
     */
    protected function getMappingDriverBundleConfigDefaults(array $bundleConfig, $namespace, $bundleName, $container)
    {
        $bundleDir = $this->findBundleDirForNamespace($namespace, $container);

        if (!$bundleDir) {
            // skip this bundle if we cannot find its location, it must be misspelled or something.
            return false;
        }

        if (!$bundleConfig['type']) {
            $bundleConfig['type'] = $this->detectMetadataDriver($bundleDir.'/'.$bundleName, $container);
        }
        if (!$bundleConfig['type']) {
            // skip this bundle, no mapping information was found.
            return false;
        }

        if (!$bundleConfig['dir']) {
            if (in_array($bundleConfig['type'], array('annotation', 'static-php'))) {
                $bundleConfig['dir'] = $bundleDir.'/'.$bundleName.'/' . $this->getMappingObjectDefaultName();
            } else {
                $bundleConfig['dir'] = $bundleDir.'/'.$bundleName.'/' . $this->getMappingResourceConfigDirectory();
            }
        } else {
            $bundleConfig['dir'] = $bundleDir.'/'.$bundleName.'/' . $bundleConfig['dir'];
        }
        
        if (!$bundleConfig['prefix']) {
            $bundleConfig['prefix'] = $namespace.'\\'. $bundleName . '\\' . $this->getMappingObjectDefaultName();
        }
        return $bundleConfig;
    }

    /**
     * Register all the collected mapping information with the object manager by registering the appropiate mapping drivers.
     *
     * @param array $objectManager
     * @param Container $container
     */
    protected function registerMappingDrivers($objectManager, $container)
    {
        // configure metadata driver for each bundle based on the type of mapping files found
        if ($container->hasDefinition($this->getObjectManagerElementName($objectManager['name'] . '_metadata_driver'))) {
            $chainDriverDef = $container->getDefinition($this->getObjectManagerElementName($objectManager['name'] . '_metadata_driver'));
        } else {
            $chainDriverDef = new Definition('%'.$this->getObjectManagerElementName('metadata.driver_chain_class%'));
        }

        foreach ($this->drivers as $driverType => $driverPaths) {
            $mappingService = $this->getObjectManagerElementName($objectManager['name'] . '_'.$driverType.'_metadata_driver');
            if ($container->hasDefinition($mappingService)) {
                $mappingDriverDef = $container->getDefinition($mappingService);
                $args = $mappingDriverDef->getArguments();
                if ($driverType == 'annotation') {
                    $args[1] = array_merge($driverPaths, $args[1]);
                } else {
                    $args[0] = array_merge($driverPaths, $args[0]);
                }
                $mappingDriverDef->setArguments($args);
            } else if ($driverType == 'annotation') {
                $mappingDriverDef = new Definition('%'.$this->getObjectManagerElementName('metadata.' . $driverType . '_class%'), array(
                    new Reference($this->getObjectManagerElementName('metadata_driver.annotation.reader')),
                    array_values($driverPaths)
                ));
            } else {
                $mappingDriverDef = new Definition('%'.$this->getObjectManagerElementName('metadata.' . $driverType . '_class%'), array(
                    array_values($driverPaths)
                ));
            }
            
            $container->setDefinition($mappingService, $mappingDriverDef);

            foreach ($driverPaths as $prefix => $driverPath) {
                $chainDriverDef->addMethodCall('addDriver', array(new Reference($mappingService), $prefix));
            }
        }

        $container->setDefinition($this->getObjectManagerElementName($objectManager['name'] . '_metadata_driver'), $chainDriverDef);
    }

    /**
     * Assertion if the specified mapping information is valid.
     * 
     * @param array $mappingConfig
     * @param string $objectManagerName
     */
    protected function assertValidMappingConfiguration(array $mappingConfig, $objectManagerName)
    {
        if (!$mappingConfig['type'] || !$mappingConfig['dir'] || !$mappingConfig['prefix']) {
            throw new \InvalidArgumentException("Mapping definitions for manager '".$objectManagerName."' ".
                "require at least the 'type', 'dir' and 'prefix' options.");
        }

        if (!in_array($mappingConfig['type'], array('xml', 'yml', 'annotation', 'php', 'staticphp'))) {
            throw new \InvalidArgumentException("Can only configure 'xml', 'yml', 'annotation', 'php' or ".
                "'static-php' through the DoctrineBundle. Use your own bundle to configure other metadata drivers. " .
                "You can register them by adding a a new driver to the ".
                "'" . $this->getObjectManagerElementName($objectManagerName . ".metadata_driver")."' service definition."
            );
        }
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
            $memcacheHost = is_array($cacheDriver) && isset($cacheDriver['host']) ? $cacheDriver['host'] : '%doctrine.orm.cache.memcache_host%';
            $memcachePort = is_array($cacheDriver) && isset($cacheDriver['port']) ? $cacheDriver['port'] : '%doctrine.orm.cache.memcache_port%';
            $memcacheInstanceClass = is_array($cacheDriver) && isset($cacheDriver['instance-class']) ? $cacheDriver['instance-class'] : (is_array($cacheDriver) && isset($cacheDriver['instance_class']) ? $cacheDriver['instance_class'] : '%doctrine.orm.cache.memcache_instance_class%');
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
        $resource = $dir.'/Resources/config/doctrine/metadata/orm';
        while (!is_dir($resource)) {
            $resource = dirname($resource);
        }
        $container->addResource(new FileResource($resource));

        if (count(glob($dir.'/Resources/config/doctrine/metadata/orm/*.xml'))) {
            return 'xml';
        } elseif (count(glob($dir.'/Resources/config/doctrine/metadata/orm/*.yml'))) {
            return 'yml';
        } elseif (count(glob($dir.'/Resources/config/doctrine/metadata/orm/*.php'))) {
            return 'php';
        }

        // add the directory itself as a resource
        $container->addResource(new FileResource($dir));

        if (is_dir($dir.'/Entity')) {
            return 'annotation';
        }

        return null;
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
