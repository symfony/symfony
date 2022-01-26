<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Doctrine\DependencyInjection;

use Symfony\Component\Config\Resource\GlobResource;
use Symfony\Component\DependencyInjection\Alias;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;

/**
 * This abstract classes groups common code that Doctrine Object Manager extensions (ORM, MongoDB, CouchDB) need.
 *
 * @author Benjamin Eberlei <kontakt@beberlei.de>
 */
abstract class AbstractDoctrineExtension extends Extension
{
    /**
     * Used inside metadata driver method to simplify aggregation of data.
     */
    protected $aliasMap = [];

    /**
     * Used inside metadata driver method to simplify aggregation of data.
     */
    protected $drivers = [];

    /**
     * @param array $objectManager A configured object manager
     *
     * @throws \InvalidArgumentException
     */
    protected function loadMappingInformation(array $objectManager, ContainerBuilder $container)
    {
        if ($objectManager['auto_mapping']) {
            // automatically register bundle mappings
            foreach (array_keys($container->getParameter('kernel.bundles')) as $bundle) {
                if (!isset($objectManager['mappings'][$bundle])) {
                    $objectManager['mappings'][$bundle] = [
                        'mapping' => true,
                        'is_bundle' => true,
                    ];
                }
            }
        }

        foreach ($objectManager['mappings'] as $mappingName => $mappingConfig) {
            if (null !== $mappingConfig && false === $mappingConfig['mapping']) {
                continue;
            }

            $mappingConfig = array_replace([
                'dir' => false,
                'type' => false,
                'prefix' => false,
            ], (array) $mappingConfig);

            $mappingConfig['dir'] = $container->getParameterBag()->resolveValue($mappingConfig['dir']);
            // a bundle configuration is detected by realizing that the specified dir is not absolute and existing
            if (!isset($mappingConfig['is_bundle'])) {
                $mappingConfig['is_bundle'] = !is_dir($mappingConfig['dir']);
            }

            if ($mappingConfig['is_bundle']) {
                $bundle = null;
                $bundleMetadata = null;
                foreach ($container->getParameter('kernel.bundles') as $name => $class) {
                    if ($mappingName === $name) {
                        $bundle = new \ReflectionClass($class);
                        $bundleMetadata = $container->getParameter('kernel.bundles_metadata')[$name];

                        break;
                    }
                }

                if (null === $bundle) {
                    throw new \InvalidArgumentException(sprintf('Bundle "%s" does not exist or it is not enabled.', $mappingName));
                }

                $mappingConfig = $this->getMappingDriverBundleConfigDefaults($mappingConfig, $bundle, $container, $bundleMetadata['path']);
                if (!$mappingConfig) {
                    continue;
                }
            } elseif (!$mappingConfig['type']) {
                $mappingConfig['type'] = $this->detectMappingType($mappingConfig['dir'], $container);
            }

            $this->assertValidMappingConfiguration($mappingConfig, $objectManager['name']);
            $this->setMappingDriverConfig($mappingConfig, $mappingName);
            $this->setMappingDriverAlias($mappingConfig, $mappingName);
        }
    }

    /**
     * Register the alias for this mapping driver.
     *
     * Aliases can be used in the Query languages of all the Doctrine object managers to simplify writing tasks.
     */
    protected function setMappingDriverAlias(array $mappingConfig, string $mappingName)
    {
        if (isset($mappingConfig['alias'])) {
            $this->aliasMap[$mappingConfig['alias']] = $mappingConfig['prefix'];
        } else {
            $this->aliasMap[$mappingName] = $mappingConfig['prefix'];
        }
    }

    /**
     * Register the mapping driver configuration for later use with the object managers metadata driver chain.
     *
     * @throws \InvalidArgumentException
     */
    protected function setMappingDriverConfig(array $mappingConfig, string $mappingName)
    {
        $mappingDirectory = $mappingConfig['dir'];
        if (!is_dir($mappingDirectory)) {
            throw new \InvalidArgumentException(sprintf('Invalid Doctrine mapping path given. Cannot load Doctrine mapping/bundle named "%s".', $mappingName));
        }

        $this->drivers[$mappingConfig['type']][$mappingConfig['prefix']] = realpath($mappingDirectory) ?: $mappingDirectory;
    }

    /**
     * If this is a bundle controlled mapping all the missing information can be autodetected by this method.
     *
     * Returns false when autodetection failed, an array of the completed information otherwise.
     *
     * @param string|null $bundleDir The bundle directory path
     *
     * @return array|false
     */
    protected function getMappingDriverBundleConfigDefaults(array $bundleConfig, \ReflectionClass $bundle, ContainerBuilder $container/*, string $bundleDir = null*/)
    {
        if (\func_num_args() < 4 && __CLASS__ !== static::class && __CLASS__ !== (new \ReflectionMethod($this, __FUNCTION__))->getDeclaringClass()->getName() && !$this instanceof \PHPUnit\Framework\MockObject\MockObject && !$this instanceof \Prophecy\Prophecy\ProphecySubjectInterface && !$this instanceof \Mockery\MockInterface) {
            trigger_deprecation('symfony/doctrine-bridge', '5.4', 'The "%s()" method will have a new "string $bundleDir = null" argument in version 6.0, not defining it is deprecated.', __METHOD__);
            $bundleDir = null;
        } else {
            $bundleDir = func_get_arg(3);
        }

        $bundleClassDir = \dirname($bundle->getFileName());
        $bundleDir ?? $bundleDir = $bundleClassDir;

        if (!$bundleConfig['type']) {
            $bundleConfig['type'] = $this->detectMetadataDriver($bundleDir, $container);

            if (!$bundleConfig['type'] && $bundleDir !== $bundleClassDir) {
                $bundleConfig['type'] = $this->detectMetadataDriver($bundleClassDir, $container);
            }
        }

        if (!$bundleConfig['type']) {
            // skip this bundle, no mapping information was found.
            return false;
        }

        if (!$bundleConfig['dir']) {
            if (\in_array($bundleConfig['type'], ['annotation', 'staticphp', 'attribute'])) {
                $bundleConfig['dir'] = $bundleClassDir.'/'.$this->getMappingObjectDefaultName();
            } else {
                $bundleConfig['dir'] = $bundleDir.'/'.$this->getMappingResourceConfigDirectory($bundleDir);
            }
        } else {
            $bundleConfig['dir'] = $bundleDir.'/'.$bundleConfig['dir'];
        }

        if (!$bundleConfig['prefix']) {
            $bundleConfig['prefix'] = $bundle->getNamespaceName().'\\'.$this->getMappingObjectDefaultName();
        }

        return $bundleConfig;
    }

    /**
     * Register all the collected mapping information with the object manager by registering the appropriate mapping drivers.
     */
    protected function registerMappingDrivers(array $objectManager, ContainerBuilder $container)
    {
        // configure metadata driver for each bundle based on the type of mapping files found
        if ($container->hasDefinition($this->getObjectManagerElementName($objectManager['name'].'_metadata_driver'))) {
            $chainDriverDef = $container->getDefinition($this->getObjectManagerElementName($objectManager['name'].'_metadata_driver'));
        } else {
            $chainDriverDef = new Definition($this->getMetadataDriverClass('driver_chain'));
            $chainDriverDef->setPublic(false);
        }

        foreach ($this->drivers as $driverType => $driverPaths) {
            $mappingService = $this->getObjectManagerElementName($objectManager['name'].'_'.$driverType.'_metadata_driver');
            if ($container->hasDefinition($mappingService)) {
                $mappingDriverDef = $container->getDefinition($mappingService);
                $args = $mappingDriverDef->getArguments();
                if ('annotation' == $driverType) {
                    $args[1] = array_merge(array_values($driverPaths), $args[1]);
                } else {
                    $args[0] = array_merge(array_values($driverPaths), $args[0]);
                }
                $mappingDriverDef->setArguments($args);
            } elseif ('attribute' === $driverType) {
                $mappingDriverDef = new Definition($this->getMetadataDriverClass($driverType), [
                    array_values($driverPaths),
                ]);
            } elseif ('annotation' == $driverType) {
                $mappingDriverDef = new Definition($this->getMetadataDriverClass($driverType), [
                    new Reference($this->getObjectManagerElementName('metadata.annotation_reader')),
                    array_values($driverPaths),
                ]);
            } else {
                $mappingDriverDef = new Definition($this->getMetadataDriverClass($driverType), [
                    array_values($driverPaths),
                ]);
            }
            $mappingDriverDef->setPublic(false);
            if (str_contains($mappingDriverDef->getClass(), 'yml') || str_contains($mappingDriverDef->getClass(), 'xml')) {
                $mappingDriverDef->setArguments([array_flip($driverPaths)]);
                $mappingDriverDef->addMethodCall('setGlobalBasename', ['mapping']);
            }

            $container->setDefinition($mappingService, $mappingDriverDef);

            foreach ($driverPaths as $prefix => $driverPath) {
                $chainDriverDef->addMethodCall('addDriver', [new Reference($mappingService), $prefix]);
            }
        }

        $container->setDefinition($this->getObjectManagerElementName($objectManager['name'].'_metadata_driver'), $chainDriverDef);
    }

    /**
     * Assertion if the specified mapping information is valid.
     *
     * @throws \InvalidArgumentException
     */
    protected function assertValidMappingConfiguration(array $mappingConfig, string $objectManagerName)
    {
        if (!$mappingConfig['type'] || !$mappingConfig['dir'] || !$mappingConfig['prefix']) {
            throw new \InvalidArgumentException(sprintf('Mapping definitions for Doctrine manager "%s" require at least the "type", "dir" and "prefix" options.', $objectManagerName));
        }

        if (!is_dir($mappingConfig['dir'])) {
            throw new \InvalidArgumentException(sprintf('Specified non-existing directory "%s" as Doctrine mapping source.', $mappingConfig['dir']));
        }

        if (!\in_array($mappingConfig['type'], ['xml', 'yml', 'annotation', 'php', 'staticphp', 'attribute'])) {
            throw new \InvalidArgumentException(sprintf('Can only configure "xml", "yml", "annotation", "php", "staticphp" or "attribute" through the DoctrineBundle. Use your own bundle to configure other metadata drivers. You can register them by adding a new driver to the "%s" service definition.', $this->getObjectManagerElementName($objectManagerName.'_metadata_driver')));
        }
    }

    /**
     * Detects what metadata driver to use for the supplied directory.
     *
     * @return string|null A metadata driver short name, if one can be detected
     */
    protected function detectMetadataDriver(string $dir, ContainerBuilder $container)
    {
        $configPath = $this->getMappingResourceConfigDirectory($dir);
        $extension = $this->getMappingResourceExtension();

        if (glob($dir.'/'.$configPath.'/*.'.$extension.'.xml', \GLOB_NOSORT)) {
            $driver = 'xml';
        } elseif (glob($dir.'/'.$configPath.'/*.'.$extension.'.yml', \GLOB_NOSORT)) {
            $driver = 'yml';
        } elseif (glob($dir.'/'.$configPath.'/*.'.$extension.'.php', \GLOB_NOSORT)) {
            $driver = 'php';
        } else {
            // add the closest existing directory as a resource
            $resource = $dir.'/'.$configPath;
            while (!is_dir($resource)) {
                $resource = \dirname($resource);
            }
            $container->fileExists($resource, false);

            if ($container->fileExists($dir.'/'.$this->getMappingObjectDefaultName(), false)) {
                return $this->detectMappingType($dir, $container);
            }

            return null;
        }
        $container->fileExists($dir.'/'.$configPath, false);

        return $driver;
    }

    /**
     * Detects what mapping type to use for the supplied directory.
     *
     * @return string A mapping type 'attribute' or 'annotation'
     */
    private function detectMappingType(string $directory, ContainerBuilder $container): string
    {
        if (\PHP_VERSION_ID < 80000) {
            return 'annotation';
        }

        $type = 'attribute';

        $glob = new GlobResource($directory, '*', true);
        $container->addResource($glob);

        $quotedMappingObjectName = preg_quote($this->getMappingObjectDefaultName(), '/');

        foreach ($glob as $file) {
            $content = file_get_contents($file);

            if (preg_match('/^#\[.*'.$quotedMappingObjectName.'\b/m', $content)) {
                break;
            }
            if (preg_match('/^ \* @.*'.$quotedMappingObjectName.'\b/m', $content)) {
                $type = 'annotation';
                break;
            }
        }

        return $type;
    }

    /**
     * Loads a configured object manager metadata, query or result cache driver.
     *
     * @throws \InvalidArgumentException in case of unknown driver type
     */
    protected function loadObjectManagerCacheDriver(array $objectManager, ContainerBuilder $container, string $cacheName)
    {
        $this->loadCacheDriver($cacheName, $objectManager['name'], $objectManager[$cacheName.'_driver'], $container);
    }

    /**
     * Loads a cache driver.
     *
     * @return string
     *
     * @throws \InvalidArgumentException
     */
    protected function loadCacheDriver(string $cacheName, string $objectManagerName, array $cacheDriver, ContainerBuilder $container)
    {
        $cacheDriverServiceId = $this->getObjectManagerElementName($objectManagerName.'_'.$cacheName);

        switch ($cacheDriver['type']) {
            case 'service':
                $container->setAlias($cacheDriverServiceId, new Alias($cacheDriver['id'], false));

                return $cacheDriverServiceId;
            case 'memcached':
                $memcachedClass = !empty($cacheDriver['class']) ? $cacheDriver['class'] : '%'.$this->getObjectManagerElementName('cache.memcached.class').'%';
                $memcachedInstanceClass = !empty($cacheDriver['instance_class']) ? $cacheDriver['instance_class'] : '%'.$this->getObjectManagerElementName('cache.memcached_instance.class').'%';
                $memcachedHost = !empty($cacheDriver['host']) ? $cacheDriver['host'] : '%'.$this->getObjectManagerElementName('cache.memcached_host').'%';
                $memcachedPort = !empty($cacheDriver['port']) ? $cacheDriver['port'] : '%'.$this->getObjectManagerElementName('cache.memcached_port').'%';
                $cacheDef = new Definition($memcachedClass);
                $memcachedInstance = new Definition($memcachedInstanceClass);
                $memcachedInstance->addMethodCall('addServer', [
                    $memcachedHost, $memcachedPort,
                ]);
                $container->setDefinition($this->getObjectManagerElementName(sprintf('%s_memcached_instance', $objectManagerName)), $memcachedInstance);
                $cacheDef->addMethodCall('setMemcached', [new Reference($this->getObjectManagerElementName(sprintf('%s_memcached_instance', $objectManagerName)))]);
                break;
             case 'redis':
                $redisClass = !empty($cacheDriver['class']) ? $cacheDriver['class'] : '%'.$this->getObjectManagerElementName('cache.redis.class').'%';
                $redisInstanceClass = !empty($cacheDriver['instance_class']) ? $cacheDriver['instance_class'] : '%'.$this->getObjectManagerElementName('cache.redis_instance.class').'%';
                $redisHost = !empty($cacheDriver['host']) ? $cacheDriver['host'] : '%'.$this->getObjectManagerElementName('cache.redis_host').'%';
                $redisPort = !empty($cacheDriver['port']) ? $cacheDriver['port'] : '%'.$this->getObjectManagerElementName('cache.redis_port').'%';
                $cacheDef = new Definition($redisClass);
                $redisInstance = new Definition($redisInstanceClass);
                $redisInstance->addMethodCall('connect', [
                    $redisHost, $redisPort,
                ]);
                $container->setDefinition($this->getObjectManagerElementName(sprintf('%s_redis_instance', $objectManagerName)), $redisInstance);
                $cacheDef->addMethodCall('setRedis', [new Reference($this->getObjectManagerElementName(sprintf('%s_redis_instance', $objectManagerName)))]);
                break;
            case 'apc':
            case 'apcu':
            case 'array':
            case 'xcache':
            case 'wincache':
            case 'zenddata':
                $cacheDef = new Definition('%'.$this->getObjectManagerElementName(sprintf('cache.%s.class', $cacheDriver['type'])).'%');
                break;
            default:
                throw new \InvalidArgumentException(sprintf('"%s" is an unrecognized Doctrine cache driver.', $cacheDriver['type']));
        }

        $cacheDef->setPublic(false);

        if (!isset($cacheDriver['namespace'])) {
            // generate a unique namespace for the given application
            if ($container->hasParameter('cache.prefix.seed')) {
                $seed = $container->getParameterBag()->resolveValue($container->getParameter('cache.prefix.seed'));
            } else {
                $seed = '_'.$container->getParameter('kernel.project_dir');
                $seed .= '.'.$container->getParameter('kernel.container_class');
            }

            $namespace = 'sf_'.$this->getMappingResourceExtension().'_'.$objectManagerName.'_'.ContainerBuilder::hash($seed);

            $cacheDriver['namespace'] = $namespace;
        }

        $cacheDef->addMethodCall('setNamespace', [$cacheDriver['namespace']]);

        $container->setDefinition($cacheDriverServiceId, $cacheDef);

        return $cacheDriverServiceId;
    }

    /**
     * Returns a modified version of $managerConfigs.
     *
     * The manager called $autoMappedManager will map all bundles that are not mapped by other managers.
     *
     * @return array
     */
    protected function fixManagersAutoMappings(array $managerConfigs, array $bundles)
    {
        if ($autoMappedManager = $this->validateAutoMapping($managerConfigs)) {
            foreach (array_keys($bundles) as $bundle) {
                foreach ($managerConfigs as $manager) {
                    if (isset($manager['mappings'][$bundle])) {
                        continue 2;
                    }
                }
                $managerConfigs[$autoMappedManager]['mappings'][$bundle] = [
                    'mapping' => true,
                    'is_bundle' => true,
                ];
            }
            $managerConfigs[$autoMappedManager]['auto_mapping'] = false;
        }

        return $managerConfigs;
    }

    /**
     * Prefixes the relative dependency injection container path with the object manager prefix.
     *
     * @example $name is 'entity_manager' then the result would be 'doctrine.orm.entity_manager'
     *
     * @return string
     */
    abstract protected function getObjectManagerElementName(string $name);

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
     * @param string|null $bundleDir The bundle directory path
     *
     * @return string
     */
    abstract protected function getMappingResourceConfigDirectory(/*string $bundleDir = null*/);

    /**
     * Extension used by the mapping files.
     *
     * @return string
     */
    abstract protected function getMappingResourceExtension();

    /**
     * The class name used by the various mapping drivers.
     */
    abstract protected function getMetadataDriverClass(string $driverType): string;

    /**
     * Search for a manager that is declared as 'auto_mapping' = true.
     *
     * @throws \LogicException
     */
    private function validateAutoMapping(array $managerConfigs): ?string
    {
        $autoMappedManager = null;
        foreach ($managerConfigs as $name => $manager) {
            if (!$manager['auto_mapping']) {
                continue;
            }

            if (null !== $autoMappedManager) {
                throw new \LogicException(sprintf('You cannot enable "auto_mapping" on more than one manager at the same time (found in "%s" and "%s"").', $autoMappedManager, $name));
            }

            $autoMappedManager = $name;
        }

        return $autoMappedManager;
    }
}
