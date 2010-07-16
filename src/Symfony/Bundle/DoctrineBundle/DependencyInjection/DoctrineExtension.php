<?php

namespace Symfony\Bundle\DoctrineBundle\DependencyInjection;

use Symfony\Components\DependencyInjection\Extension\Extension;
use Symfony\Components\DependencyInjection\Loader\XmlFileLoader;
use Symfony\Components\DependencyInjection\ContainerBuilder;
use Symfony\Components\DependencyInjection\Definition;
use Symfony\Components\DependencyInjection\Reference;

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
 * @package    Symfony
 * @subpackage Bundle_DoctrineBundle
 * @author     Fabien Potencier <fabien.potencier@symfony-project.com>
 */
class DoctrineExtension extends Extension
{
    protected $resources = array(
        'dbal' => 'dbal.xml',
        'orm'  => 'orm.xml',
    );
    protected $alias;
    protected $bundleDirs;
    protected $bundles;
    protected $kernelCacheDir;

    public function __construct(array $bundleDirs, array $bundles, $kernelCacheDir)
    {
        $this->bundleDirs = $bundleDirs;
        $this->bundles = $bundles;
        $this->kernelCacheDir = $kernelCacheDir;
    }

    public function setAlias($alias)
    {
        $this->alias = $alias;
    }

    /**
     * Loads the DBAL configuration.
     *
     * Usage example:
     *
     *      <doctrine:dbal dbname="sfweb" user="root" />
     *
     * @param array                                                        $config        An array of configuration settings
     * @param \Symfony\Components\DependencyInjection\ContainerBuilder $container A ContainerBuilder instance
     */
    public function dbalLoad($config, ContainerBuilder $container)
    {
        if (!$container->hasDefinition('doctrine.dbal.logger')) {
            $loader = new XmlFileLoader($container, __DIR__.'/../Resources/config');
            $loader->load($this->resources['dbal']);
        }

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

        $defaultConnectionName = isset($config['default_connection']) ? $config['default_connection'] : $container->getParameter('doctrine.dbal.default_connection');
        $container->setAlias('database_connection', null !== $this->alias ? $this->alias : sprintf('doctrine.dbal.%s_connection', $defaultConnectionName));
        $container->setParameter('doctrine.dbal.default_connection', $defaultConnectionName);

        $connections = array();
        if (isset($config['connections'])) {
            $config_connections = $config['connections'];
            if(isset($config['connections']['connection']) && isset($config['connections']['connection'][0])) {
                // Multiple connections
                $config_connections = $config['connections']['connection'];
            }
            foreach ($config_connections as $name => $connection) {
                $connections[isset($connection['id']) ? $connection['id'] : $name] = $connection;
            }
        } else {
            $connections = array($defaultConnectionName => $config);
        }

        foreach ($connections as $name => $connection) {
            // previously registered?
            if ($container->hasDefinition(sprintf('doctrine.dbal.%s_connection', $name))) {
                $driverDef = $container->getDefinition(sprintf('doctrine.dbal.%s_connection', $name));
                $arguments = $driverDef->getArguments();
                $driverOptions = $arguments[0];
            } else {
                $connection = array_merge($defaultConnection, $connection);

                $containerClass = isset($connection['configuration_class']) ? $connection['configuration_class'] : 'Doctrine\DBAL\Configuration';
                $containerDef = new Definition($containerClass);
                $containerDef->addMethodCall('setSqlLogger', array(new Reference('doctrine.dbal.logger')));
                $container->setDefinition(sprintf('doctrine.dbal.%s_connection.configuration', $name), $containerDef);

                $eventManagerDef = new Definition($connection['event_manager_class']);
                $container->setDefinition(sprintf('doctrine.dbal.%s_connection.event_manager', $name), $eventManagerDef);

                $driverOptions = array();
                $driverDef = new Definition('Doctrine\DBAL\DriverManager');
                $driverDef->setFactoryMethod('getConnection');
                $container->setDefinition(sprintf('doctrine.dbal.%s_connection', $name), $driverDef);
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
                new Reference(sprintf('doctrine.dbal.%s_connection.configuration', $name)),
                new Reference(sprintf('doctrine.dbal.%s_connection.event_manager', $name))
            ));
        }
    }

    /**
     * Loads the Doctrine ORM configuration.
     *
     * @param array                                                        $config        An array of configuration settings
     * @param \Symfony\Components\DependencyInjection\ContainerBuilder $container A ContainerBuilder instance
     */
    public function ormLoad($config, ContainerBuilder $container)
    {
        $loader = new XmlFileLoader($container, __DIR__.'/../Resources/config');
        $loader->load($this->resources['orm']);

        if (isset($config['default_entity_manager'])) {
            $container->getParameter('doctrine.orm.default_entity_manager', $config['default_entity_manager']);
        }
        $defaultEntityManager = $container->getParameter('doctrine.orm.default_entity_manager');

        foreach (array('metadata_driver', 'cache_driver') as $key) {
            if (isset($config[$key])) {
                $container->setParameter('doctrine.orm.'.$key, $config[$key]);
            }
        }

        $config['entity_managers'] = isset($config['entity_managers']) ? $config['entity_managers'] : array($defaultEntityManager => array());
        foreach ($config['entity_managers'] as $name => $connection) {
            $ormConfigDef = new Definition('Doctrine\ORM\Configuration');
            $container->setDefinition(sprintf('doctrine.orm.%s_configuration', $name), $ormConfigDef);

            $drivers = array('metadata', 'query', 'result');
            foreach ($drivers as $driver) {
                $definition = $container->getDefinition(sprintf('doctrine.orm.cache.%s', $container->getParameter('doctrine.orm.cache_driver')));
                $clone = clone $definition;
                $clone->addMethodCall('setNamespace', array(sprintf('doctrine_%s_', $driver)));
                $container->setDefinition(sprintf('doctrine.orm.%s_cache', $driver), $clone);
            }

            // configure metadata driver for each bundle based on the type of mapping files found
            $mappingDriverDef = new Definition('Doctrine\ORM\Mapping\Driver\DriverChain');
            $bundleEntityMappings = array();
            $bundleDirs = $this->bundleDirs;
            $aliasMap = array();
            foreach ($this->bundles as $className) {
                $tmp = dirname(str_replace('\\', '/', $className));
                $namespace = str_replace('/', '\\', dirname($tmp));
                $class = basename($tmp);

                if (!isset($bundleDirs[$namespace])) {
                    continue;
                }

                $type = false;
                if (is_dir($dir = $bundleDirs[$namespace].'/'.$class.'/Resources/config/doctrine/metadata')) {
                    $type = $this->detectMappingType($dir);
                }

                if (is_dir($dir = $bundleDirs[$namespace].'/'.$class.'/Entities')) {
                    if ($type === false) {
                        $type = 'annotation';
                    }
                    $aliasMap[$class] = $namespace.'\\'.$class.'\\Entities';
                }

                if (false !== $type) {
                    $mappingDriverDef->addMethodCall('addDriver', array(
                            new Reference(sprintf('doctrine.orm.metadata_driver.%s', $type)),
                            $namespace.'\\'.$class.'\\Entities'
                        )
                    );
                }
            }
            $ormConfigDef->addMethodCall('setEntityNamespaces', array($aliasMap));

            $container->setDefinition('doctrine.orm.metadata_driver', $mappingDriverDef);

            $proxyCacheDir = $this->kernelCacheDir . '/doctrine/orm/Proxies';
            if (!is_dir($proxyCacheDir)) {
                if (false === @mkdir($proxyCacheDir, 0777, true)) {
                    die(sprintf('Unable to create the Doctrine Proxy directory (%s)', dirname($proxyCacheDir)));
                }
            } elseif (!is_writable($proxyCacheDir)) {
                die(sprintf('Unable to write in the Doctrine Proxy directory (%s)', $proxyCacheDir));
            }

            $methods = array(
                'setMetadataCacheImpl' => new Reference('doctrine.orm.metadata_cache'),
                'setQueryCacheImpl' => new Reference('doctrine.orm.query_cache'),
                'setResultCacheImpl' => new Reference('doctrine.orm.result_cache'),
                'setMetadataDriverImpl' => new Reference('doctrine.orm.metadata_driver'),
                'setProxyDir' => $proxyCacheDir,
                'setProxyNamespace' => 'Proxies',
                'setAutoGenerateProxyClasses' => true
            );

            foreach ($methods as $method => $arg) {
                $ormConfigDef->addMethodCall($method, array($arg));
            }

            $ormEmArgs = array(
                new Reference(
                    sprintf('doctrine.dbal.%s_connection',
                    isset($connection['connection']) ? $connection['connection'] : $name)
                ),
                new Reference(sprintf('doctrine.orm.%s_configuration', $name))
            );
            $ormEmDef = new Definition('Doctrine\ORM\EntityManager', $ormEmArgs);
            $ormEmDef->setFactoryMethod('create');

            $container->setDefinition(
                sprintf('doctrine.orm.%s_entity_manager', $name),
                $ormEmDef
            );

            if ($name == $defaultEntityManager) {
                $container->setAlias(
                    'doctrine.orm.entity_manager',
                    sprintf('doctrine.orm.%s_entity_manager', $name)
                );
            }
        }

        $container->setAlias(
            'doctrine.orm.cache',
            sprintf(
                'doctrine.orm.cache.%s',
                $container->getParameter('doctrine.orm.cache_driver')
            )
        );
    }

    /**
     * Detect the type of Doctrine 2 mapping files located in a given directory.
     * Simply finds the first file in a directory and returns the extension. If no
     * mapping files are found then the annotation type is returned.
     *
     * @param string $dir
     *
     * @return string
     */
    protected function detectMappingType($dir)
    {
        $files = glob($dir.'/*.*');
        if (!$files) {
            return 'annotation';
        }
        $info = pathinfo($files[0]);

        return $info['extension'];
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
