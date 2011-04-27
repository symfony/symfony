<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\DoctrineBundle\DependencyInjection;

use Symfony\Component\DependencyInjection\Alias;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\DefinitionDecorator;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Bundle\DoctrineAbstractBundle\DependencyInjection\AbstractDoctrineExtension;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\Config\Definition\Processor;

/**
 * DoctrineExtension is an extension for the Doctrine DBAL and ORM library.
 *
 * @author Jonathan H. Wage <jonwage@gmail.com>
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Benjamin Eberlei <kontakt@beberlei.de>
 */
class DoctrineExtension extends AbstractDoctrineExtension
{
    public function load(array $configs, ContainerBuilder $container)
    {
        $processor = new Processor();
        $configuration = new Configuration($container->getParameter('kernel.debug'));
        $config = $processor->processConfiguration($configuration, $configs);

        if (!empty($config['dbal'])) {
            $this->dbalLoad($config['dbal'], $container);
        }

        if (!empty($config['orm'])) {
            $this->ormLoad($config['orm'], $container);
        }
    }

    /**
     * Loads the DBAL configuration.
     *
     * Usage example:
     *
     *      <doctrine:dbal id="myconn" dbname="sfweb" user="root" />
     *
     * @param array            $config    An array of configuration settings
     * @param ContainerBuilder $container A ContainerBuilder instance
     */
    protected function dbalLoad(array $config, ContainerBuilder $container)
    {
        $loader = new XmlFileLoader($container, new FileLocator(__DIR__.'/../Resources/config'));
        $loader->load('dbal.xml');

        if (empty($config['default_connection'])) {
            $keys = array_keys($config['connections']);
            $config['default_connection'] = reset($keys);
        }

        $container->setAlias('database_connection', sprintf('doctrine.dbal.%s_connection', $config['default_connection']));
        $container->setAlias('doctrine.dbal.event_manager', new Alias(sprintf('doctrine.dbal.%s_connection.event_manager', $config['default_connection']), false));
        $container->setParameter('doctrine.dbal.default_connection', $config['default_connection']);

        $container->getDefinition('doctrine.dbal.connection_factory')->replaceArgument(0, $config['types']);

        foreach ($config['connections'] as $name => $connection) {
            $this->loadDbalConnection($name, $connection, $container);
        }
    }

    /**
     * Loads a configured DBAL connection.
     *
     * @param string           $name       The name of the connection
     * @param array            $connection A dbal connection configuration.
     * @param ContainerBuilder $container  A ContainerBuilder instance
     */
    protected function loadDbalConnection($name, array $connection, ContainerBuilder $container)
    {
        // configuration
        $configuration = $container->setDefinition(sprintf('doctrine.dbal.%s_connection.configuration', $name), new DefinitionDecorator('doctrine.dbal.connection.configuration'));
        if (isset($connection['logging']) && $connection['logging']) {
            $configuration->addMethodCall('setSQLLogger', array(new Reference('doctrine.dbal.logger')));
            unset ($connection['logging']);
        }

        // event manager
        $container->setDefinition(sprintf('doctrine.dbal.%s_connection.event_manager', $name), new DefinitionDecorator('doctrine.dbal.connection.event_manager'));

        // connection
        if (isset($connection['charset'])) {
            if ((isset($connection['driver']) && stripos($connection['driver'], 'mysql') !== false) ||
                 (isset($connection['driverClass']) && stripos($connection['driverClass'], 'mysql') !== false)) {
                $mysqlSessionInit = new Definition('%doctrine.dbal.events.mysql_session_init.class%');
                $mysqlSessionInit->setArguments(array($connection['charset']));
                $mysqlSessionInit->setPublic(false);
                $mysqlSessionInit->addTag(sprintf('doctrine.dbal.%s_event_subscriber', $name));

                $container->setDefinition(
                    sprintf('doctrine.dbal.%s_connection.events.mysqlsessioninit', $name),
                    $mysqlSessionInit
                );
                unset($connection['charset']);
            }
        }

        if (isset($connection['platform_service'])) {
            $connection['platform'] = new Reference($connection['platform_service']);
            unset($connection['platform_service']);
        }

        $container
            ->setDefinition(sprintf('doctrine.dbal.%s_connection', $name), new DefinitionDecorator('doctrine.dbal.connection'))
            ->setArguments(array(
                $connection,
                new Reference(sprintf('doctrine.dbal.%s_connection.configuration', $name)),
                new Reference(sprintf('doctrine.dbal.%s_connection.event_manager', $name)),
            ))
        ;
    }

    /**
     * Loads the Doctrine ORM configuration.
     *
     * Usage example:
     *
     *     <doctrine:orm id="mydm" connection="myconn" />
     *
     * @param array            $config    An array of configuration settings
     * @param ContainerBuilder $container A ContainerBuilder instance
     */
    protected function ormLoad(array $config, ContainerBuilder $container)
    {
        $loader = new XmlFileLoader($container, new FileLocator(__DIR__.'/../Resources/config'));
        $loader->load('orm.xml');

        $container->setParameter('doctrine.orm.entity_managers', $entityManagers = array_keys($config['entity_managers']));

        if (empty($config['default_entity_manager'])) {
            $config['default_entity_manager'] = reset($entityManagers);
        }

        $options = array('default_entity_manager', 'auto_generate_proxy_classes', 'proxy_dir', 'proxy_namespace');
        foreach ($options as $key) {
            $container->setParameter('doctrine.orm.'.$key, $config[$key]);
        }

        $container->setAlias('doctrine.orm.entity_manager', sprintf('doctrine.orm.%s_entity_manager', $config['default_entity_manager']));

        foreach ($config['entity_managers'] as $name => $entityManager) {
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
        if ($entityManager['auto_mapping'] && count($container->getParameter('doctrine.orm.entity_managers')) > 1) {
            throw new \LogicException('You cannot enable "auto_mapping" when several entity managers are defined.');
        }

        $ormConfigDef = $container->setDefinition(sprintf('doctrine.orm.%s_configuration', $entityManager['name']), new DefinitionDecorator('doctrine.orm.configuration'));

        $this->loadOrmEntityManagerMappingInformation($entityManager, $ormConfigDef, $container);
        $this->loadOrmCacheDrivers($entityManager, $container);

        $methods = array(
            'setMetadataCacheImpl'        => new Reference(sprintf('doctrine.orm.%s_metadata_cache', $entityManager['name'])),
            'setQueryCacheImpl'           => new Reference(sprintf('doctrine.orm.%s_query_cache', $entityManager['name'])),
            'setResultCacheImpl'          => new Reference(sprintf('doctrine.orm.%s_result_cache', $entityManager['name'])),
            'setMetadataDriverImpl'       => new Reference('doctrine.orm.'.$entityManager['name'].'_metadata_driver'),
            'setProxyDir'                 => '%doctrine.orm.proxy_dir%',
            'setProxyNamespace'           => '%doctrine.orm.proxy_namespace%',
            'setAutoGenerateProxyClasses' => '%doctrine.orm.auto_generate_proxy_classes%',
            'setClassMetadataFactoryName' => $entityManager['class_metadata_factory_name'],
        );
        foreach ($methods as $method => $arg) {
            $ormConfigDef->addMethodCall($method, array($arg));
        }

        foreach ($entityManager['hydrators'] as $name => $class) {
            $ormConfigDef->addMethodCall('addCustomHydrationMode', array($name, $class));
        }

        if (!empty($entityManager['dql'])) {
            foreach ($entityManager['dql']['string_functions'] as $name => $function) {
                $ormConfigDef->addMethodCall('addCustomStringFunction', array($name, $function));
            }
            foreach ($entityManager['dql']['numeric_functions'] as $name => $function) {
                $ormConfigDef->addMethodCall('addCustomNumericFunction', array($name, $function));
            }
            foreach ($entityManager['dql']['datetime_functions'] as $name => $function) {
                $ormConfigDef->addMethodCall('addCustomDatetimeFunction', array($name, $function));
            }
        }

        $entityManagerService = sprintf('doctrine.orm.%s_entity_manager', $entityManager['name']);
        $connectionId = isset($entityManager['connection']) ? sprintf('doctrine.dbal.%s_connection', $entityManager['connection']) : 'database_connection';
        $eventManagerID = isset($entityManager['connection']) ? sprintf('doctrine.dbal.%s_connection.event_manager', $entityManager['connection']) : 'doctrine.dbal.event_manager';

        $ormEmArgs = array(
            new Reference($connectionId),
            new Reference(sprintf('doctrine.orm.%s_configuration', $entityManager['name']))
        );
        $ormEmDef = new Definition('%doctrine.orm.entity_manager.class%', $ormEmArgs);
        $ormEmDef->setFactoryClass('%doctrine.orm.entity_manager.class%');
        $ormEmDef->setFactoryMethod('create');
        $ormEmDef->addTag('doctrine.orm.entity_manager');
        $container->setDefinition($entityManagerService, $ormEmDef);

        $container->setAlias(
            sprintf('doctrine.orm.%s_entity_manager.event_manager', $entityManager['name']),
            new Alias($eventManagerID, false)
        );
    }

    /**
     * Loads an ORM entity managers bundle mapping information.
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
        return 'doctrine.orm.'.$name;
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
     * @param array            $entityManager A configured ORM entity manager.
     * @param ContainerBuilder $container     A ContainerBuilder instance
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
     * @param array            $entityManager A configured ORM entity manager.
     * @param ContainerBuilder $container A ContainerBuilder instance
     * @param string           $cacheName
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
     * @param array            $entityManager The array configuring an entity manager.
     * @param array            $cacheDriver The cache driver configuration.
     * @param ContainerBuilder $container
     * @return Definition $cacheDef
     */
    protected function getEntityManagerCacheDefinition(array $entityManager, $cacheDriver, ContainerBuilder $container)
    {
        switch ($cacheDriver['type']) {
            case 'memcache':
                $memcacheClass = !empty($cacheDriver['class']) ? $cacheDriver['class'] : '%doctrine.orm.cache.memcache.class%';
                $memcacheInstanceClass = !empty($cacheDriver['instance_class']) ? $cacheDriver['instance_class'] : '%doctrine.orm.cache.memcache_instance.class%';
                $memcacheHost = !empty($cacheDriver['host']) ? $cacheDriver['host'] : '%doctrine.orm.cache.memcache_host%';
                $memcachePort = !empty($cacheDriver['port']) ? $cacheDriver['port'] : '%doctrine.orm.cache.memcache_port%';
                $cacheDef = new Definition($memcacheClass);
                $memcacheInstance = new Definition($memcacheInstanceClass);
                $memcacheInstance->addMethodCall('connect', array(
                    $memcacheHost, $memcachePort
                ));
                $container->setDefinition(sprintf('doctrine.orm.%s_memcache_instance', $entityManager['name']), $memcacheInstance);
                $cacheDef->addMethodCall('setMemcache', array(new Reference(sprintf('doctrine.orm.%s_memcache_instance', $entityManager['name']))));
                break;
            case 'apc':
            case 'array':
            case 'xcache':
                $cacheDef = new Definition('%'.sprintf('doctrine.orm.cache.%s.class', $cacheDriver['type']).'%');
                break;
            default:
                throw new \InvalidArgumentException(sprintf('"%s" is an unrecognized Doctrine cache driver.', $cacheDriver['type']));
        }

        $cacheDef->setPublic(false);
        // generate a unique namespace for the given application
        $namespace = 'sf2orm_'.$entityManager['name'].'_'.md5($container->getParameter('kernel.root_dir'));
        $cacheDef->addMethodCall('setNamespace', array($namespace));

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
        return 'http://symfony.com/schema/dic/doctrine';
    }
}
