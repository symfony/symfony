<?php

namespace Symfony\Bundle\DoctrineAbstractBundle\DependencyInjection;

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
 * This abstract classes groups common code that Doctrine Object Manager extensions (ORM, MongoDB, CouchDB) need.
 */
abstract class AbstractDoctrineExtension extends Extension
{
    /**
     * Used inside metadata driver method to simplify aggregation of data.
     *
     * @var array
     */
    protected $aliasMap = array();

    /**
     * Used inside metadata driver method to simplify aggregation of data.
     *
     * @var array
     */
    protected $drivers = array();

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
                    $mappingConfig = array('type' => $mappingConfig);
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
    protected function findBundleDirForNamespace($namespace, $container)
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
    protected function getBundleNamespace($bundleName, $container)
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
            $chainDriverDef->setPublic(false);
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
                    new Reference($this->getObjectManagerElementName('metadata.annotation_reader')),
                    array_values($driverPaths)
                ));
            } else {
                $mappingDriverDef = new Definition('%'.$this->getObjectManagerElementName('metadata.' . $driverType . '_class%'), array(
                    array_values($driverPaths)
                ));
            }
            $mappingDriverDef->setPublic(false);

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

    /**
     * Detects what metadata driver to use for the supplied directory.
     *
     * @param string $dir A directory path
     * @param ContainerBuilder $container A ContainerBuilder configuration
     *
     * @return string|null A metadata driver short name, if one can be detected
     */
    protected function detectMetadataDriver($dir, ContainerBuilder $container)
    {
        // add the closest existing directory as a resource
        $configPath = $this->getMappingResourceConfigDirectory();
        $resource = $dir.'/'.$configPath;
        while (!is_dir($resource)) {
            $resource = dirname($resource);
        }
        $container->addResource(new FileResource($resource));

        if (($files = glob($dir.'/'.$configPath.'/*.xml')) && count($files)) {
            return 'xml';
        } elseif (($files = glob($dir.'/'.$configPath.'/*.yml')) && count($files)) {
            return 'yml';
        } elseif (($files = glob($dir.'/'.$configPath.'/*.php')) && count($files)) {
            return 'php';
        }

        // add the directory itself as a resource
        $container->addResource(new FileResource($dir));

        if (is_dir($dir . '/' . $this->getMappingObjectDefaultName())) {
            return 'annotation';
        }

        return null;
    }

    /**
     * Prefixes the relative dependency injenction container path with the object manager prefix.
     *
     * @example $name is 'entity_manager' then the result would be 'doctrine.orm.entity_manager'
     *
     * @param string $name
     * @return string
     */
    abstract protected function getObjectManagerElementName($name);

    /**
     * Noun that describes the mapped objects such as Entity or Document.
     *
     * Will be used for autodetection of persistent objects directory.
     *
     * @return string
     */
    abstract protected function getMappingObjectDefaultName();

    /**
     * Relative path from the bundle root to the directory where mapping files reside.
     *
     * @return string
     */
    abstract protected function getMappingResourceConfigDirectory();
}