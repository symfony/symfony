<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Routing;

use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Config\Loader\LoaderResolver;

/**
 * LazyLoader facilitate lazy loading of loader services.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class LazyLoader implements LoaderInterface
{
    protected $container;
    protected $service;

    /**
     * Constructor.
     *
     * @param ContainerInterface $container The container
     * @param string             $service   The loader service
     */
    public function __construct(ContainerInterface $container, $service)
    {
        $this->container = $container;
        $this->service = $service;
    }

    /**
     * Loads a resource.
     *
     * @param mixed  $resource A resource
     * @param string $type     The resource type
     *
     * @return RouteCollection A RouteCollection instance
     */
    public function load($resource, $type = null)
    {
        return $this->container->get($this->service)->load($resource, $type);
    }

    /**
     * Returns true if this class supports the given resource.
     *
     * @param mixed  $resource A resource
     * @param string $type     The resource type
     *
     * @return Boolean True if this class supports the given resource, false otherwise
     */
    public function supports($resource, $type = null)
    {
        return $this->container->get($this->service)->supports($resource, $type);
    }

    /**
     * Gets the loader resolver.
     *
     * @return LoaderResolver A LoaderResolver instance
     */
    public function getResolver()
    {
        return $this->container->get($this->service)->getResolver();
    }

    /**
     * Sets the loader resolver.
     *
     * @param LoaderResolver $resolver A LoaderResolver instance
     */
    public function setResolver(LoaderResolver $resolver)
    {
        $this->container->get($this->service)->setResolver($resolver);
    }
}
