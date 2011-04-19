<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\DoctrineMongoDBBundle\DependencyInjection;

use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Alias;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\Config\Definition\Processor;
use Symfony\Bundle\DoctrineAbstractBundle\DependencyInjection\AbstractDoctrineExtension;

/**
 * Doctrine MongoDB ODM extension.
 *
 * @author Bulat Shakirzyanov <bulat@theopenskyproject.com>
 * @author Kris Wallsmith <kris.wallsmith@symfony.com>
 * @author Jonathan H. Wage <jonwage@gmail.com>
 */
class DoctrineMongoDBExtension extends AbstractDoctrineExtension
{
    /**
     * Responds to the doctrine_mongo_db configuration parameter.
     */
    public function load(array $configs, ContainerBuilder $container)
    {
        // Load DoctrineMongoDBBundle/Resources/config/mongodb.xml
        $loader = new XmlFileLoader($container, new FileLocator(__DIR__.'/../Resources/config'));
        $loader->load('mongodb.xml');

        $processor = new Processor();
        $configuration = new Configuration($container->getParameter('kernel.debug'));
        $config = $processor->processConfiguration($configuration, $configs);

        // can't currently default this correctly in Configuration
        if (!isset($config['metadata_cache_driver'])) {
            $config['metadata_cache_driver'] = array('type' => 'array');
        }

        if (empty ($config['default_connection'])) {
            $keys = array_keys($config['connections']);
            $config['default_connection'] = reset($keys);
        }

        if (empty ($config['default_document_manager'])) {
            $keys = array_keys($config['document_managers']);
            $config['default_document_manager'] = reset($keys);
        }

        // set some options as parameters and unset them
        $config = $this->overrideParameters($config, $container);

        // load the connections
        $this->loadConnections($config['connections'], $container);

        // load the document managers
        $this->loadDocumentManagers(
            $config['document_managers'],
            $config['default_document_manager'],
            $config['default_database'],
            $config['metadata_cache_driver'],
            $container
        );

        $this->loadConstraints($container);
    }

    /**
     * Uses some of the extension options to override DI extension parameters.
     *
     * @param array $options The available configuration options
     * @param ContainerBuilder $container A ContainerBuilder instance
     */
    protected function overrideParameters($options, ContainerBuilder $container)
    {
        $overrides = array(
            'proxy_namespace',
            'proxy_dir',
            'auto_generate_proxy_classes',
            'hydrator_namespace',
            'hydrator_dir',
            'auto_generate_hydrator_classes',
        );

        foreach ($overrides as $key) {
            if (isset($options[$key])) {
                $container->setParameter('doctrine.odm.mongodb.'.$key, $options[$key]);

                // the option should not be used, the parameter should be referenced
                unset($options[$key]);
            }
        }

        return $options;
    }

    /**
     * Loads the document managers configuration.
     *
     * @param array $dmConfigs An array of document manager configs
     * @param string $defaultDM The default document manager name
     * @param string $defaultDB The default db name
     * @param string $defaultMetadataCache The default metadata cache configuration
     * @param ContainerBuilder $container A ContainerBuilder instance
     */
    protected function loadDocumentManagers(array $dmConfigs, $defaultDM, $defaultDB, $defaultMetadataCache, ContainerBuilder $container)
    {
        foreach ($dmConfigs as $name => $documentManager) {
            $documentManager['name'] = $name;
            $this->loadDocumentManager(
                $documentManager,
                $defaultDM,
                $defaultDB,
                $defaultMetadataCache,
                $container
            );
        }
        $container->setParameter('doctrine.odm.mongodb.document_managers', array_keys($dmConfigs));
    }

    /**
     * Loads a document manager configuration.
     *
     * @param array $documentManager        A document manager configuration array
     * @param string $defaultDM The default document manager name
     * @param string $defaultDB The default db name
     * @param string $defaultMetadataCache The default metadata cache configuration
     * @param ContainerBuilder $container A ContainerBuilder instance
     */
    protected function loadDocumentManager(array $documentManager, $defaultDM, $defaultDB, $defaultMetadataCache, ContainerBuilder $container)
    {
        $defaultDatabase = isset($documentManager['default_database']) ? $documentManager['default_database'] : $defaultDB;
        $configServiceName = sprintf('doctrine.odm.mongodb.%s_configuration', $documentManager['name']);

        if ($container->hasDefinition($configServiceName)) {
            $odmConfigDef = $container->getDefinition($configServiceName);
        } else {
            $odmConfigDef = new Definition('%doctrine.odm.mongodb.configuration_class%');
            $container->setDefinition($configServiceName, $odmConfigDef);
        }

        $this->loadDocumentManagerBundlesMappingInformation($documentManager, $odmConfigDef, $container);
        $this->loadDocumentManagerMetadataCacheDriver($documentManager, $container, $defaultMetadataCache);

        $methods = array(
            'setMetadataCacheImpl' => new Reference(sprintf('doctrine.odm.mongodb.%s_metadata_cache', $documentManager['name'])),
            'setMetadataDriverImpl' => new Reference(sprintf('doctrine.odm.mongodb.%s_metadata_driver', $documentManager['name'])),
            'setProxyDir' => '%doctrine.odm.mongodb.proxy_dir%',
            'setProxyNamespace' => '%doctrine.odm.mongodb.proxy_namespace%',
            'setAutoGenerateProxyClasses' => '%doctrine.odm.mongodb.auto_generate_proxy_classes%',
            'setHydratorDir' => '%doctrine.odm.mongodb.hydrator_dir%',
            'setHydratorNamespace' => '%doctrine.odm.mongodb.hydrator_namespace%',
            'setAutoGenerateHydratorClasses' => '%doctrine.odm.mongodb.auto_generate_hydrator_classes%',
            'setDefaultDB' => $defaultDatabase,
        );

        if ($documentManager['logging']) {
            $methods['setLoggerCallable'] = array(new Reference('doctrine.odm.mongodb.logger'), 'logQuery');
        }

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

        if ($documentManager['name'] == $defaultDM) {
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
     * Loads the configured document manager metadata cache driver.
     *
     * @param array $config                 A configured document manager array
     * @param ContainerBuilder $container   A ContainerBuilder instance
     * @param array $defaultMetadataCache   The default metadata cache configuration array
     */
    protected function loadDocumentManagerMetadataCacheDriver(array $documentManager, ContainerBuilder $container, $defaultMetadataCache)
    {
        $dmMetadataCacheDriver = isset($documentManager['metadata_cache_driver']) ? $documentManager['metadata_cache_driver'] : $defaultMetadataCache;
        $type = $dmMetadataCacheDriver['type'];

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
     * @param array $config An array of connections configurations
     * @param ContainerBuilder $container A ContainerBuilder instance
     */
    protected function loadConnections(array $connections, ContainerBuilder $container)
    {
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
     * Loads an ODM document managers bundle mapping information.
     *
     * There are two distinct configuration possibilities for mapping information:
     *
     * 1. Specify a bundle and optionally details where the entity and mapping information reside.
     * 2. Specify an arbitrary mapping location.
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
            foreach ($calls as $call) {
                if ($call[0] == 'setDocumentNamespaces') {
                    $this->aliasMap = array_merge($call[1][0], $this->aliasMap);
                }
            }
            $method = $odmConfigDef->removeMethodCall('setDocumentNamespaces');
        }
        $odmConfigDef->addMethodCall('setDocumentNamespaces', array($this->aliasMap));
    }

    protected function loadConstraints(ContainerBuilder $container)
    {
        // FIXME: the validator.annotations.namespaces parameter does not exist anymore
        // and anyway, it was not available in the FrameworkExtension code
        // as each bundle is isolated from the others
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
        return 'http://symfony.com/schema/dic/doctrine/odm/mongodb';
    }

    /**
     * @return string
     */
    public function getXsdValidationBasePath()
    {
        return __DIR__.'/../Resources/config/schema';
    }
}
