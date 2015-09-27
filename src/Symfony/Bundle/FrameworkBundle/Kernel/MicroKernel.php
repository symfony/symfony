<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Kernel;

use Symfony\Bundle\FrameworkBundle\Routing\RouteCollectionBuilder;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\MicroKernel as BaseMicroKernel;
use Symfony\Component\Routing\Loader\RouteLoaderInterface;

/**
 * A kernel that adds some functionality that depends on FrameworkBundle.
 *
 * @author Ryan Weaver <ryan@knpuniversity.com>
 */
abstract class MicroKernel extends BaseMicroKernel implements RouteLoaderInterface
{
    /**
     * Add or import routes into your application.
     *
     *     $routes->import('config/routing.yml');
     *     $routes->add('/admin', 'AppBundle:Admin:dashboard', 'admin_dashboard');
     *
     * @param RouteCollectionBuilder $routes
     */
    abstract protected function configureRoutes(RouteCollectionBuilder $routes);

    /**
     * Creates a RouteCollectionBuilder for convenience and calls configureRoutes.
     *
     * @param LoaderInterface $loader
     *
     * @return \Symfony\Component\Routing\RouteCollection
     */
    public function getRouteCollection(LoaderInterface $loader)
    {
        $routes = new RouteCollectionBuilder($loader);

        $this->configureRoutes($routes);

        return $routes->build();
    }

    /**
     * Overridden to make the routing resource be the kernel.
     *
     * @param LoaderInterface $loader
     */
    public function registerContainerConfiguration(LoaderInterface $loader)
    {
        $loader->load(function (ContainerBuilder $container) {
            $container->prependExtensionConfig('framework', array(
                'router' => array(
                    'resource' => 'kernel',
                    'type' => 'service',
                ),
            ));
        });

        parent::registerContainerConfiguration($loader);
    }
}
