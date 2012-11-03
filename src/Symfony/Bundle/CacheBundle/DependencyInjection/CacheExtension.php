<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\CacheBundle\DependencyInjection;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Config\FileLocator;

/**
 * Cache extension
 *
 * @author Florin Patan <florinpatan@gmail.com>
 */
class CacheExtension extends Extension
{
    public function load(array $configs, ContainerBuilder $container)
    {
        $configuration = $this->getConfiguration($configs, $container);
        $config = $this->processConfiguration($configuration, $configs);

        $loader = new XmlFileLoader($container, new FileLocator(__DIR__.'/../Resources/config'));
        $loader->load('cache.xml');

        $container->setParameter('cache.driver.class', $config['driver']['class']);

        // Configure cache drivers
        foreach ($config['drivers'] as $type => $driver) {
            if (!$driver['enabled']) {
                continue;
            }

            $container->setParameter(sprintf('cache.drivers.%s.class', $type), $driver['class']);
            $container->setParameter(sprintf('cache.drivers.%s.ttl', $type), $driver['config']['ttl']);

            $cacheDriverDefinition = new Definition();
            $cacheDriverDefinition
                ->setPublic(false)
                ->setClass(sprintf('%%cache.drivers.%s.class%%', $type))
            ;

            if (isset($driver['instance'])) {
                $container->setParameter(sprintf('cache.internal.drivers.%s.instance', $type), $driver['instance']);

                $internalDriverDefinition  = new Definition();
                $internalDriverDefinition
                    ->setPublic(false)
                    ->setClass(sprintf('%%cache.internal.drivers.%s.instance%%', $type))
                ;

                if (isset($driver['servers']) && !empty($driver['servers'])) {
                    $container->setParameter(sprintf('cache.internal.drivers.%s.servers', $type), $driver['servers']);

                    $internalDriverDefinition->addMethodCall('addServers', array(sprintf('%%cache.internal.drivers.%s.servers%%', $type)));
                }

                $container->setDefinition('cache.internal.driver.' . $type, $internalDriverDefinition);
            }

            if (isset($driver['service'])) {
                $cacheDriverDefinition->addArgument(new Reference($driver['service']));
            } elseif(isset($driver['instance'])) {
                $cacheDriverDefinition->addArgument(new Reference(sprintf('cache.internal.driver.%s', $type)));
            }

            $container->setDefinition('cache.driver.' . $type, $cacheDriverDefinition);
        }


        // Configure cache instances
        foreach ($config['instances'] as $name => $instanceConfig) {

            $instanceType = $instanceConfig['type'];
            $driverInstance = $config['drivers'][$instanceType];

            if (!$driverInstance['enabled']) {
                continue;
            }

            $instanceConfig['config'] = array_merge($driverInstance['config'], $instanceConfig['config']);
            $instanceConfig = array_merge($driverInstance, $instanceConfig);

            if (isset($instanceConfig['service'])) {
                unset($instanceConfig['servers'], $instanceConfig['instance']);
            } else {
                unset($instanceConfig['service']);
            }

            if (isset($instanceConfig['servers']) && isset($driverInstance['servers'])) {
                $instanceConfig['servers'] = array_merge($driverInstance['servers'], $instanceConfig['servers']);
            }

            if (!empty($instanceConfig['servers']) && isset($instanceConfig['instance'])) {
                $container->setParameter(sprintf('cache.internal.instances.%s.class', $name), $instanceConfig['instance']);

                $internalDriverDefinition  = new Definition();
                $internalDriverDefinition
                    ->setPublic(false)
                    ->setClass(sprintf('%%cache.internal.instances.%s.class%%', $name))
                ;

                $container->setParameter(sprintf('cache.internal.instances.%s.servers', $name), $instanceConfig['servers']);

                $internalDriverDefinition->addMethodCall('addServers', array(sprintf('%%cache.internal.instances.%s.servers%%', $name)));

                $internalCacheDriverName = sprintf('cache.internal.driver.%s_%s', $instanceConfig['type'], $name);
                $container->setDefinition($internalCacheDriverName, $internalDriverDefinition);
            } elseif (isset($instanceConfig['service'])) {
                $internalCacheDriverName = $instanceConfig['service'];
            } else {
                $internalCacheDriverName = sprintf('cache.driver.%s', $instanceConfig['type']);
            }

            $container->setParameter(sprintf('cache.instances.%s.instance.class', $name), $instanceConfig['class']);
            $container->setParameter(sprintf('cache.instances.%s.instance.config.ttl', $name), $instanceConfig['config']['ttl']);

            $internalInstanceDefinition = new Definition();
            $internalInstanceDefinition
                ->setPublic(false)
                ->setClass(sprintf('%%cache.instances.%s.instance.class%%', $name))
                ->addArgument(new Reference($internalCacheDriverName))
            ;

            $internalInstanceName = sprintf('cache.instance.%s_%s', $instanceType, $name);

            $container->setDefinition($internalInstanceName, $internalInstanceDefinition);

            $container->setParameter(sprintf('cache.instances.%s.config.ttl', $name), $instanceConfig['config']['ttl']);

            $cacheInstanceDefinition = new Definition();
            $cacheInstanceDefinition
                ->setClass('%cache.driver.class%')
                ->addMethodCall('setDefaultTtl', array(sprintf('%%cache.instances.%s.config.ttl%%', $name)))
                ->addMethodCall('setProfiler', array(new Reference('cache.profiler')))
                ->addMethodCall('setLogger', array(new Reference('logger')))
                ->addArgument(new Reference($internalInstanceName))
                ->addArgument($name)
                ->addArgument($instanceConfig['type'])
            ;


            $container->setDefinition('cache.' . $name, $cacheInstanceDefinition);
        }
    }
}
