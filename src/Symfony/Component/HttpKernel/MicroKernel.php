<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel;

use Symfony\Component\Config\Loader\Loader;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Loader\ContainerBuilderAwareLoader;

/**
 * A Kernel that allows you to configure services.
 *
 * @author Ryan Weaver <ryan@knpuniversity.com>
 */
abstract class MicroKernel extends Kernel
{
    /**
     * Applies the bundle configuration and calls configureServices() for continued building.
     *
     * @param LoaderInterface $loader
     */
    public function registerContainerConfiguration(LoaderInterface $loader)
    {
        if (!$loader instanceof ContainerBuilderAwareLoader) {
            throw new \LogicException('registerContainerConfiguration requires the LoaderInterface to be a ContainerBuilderAwareLoader.');
        }

        $this->configureExtensions($loader->getContainerBuilder(), $loader->getResourceLoader());
        $this->configureServices($loader->getContainerBuilder(), $loader->getResourceLoader());
    }

    /**
     * Configure dependency injection extensions that have been added to the container.
     *
     * $c->loadFromExtension('framework', array(
     *     'secret' => '%secret%'
     * ));
     *
     * @param ContainerBuilder $c
     * @param LoaderInterface  $loader
     */
    protected function configureExtensions(ContainerBuilder $c, LoaderInterface $loader)
    {
    }

    /**
     * Add any service definitions to your container.
     *
     * @param ContainerBuilder $c
     * @param LoaderInterface  $loader
     */
    protected function configureServices(ContainerBuilder $c, LoaderInterface $loader)
    {
    }

    /**
     * Returns a loader with the ContainerBuilder embedded inside of it.
     *
     * @param ContainerInterface $container
     *
     * @return ContainerBuilderAwareLoader
     */
    protected function getContainerLoader(ContainerInterface $container)
    {
        if (!$container instanceof ContainerBuilder) {
            throw new \LogicException('Only ContainerBuilder instances are supported.');
        }

        $loader = parent::getContainerLoader($container);

        return new ContainerBuilderAwareLoader($container, $loader);
    }
}
