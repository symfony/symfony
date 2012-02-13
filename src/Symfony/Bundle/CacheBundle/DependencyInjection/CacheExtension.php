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

use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Bundle\CacheBundle\DependencyInjection\Backend\BackendFactoryInterface;
use Symfony\Bundle\CacheBundle\DependencyInjection\Provider\ProviderFactoryInterface;

/**
 * CacheExtension is an extension for the Doctrine\Common\Cache interface.
 *
 * @author Lukas Kahwe Smith <smith@pooteeweet.org>
 * @author Victor Berchet <victor@suumit.com>
 */
class CacheExtension extends Extension
{
    private $beFactories = array();
    private $providerFactories = array();

    public function load(array $configs, ContainerBuilder $container)
    {
        $processor = new Processor();
        $configuration = $this->getConfiguration($configs, $container);
        $config = $processor->processConfiguration($configuration, $configs);

        $loader =  new XmlFileLoader($container, new FileLocator(__DIR__.'/../Resources/config'));
        $loader->load('services.xml');

        $container->setParameter('cache.debug', $config['debug']);

        $this->initBackends($container, $config['backends']);

        $this->configureBackends($container, $config['backends']);

        //$this->configureProviders($container, $config['providers']);

        //$container->setParameter($this->getAlias().'.namespaces', $config['namespaces']);
    }

    public function addBackendFactory(BackendFactoryInterface $beFactory)
    {
        $this->beFactories[$beFactory->getType()] = $beFactory;
    }

    public function addProviderFactory(ProviderFactoryInterface $providerFactory)
    {
        $this->providerFactories[$providerFactory->getName()] = $providerFactory;
    }

    private function initBackends(ContainerBuilder $container, $backends)
    {
        foreach ($backends as $name => $configs) {
            foreach ($configs as $type => $config) {
                $this->beFactories[$type]->init($container, $config);
            }
        }
    }

    private function configureBackends(ContainerBuilder $container, $backends)
    {
        foreach ($backends as $name => $configs) {
            foreach ($configs as $type => $config) {
                $this->beFactories[$type]->createService('cache.backend.concrete.'.$name, $container, $config);
            }
        }
    }

    private function configureProviders(ContainerBuilder $container, $configs)
    {
        foreach ($configs as $name => $config) {
            $type = $config['type'];
            $this->providerFactories[$type]->configure($container, $name, $config);
        }
    }

    public function getConfiguration(array $config, ContainerBuilder $container)
    {
        return new Configuration($this->beFactories, $this->providerFactories);
    }

    public function getXsdValidationBasePath()
    {
        return __DIR__.'/../Resources/config/schema';
    }

    public function getNamespace()
    {
        return 'http://symfony.com/schema/dic/cache';
    }
}
