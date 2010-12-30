<?php

namespace Symfony\Bundle\DoctrineMongoDBBundle\DependencyInjection;

use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Resource\FileResource;

/**
 * Doctrine MongoDB ODM extension.
 *
 * @author Bulat Shakirzyanov <bulat@theopenskyproject.com>
 * @author Kris Wallsmith <kris.wallsmith@symfony-project.com>
 * @author Jonathan H. Wage <jonwage@gmail.com>
 */
class DoctrineMongoDBExtension extends Extension
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
     * Loads the MongoDB ODM configuration.
     *
     * Usage example:
     *
     *     <doctrine:mongodb server="mongodb://localhost:27017" />
     *
     * @param array $config An array of configuration settings
     * @param ContainerBuilder $container A ContainerBuilder instance
     */
    public function mongodbLoad($config, ContainerBuilder $container)
    {
        $this->createProxyDirectory($container->getParameter('kernel.cache_dir'));
        $this->createHydratorDirectory($container->getParameter('kernel.cache_dir'));
        $this->loadDefaults($config, $container);
        $this->loadConnections($config, $container);
        $this->loadDocumentManagers($config, $container);
    }

    /**
     * Create the Doctrine MongoDB ODM Document proxy directory
     */
    protected function createProxyDirectory($tmpDir)
    {
        // Create document proxy directory
        $proxyCacheDir = $tmpDir.'/doctrine/odm/mongodb/Proxies';
        if (!is_dir($proxyCacheDir)) {
            if (false === @mkdir($proxyCacheDir, 0777, true)) {
                die(sprintf('Unable to create the Doctrine Proxy directory (%s)', dirname($proxyCacheDir)));
            }
        } elseif (!is_writable($proxyCacheDir)) {
            die(sprintf('Unable to write in the Doctrine Proxy directory (%s)', $proxyCacheDir));
        }
    }

    /**
     * Create the Doctrine MongoDB ODM Document hydrator directory
     */
    protected function createHydratorDirectory($tmpDir)
    {
        // Create document hydrator directory
        $hydratorCacheDir = $tmpDir.'/doctrine/odm/mongodb/Hydrators';
        if (!is_dir($hydratorCacheDir)) {
            if (false === @mkdir($hydratorCacheDir, 0777, true)) {
                die(sprintf('Unable to create the Doctrine Hydrator directory (%s)', dirname($hydratorCacheDir)));
            }
        } elseif (!is_writable($hydratorCacheDir)) {
            die(sprintf('Unable to write in the Doctrine Hydrator directory (%s)', $hydratorCacheDir));
        }
    }

    /**
     * Loads the default configuration.
     *
     * @param array $config An array of configuration settings
     * @param ContainerBuilder $container A ContainerBuilder instance
     */
    protected function loadDefaults(array $config, ContainerBuilder $container)
    {
        if (!$container->hasDefinition('doctrine.odm.mongodb.metadata.annotation')) {
            // Load DoctrineMongoDBBundle/Resources/config/mongodb.xml
            $loader = new XmlFileLoader($container, __DIR__.'/../Resources/config');
            $loader->load('mongodb.xml');
        }

        // Allow these application configuration options to override the defaults
        $options = array(
            'default_document_manager',
            'default_connection',
            'cache_driver',
            'metadata_cache_driver',
            'proxy_namespace',
            'auto_generate_proxy_classes',
            'hydrator_namespace',
            'auto_generate_hydrator_classes',
            'default_database',
        );
        foreach ($options as $key) {
            if (isset($config[$key])) {
                $container->setParameter('doctrine.odm.mongodb.'.$key, $config[$key]);
            }

            $nKey = str_replace('_', '-', $key);
            if (isset($config[$nKey])) {
                $container->setParameter('doctrine.odm.mongodb.'.$key, $config[$nKey]);
            }
        }
    }

    /**
     * Loads the document managers configuration.
     *
     * @param array $config An array of configuration settings
     * @param ContainerBuilder $container A ContainerBuilder instance
     */
    protected function loadDocumentManagers(array $config, ContainerBuilder $container)
    {
        $documentManagers = $this->getDocumentManagers($config, $container);
        foreach ($documentManagers as $name => $documentManager) {
            $documentManager['name'] = $name;
            $this->loadDocumentManager($documentManager, $container);
        }
    }

    /**
     * Loads a document manager configuration.
     *
     * @param array $documentManager        A document manager configuration array
     * @param ContainerBuilder $container A ContainerBuilder instance
     */
    protected function loadDocumentManager(array $documentManager, ContainerBuilder $container)
    {
        $defaultDocumentManager = $container->getParameter('doctrine.odm.mongodb.default_document_manager');
        $defaultDatabase = isset($documentManager['default_database']) ? $documentManager['default_database'] : $container->getParameter('doctrine.odm.mongodb.default_database');
        $proxyCacheDir = $container->getParameter('kernel.cache_dir').'/doctrine/odm/mongodb/Proxies';
        $hydratorCacheDir = $container->getParameter('kernel.cache_dir').'/doctrine/odm/mongodb/Hydrators';

        $odmConfigDef = new Definition('%doctrine.odm.mongodb.configuration_class%');
        $container->setDefinition(sprintf('doctrine.odm.mongodb.%s_configuration', $documentManager['name']), $odmConfigDef);

        $this->loadDocumentManagerBundlesMappingInformation($documentManager, $odmConfigDef, $container);
        $this->loadDocumentManagerMetadataCacheDriver($documentManager, $container);

        $methods = array(
            'setMetadataCacheImpl' => new Reference(sprintf('doctrine.odm.mongodb.%s_metadata_cache', $documentManager['name'])),
            'setMetadataDriverImpl' => new Reference(sprintf('doctrine.odm.mongodb.%s_metadata_driver', $documentManager['name'])),
            'setProxyDir' => $proxyCacheDir,
            'setProxyNamespace' => $container->getParameter('doctrine.odm.mongodb.proxy_namespace'),
            'setAutoGenerateProxyClasses' => $container->getParameter('doctrine.odm.mongodb.auto_generate_proxy_classes'),
            'setHydratorDir' => $hydratorCacheDir,
            'setHydratorNamespace' => $container->getParameter('doctrine.odm.mongodb.hydrator_namespace'),
            'setAutoGenerateHydratorClasses' => $container->getParameter('doctrine.odm.mongodb.auto_generate_hydrator_classes'),
            'setDefaultDB' => $defaultDatabase,
            'setLoggerCallable' => array(new Reference('doctrine.odm.mongodb.logger'), 'logQuery'),
        );
        foreach ($methods as $method => $arg) {
            $odmConfigDef->addMethodCall($method, array($arg));
        }

        // event manager
        $eventManagerName = isset($documentManager['event_manager']) ? $documentManager['event_manager'] : $documentManager['name'];
        $eventManagerId = sprintf('doctrine.odm.mongodb.%s_event_manager', $eventManagerName);
        if (!$container->hasDefinition($eventManagerId)) {
            $eventManagerDef = new Definition('%doctrine.odm.mongodb.event_manager_class%');
            $eventManagerDef->addMethodCall('loadTaggedEventListeners', array(
                new Reference('service_container'),
            ));
            $eventManagerDef->addMethodCall('loadTaggedEventListeners', array(
                new Reference('service_container'),
                sprintf('doctrine.odm.mongodb.%s_event_listener', $eventManagerName),
            ));
            $eventManagerDef->addMethodCall('loadTaggedEventSubscribers', array(
                new Reference('service_container'),
            ));
            $eventManagerDef->addMethodCall('loadTaggedEventSubscribers', array(
                new Reference('service_container'),
                sprintf('doctrine.odm.mongodb.%s_event_subscriber', $eventManagerName),
            ));
            $container->setDefinition($eventManagerId, $eventManagerDef);
        }

        $odmDmArgs = array(
            new Reference(sprintf('doctrine.odm.mongodb.%s_connection', isset($documentManager['connection']) ? $documentManager['connection'] : $documentManager['name'])),
            new Reference(sprintf('doctrine.odm.mongodb.%s_configuration', $documentManager['name'])),
            new Reference($eventManagerId),
        );
        $odmDmDef = new Definition('%doctrine.odm.mongodb.document_manager_class%', $odmDmArgs);
        $odmDmDef->setFactoryMethod('create');
        $odmDmDef->addTag('doctrine.odm.mongodb.document_manager');
        $container->setDefinition(sprintf('doctrine.odm.mongodb.%s_document_manager', $documentManager['name']), $odmDmDef);

        if ($documentManager['name'] == $defaultDocumentManager) {
            $container->setAlias(
                'doctrine.odm.mongodb.document_manager',
                sprintf('doctrine.odm.mongodb.%s_document_manager', $documentManager['name'])
            );
        }
    }

    /**
     * Gets the configured document managers.
     *
     * @param array $config An array of configuration settings
     * @param ContainerBuilder $container A ContainerBuilder instance
     */
    protected function getDocumentManagers(array $config, ContainerBuilder $container)
    {
        $defaultDocumentManager = $container->getParameter('doctrine.odm.mongodb.default_document_manager');

        $documentManagers = array();

        if (isset($config['document-managers'])) {
            $config['document_managers'] = $config['document-managers'];
        }

        if (isset($config['document_managers'])) {
            $configDocumentManagers = $config['document_managers'];

            if (isset($config['document_managers']['document-manager'])) {
                $config['document_managers']['document_manager'] = $config['document_managers']['document-manager'];
            }

            if (isset($config['document_managers']['document_manager']) && isset($config['document_managers']['document_manager'][0])) {
                // Multiple document managers
                $configDocumentManagers = $config['document_managers']['document_manager'];
            }
            foreach ($configDocumentManagers as $name => $documentManager) {
                $documentManagers[isset($documentManager['id']) ? $documentManager['id'] : $name] = $documentManager;
            }
        } else {
            $documentManagers = array($defaultDocumentManager => $config);
        }
        return $documentManagers;
    }

    /**
     * Loads the configured document manager metadata cache driver.
     *
     * @param array $config        A configured document manager array
     * @param ContainerBuilder $container A ContainerBuilder instance
     */
    protected function loadDocumentManagerMetadataCacheDriver(array $documentManager, ContainerBuilder $container)
    {
        $metadataCacheDriver = $container->getParameter('doctrine.odm.mongodb.metadata_cache_driver');
        $dmMetadataCacheDriver = isset($documentManager['metadata-cache-driver']) ? $documentManager['metadata-cache-driver'] : (isset($documentManager['metadata_cache_driver']) ? $documentManager['metadata_cache_driver'] : $metadataCacheDriver);
        $type = is_array($dmMetadataCacheDriver) && isset($dmMetadataCacheDriver['type']) ? $dmMetadataCacheDriver['type'] : $dmMetadataCacheDriver;

        if ('memcache' === $type) {
            $memcacheClass = isset($dmMetadataCacheDriver['class']) ? $dmMetadataCacheDriver['class'] : sprintf('%%doctrine.odm.mongodb.cache.%s_class%%', $type);
            $cacheDef = new Definition($memcacheClass);
            $memcacheHost = isset($dmMetadataCacheDriver['host']) ? $dmMetadataCacheDriver['host'] : '%doctrine.odm.mongodb.cache.memcache_host%';
            $memcachePort = isset($dmMetadataCacheDriver['port']) ? $dmMetadataCacheDriver['port'] : '%doctrine.odm.mongodb.cache.memcache_port%';
            $memcacheInstanceClass = isset($dmMetadataCacheDriver['instance-class']) ? $dmMetadataCacheDriver['instance-class'] : (isset($dmMetadataCacheDriver['instance_class']) ? $dmMetadataCacheDriver['instance_class'] : '%doctrine.odm.mongodb.cache.memcache_instance_class%');
            $memcacheInstance = new Definition($memcacheInstanceClass);
            $memcacheInstance->addMethodCall('connect', array($memcacheHost, $memcachePort));
            $container->setDefinition(sprintf('doctrine.odm.mongodb.%s_memcache_instance', $documentManager['name']), $memcacheInstance);
            $cacheDef->addMethodCall('setMemcache', array(new Reference(sprintf('doctrine.odm.mongodb.%s_memcache_instance', $documentManager['name']))));
        } else {
             $cacheDef = new Definition(sprintf('%%doctrine.odm.mongodb.cache.%s_class%%', $type));
        }
        $container->setDefinition(sprintf('doctrine.odm.mongodb.%s_metadata_cache', $documentManager['name']), $cacheDef);
    }

    /**
     * Loads the configured connections.
     *
     * @param array $config An array of configuration settings
     * @param ContainerBuilder $container A ContainerBuilder instance
     */
    protected function loadConnections(array $config, ContainerBuilder $container)
    {
        $connections = $this->getConnections($config, $container);
        foreach ($connections as $name => $connection) {
            $odmConnArgs = array(
                isset($connection['server']) ? $connection['server'] : null,
                isset($connection['options']) ? $connection['options'] : array(),
                new Reference(sprintf('doctrine.odm.mongodb.%s_configuration', $name))
            );
            $odmConnDef = new Definition('%doctrine.odm.mongodb.connection_class%', $odmConnArgs);
            $container->setDefinition(sprintf('doctrine.odm.mongodb.%s_connection', $name), $odmConnDef);
        }
    }

    /**
     * Gets the configured connections.
     *
     * @param array $config An array of configuration settings
     * @param ContainerBuilder $container A ContainerBuilder instance
     */
    protected function getConnections(array $config, ContainerBuilder $container)
    {
        $defaultConnection = $container->getParameter('doctrine.odm.mongodb.default_connection');

        $connections = array();
        if (isset($config['connections'])) {
            $configConnections = $config['connections'];
            if (isset($config['connections']['connection']) && isset($config['connections']['connection'][0])) {
                // Multiple connections
                $configConnections = $config['connections']['connection'];
            }
            foreach ($configConnections as $name => $connection) {
                $connections[isset($connection['id']) ? $connection['id'] : $name] = $connection;
            }
        } else {
            $connections = array($defaultConnection => $config);
        }
        return $connections;
    }


    /**
     * Loads an ODM document managers bundle mapping information.
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
     *         MyBundle3: { type: annotation, dir: Documents/ }
     *         MyBundle4: { type: xml, dir: Resources/config/doctrine/mapping }
     *         MyBundle5:
     *             type: yml
     *             dir: [bundle-mappings1/, bundle-mappings2/]
     *             alias: BundleAlias
     *         arbitrary_key:
     *             type: xml
     *             dir: %kernel.dir%/../src/vendor/DoctrineExtensions/lib/DoctrineExtensions/Documents
     *             prefix: DoctrineExtensions\Documents\
     *             alias: DExt
     *
     * In the case of bundles everything is really optional (which leads to autodetection for this bundle) but
     * in the mappings key everything except alias is a required argument.
     *
     * @param array $entityManager A configured ORM entity manager.
     * @param ContainerBuilder $container A ContainerBuilder instance
     */
    protected function loadDocumentManagerBundlesMappingInformation(array $documentManager, Definition $odmConfigDef, ContainerBuilder $container)
    {
        // reset state of drivers and alias map. They are only used by this methods and children.
        $this->drivers = array();
        $this->aliasMap = array();

        $this->loadMappingInformation($documentManager, $container);
        $this->registerMappingDrivers($documentManager, $container);
        
        $odmConfigDef->addMethodCall('setDocumentNamespaces', array($this->aliasMap));
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
        $chainDriverDef = new Definition('%'.$this->getObjetManagerElementName('metadata.driver_chain_class%'));
        foreach ($this->drivers as $driverType => $driverPaths) {
            if ($driverType == 'annotation') {
                $mappingDriverDef = new Definition('%'.$this->getObjetManagerElementName('metadata.' . $driverType . '_class%'), array(
                    new Reference($this->getObjetManagerElementName('metadata.annotation_reader')),
                    array_values($driverPaths)
                ));
            } else {
                $mappingDriverDef = new Definition('%'.$this->getObjetManagerElementName('metadata.' . $driverType . '_class%'), array(
                    array_values($driverPaths)
                ));
            }
            $mappingService = $this->getObjetManagerElementName($objectManager['name'] . '_'.$driverType.'_metadata_driver');
            $container->setDefinition($mappingService, $mappingDriverDef);

            foreach ($driverPaths as $prefix => $driverPath) {
                $chainDriverDef->addMethodCall('addDriver', array(new Reference($mappingService), $prefix));
            }
        }

        $container->setDefinition($this->getObjetManagerElementName($objectManager['name'] . '_metadata_driver'), $chainDriverDef);
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
                "'" . $this->getObjetManagerElementName($objectManagerName . ".metadata_driver")."' service definition."
            );
        }
    }

    protected function getObjetManagerElementName($name)
    {
        return 'doctrine.odm.mongodb.' . $name;
    }

    protected function getMappingObjectDefaultName()
    {
        return 'Document';
    }

    protected function getMappingResourceConfigDirectory()
    {
        return 'Resources/config/doctrine/metadata/mongodb';
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
        $resource = $dir.'/Resources/config/doctrine/metadata/mongodb';
        while (!is_dir($resource)) {
            $resource = dirname($resource);
        }
        $container->addResource(new FileResource($resource));

        if (count(glob($dir.'/Resources/config/doctrine/metadata/mongodb/*.xml'))) {
            return 'xml';
        } elseif (count(glob($dir.'/Resources/config/doctrine/metadata/mongodb/*.yml'))) {
            return 'yml';
        }

        // add the directory itself as a resource
        $container->addResource(new FileResource($dir));

        if (is_dir($dir.'/Document')) {
            return 'annotation';
        }
    }

    /**
     * Returns the namespace to be used for this extension (XML namespace).
     *
     * @return string The XML namespace
     */
    public function getNamespace()
    {
        return 'http://www.symfony-project.org/schema/dic/doctrine/odm/mongodb';
    }

    /**
     * @return string
     */
    public function getXsdValidationBasePath()
    {
        return __DIR__.'/../Resources/config/schema';
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
        return 'doctrine_odm';
    }
}
