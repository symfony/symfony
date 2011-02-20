<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\DoctrineMongoDBBundle\DependencyInjection;

use Symfony\Component\HttpKernel\DependencyInjection\Extension;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Alias;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\Config\Resource\FileResource;
use Symfony\Component\Config\FileLocator;
use Symfony\Bundle\DoctrineAbstractBundle\DependencyInjection\AbstractDoctrineExtension;

/**
 * Doctrine MongoDB ODM extension.
 *
 * @author Bulat Shakirzyanov <bulat@theopenskyproject.com>
 * @author Kris Wallsmith <kris.wallsmith@symfony-project.com>
 * @author Jonathan H. Wage <jonwage@gmail.com>
 */
class DoctrineMongoDBExtension extends AbstractDoctrineExtension
{
    public function load(array $configs, ContainerBuilder $container)
    {
        foreach ($configs as $config) {
            $this->doMongodbLoad($config, $container);
        }
    }

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
    protected function doMongodbLoad($config, ContainerBuilder $container)
    {
        $this->loadDefaults($config, $container);
        $this->loadConnections($config, $container);
        $this->loadDocumentManagers($config, $container);
        $this->loadConstraints($config, $container);
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
            $loader = new XmlFileLoader($container, new FileLocator(__DIR__.'/../Resources/config'));
            $loader->load('mongodb.xml');
        }

        // Allow these application configuration options to override the defaults
        $options = array(
            'default_document_manager',
            'default_connection',
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
        $container->setParameter('doctrine.odm.mongodb.document_managers', array_keys($documentManagers));
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
        $configServiceName = sprintf('doctrine.odm.mongodb.%s_configuration', $documentManager['name']);

        if ($container->hasDefinition($configServiceName)) {
            $odmConfigDef = $container->getDefinition($configServiceName);
        } else {
            $odmConfigDef = new Definition('%doctrine.odm.mongodb.configuration_class%');
            $container->setDefinition($configServiceName, $odmConfigDef);
        }

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
            if ($odmConfigDef->hasMethodCall($method)) {
                $odmConfigDef->removeMethodCall($method);
            }
            $odmConfigDef->addMethodCall($method, array($arg));
        }

        // event manager
        $eventManagerName = isset($documentManager['event_manager']) ? $documentManager['event_manager'] : $documentManager['name'];
        $eventManagerId = sprintf('doctrine.odm.mongodb.%s_event_manager', $eventManagerName);
        if (!$container->hasDefinition($eventManagerId)) {
            $eventManagerDef = new Definition('%doctrine.odm.mongodb.event_manager_class%');
            $eventManagerDef->addTag('doctrine.odm.mongodb.event_manager');
            $eventManagerDef->setPublic(false);
            $container->setDefinition($eventManagerId, $eventManagerDef);
        }

        $odmDmArgs = array(
            new Reference(sprintf('doctrine.odm.mongodb.%s_connection', isset($documentManager['connection']) ? $documentManager['connection'] : $documentManager['name'])),
            new Reference(sprintf('doctrine.odm.mongodb.%s_configuration', $documentManager['name'])),
            new Reference($eventManagerId),
        );
        $odmDmDef = new Definition('%doctrine.odm.mongodb.document_manager_class%', $odmDmArgs);
        $odmDmDef->setFactoryClass('%doctrine.odm.mongodb.document_manager_class%');
        $odmDmDef->setFactoryMethod('create');
        $odmDmDef->addTag('doctrine.odm.mongodb.document_manager');
        $container->setDefinition(sprintf('doctrine.odm.mongodb.%s_document_manager', $documentManager['name']), $odmDmDef);

        if ($documentManager['name'] == $defaultDocumentManager) {
            $container->setAlias(
                'doctrine.odm.mongodb.document_manager',
                new Alias(sprintf('doctrine.odm.mongodb.%s_document_manager', $documentManager['name']))
            );
            $container->setAlias(
                'doctrine.odm.mongodb.event_manager',
                new Alias(sprintf('doctrine.odm.mongodb.%s_event_manager', $documentManager['name']))
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
     * @param array $documentManager A configured ODM entity manager.
     * @param Definition A Definition instance
     * @param ContainerBuilder $container A ContainerBuilder instance
     */
    protected function loadDocumentManagerBundlesMappingInformation(array $documentManager, Definition $odmConfigDef, ContainerBuilder $container)
    {
        // reset state of drivers and alias map. They are only used by this methods and children.
        $this->drivers = array();
        $this->aliasMap = array();

        $this->loadMappingInformation($documentManager, $container);
        $this->registerMappingDrivers($documentManager, $container);

        if ($odmConfigDef->hasMethodCall('setDocumentNamespaces')) {
            // TODO: Can we make a method out of it on Definition? replaceMethodArguments() or something.
            $calls = $odmConfigDef->getMethodCalls();
            foreach ($calls AS $call) {
                if ($call[0] == 'setDocumentNamespaces') {
                    $this->aliasMap = array_merge($call[1][0], $this->aliasMap);
                }
            }
            $method = $odmConfigDef->removeMethodCall('setDocumentNamespaces');
        }
        $odmConfigDef->addMethodCall('setDocumentNamespaces', array($this->aliasMap));
    }

    protected function loadConstraints($config, ContainerBuilder $container)
    {
        if ($container->hasParameter('validator.annotations.namespaces')) {
            $container->setParameter('validator.annotations.namespaces', array_merge(
                $container->getParameter('validator.annotations.namespaces'),
                array('mongodb' => 'Symfony\Bundle\DoctrineMongoDBBundle\Validator\Constraints\\')
            ));
        }
    }

    protected function getObjectManagerElementName($name)
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
}
