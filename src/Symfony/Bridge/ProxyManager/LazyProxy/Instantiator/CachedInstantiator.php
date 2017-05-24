<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\ProxyManager\LazyProxy\Instantiator;

use ProxyManager\Configuration;
use ProxyManager\Factory\LazyLoadingValueHolderFactory;
use ProxyManager\FileLocator\FileLocator;
use ProxyManager\GeneratorStrategy\FileWriterGeneratorStrategy;
use ProxyManager\Proxy\LazyLoadingInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\LazyProxy\Instantiator\InstantiatorInterface;

/**
 * Lazy loading proxy generator (Using cached Proxy to improve performance).
 *
 * @author Alex Moreno <alex.m.lopez@capgemini.com>
 * @author Dries Vanlerberghe <dries.vanlerberghe@capgemini.com>
 */
class CachedInstantiator implements InstantiatorInterface
{
    /**
     * @var LazyLoadingValueHolderFactory
     */
    private $factory;

    /**
     * Constructor
     *
     * @param string $proxiesPath      Path where we'll store temporary the proxy files.
     */
    public function __construct($proxiesPath)
    {
        $config = new Configuration();
        $config->setProxiesTargetDir($proxiesPath);
        $fileLocator = new FileLocator($config->getProxiesTargetDir());
        $config->setGeneratorStrategy(new FileWriterGeneratorStrategy($fileLocator));

        $this->factory = new LazyLoadingValueHolderFactory($config);
    }

    /**
     * {@inheritdoc}
     */
    public function instantiateProxy(ContainerInterface $container, Definition $definition, $id, $realInstantiator)
    {
        return $this->factory->createProxy(
            $definition->getClass(),
            function (&$wrappedInstance, LazyLoadingInterface $proxy) use ($realInstantiator) {
                $wrappedInstance = call_user_func($realInstantiator);

                $proxy->setProxyInitializer(null);

                return true;
            }
        );
    }
}
