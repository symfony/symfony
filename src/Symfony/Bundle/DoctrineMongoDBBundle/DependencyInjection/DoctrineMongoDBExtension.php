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
    protected $resources = array(
        'mongodb' => 'mongodb.xml',
    );

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
            $loader->load($this->resources['mongodb']);
        }

        // Allow these application configuration options to override the defaults
        $options = array(
            'default_document_manager',
            'default_connection',
            'cache_driver',
            'metadata_cache_driver',
            'proxy_namespace',
            'auto_generate_proxy_classes',
            'default_database',
        );
        foreach ($options as $key) {
            if (isset($config[$key])) {
                $container->setParameter('doctrine.odm.mongodb.'.$key, $config[$key]);
            }
        }
        $container->setParameter('doctrine.odm.mongodb.mapping_dirs', $this->findBundleSubpaths('Resources/config/doctrine/metadata', $container));
        $container->setParameter('doctrine.odm.mongodb.document_dirs', $this->findBundleSubpaths('Document', $container));
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

        $odmConfigDef = new Definition('%doctrine.odm.mongodb.configuration_class%');
        $container->setDefinition(sprintf('doctrine.odm.mongodb.%s_configuration', $documentManager['name']), $odmConfigDef);

        $this->loadDocumentManagerBundlesMappingInformation($documentManager, $odmConfigDef, $container);
        $this->loadDocumentManagerMetadataCacheDriver($documentManager, $container);

        $methods = array(
            'setMetadataCacheImpl' => new Reference(sprintf('doctrine.odm.mongodb.%s_metadata_cache', $documentManager['name'])),
            'setMetadataDriverImpl' => new Reference('doctrine.odm.mongodb.metadata'),
            'setProxyDir' => $proxyCacheDir,
            'setProxyNamespace' => $container->getParameter('doctrine.odm.mongodb.proxy_namespace'),
            'setAutoGenerateProxyClasses' => $container->getParameter('doctrine.odm.mongodb.auto_generate_proxy_classes'),
            'setDefaultDB' => $defaultDatabase,
            'setLoggerCallable' => array(new Reference('doctrine.odm.mongodb.logger'), 'logQuery'),
        );
        foreach ($methods as $method => $arg) {
            $odmConfigDef->addMethodCall($method, array($arg));
        }

        $odmDmArgs = array(
            new Reference(sprintf('doctrine.odm.mongodb.%s_connection', isset($documentManager['connection']) ? $documentManager['connection'] : $documentManager['name'])),
            new Reference(sprintf('doctrine.odm.mongodb.%s_configuration', $documentManager['name']))
        );
        $odmDmDef = new Definition('%doctrine.odm.mongodb.document_manager_class%', $odmDmArgs);
        $odmDmDef->setFactoryMethod('create');
        $container->setDefinition(sprintf('doctrine.odm.mongodb.%s_document_manager', $documentManager['name']), $odmDmDef );

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
        if (isset($config['document_managers'])) {
            $configDocumentManagers = $config['document_managers'];
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
     * Loads a document managers bundles mapping information configuration.
     *
     * @param array $config An array of configuration settings
     * @param ContainerBuilder $container A ContainerBuilder instance
     */
    protected function loadDocumentManagerBundlesMappingInformation(array $documentManager, Definition $odmConfigDef, ContainerBuilder $container)
    {
        // configure metadata driver for each bundle based on the type of mapping files found
        $mappingDriverDef = new Definition('%doctrine.odm.mongodb.metadata.driver_chain_class%');
        $bundleDocumentMappings = array();
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

            if (is_dir($dir = $bundleDirs[$namespace].'/'.$class.'/Document')) {
                if ($type === null) {
                    $type = 'annotation';
                }
                $aliasMap[$class] = $namespace.'\\'.$class.'\\Document';
            }

            if ($type !== null) {
                $mappingDriverDef->addMethodCall('addDriver', array(
                        new Reference(sprintf('doctrine.odm.mongodb.metadata.%s', $type)),
                        $namespace.'\\'.$class.'\\Document'
                    )
                );
            }
        }
        $odmConfigDef->addMethodCall('setDocumentNamespaces', array($aliasMap));

        $container->setDefinition('doctrine.odm.mongodb.metadata', $mappingDriverDef);
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
        $dmMetadataCacheDriver = isset($documentManager['metadata_cache_driver']) ? $documentManager['metadata_cache_driver'] : $metadataCacheDriver;
        $type = is_array($dmMetadataCacheDriver) && isset($dmMetadataCacheDriver['type']) ? $dmMetadataCacheDriver['type'] : $dmMetadataCacheDriver;

        if ($type === 'memcache') {
            $memcacheClass = isset($dmMetadataCacheDriver['class']) ? $dmMetadataCacheDriver['class'] : sprintf('%%doctrine.odm.mongodb.cache.%s_class%%', $type);
            $cacheDef = new Definition($memcacheClass);
            $memcacheHost = isset($dmMetadataCacheDriver['host']) ? $dmMetadataCacheDriver['host'] : '%doctrine.odm.mongodb.cache.memcache_host%';
            $memcachePort = isset($dmMetadataCacheDriver['port']) ? $dmMetadataCacheDriver['port'] : '%doctrine.odm.mongodb.cache.memcache_port%';
            $memcacheInstanceClass = isset($dmMetadataCacheDriver['instance_class']) ? $dmMetadataCacheDriver['instance_class'] : '%doctrine.odm.mongodb.cache.memcache_instance_class%';
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
                isset($connection['options']) ? $connection['options'] : array()
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
