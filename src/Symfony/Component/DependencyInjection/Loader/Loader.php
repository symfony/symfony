<?php

namespace Symfony\Component\DependencyInjection\Loader;

use Symfony\Component\DependencyInjection\ContainerBuilder;

/*
 * This file is part of the Symfony framework.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

/**
 * Loader is the abstract class used by all built-in loaders.
 *
 * @author     Fabien Potencier <fabien.potencier@symfony-project.com>
 */
abstract class Loader implements LoaderInterface
{
    protected $container;
    protected $resolver;

    /**
     * Constructor.
     *
     * @param ContainerBuilder $container A ContainerBuilder instance
     */
    public function __construct(ContainerBuilder $container)
    {
        $this->container = $container;
    }

    /**
     * Gets the loader resolver.
     *
     * @return LoaderResolver A LoaderResolver instance
     */
    public function getResolver()
    {
        return $this->resolver;
    }

    /**
     * Sets the loader resolver.
     *
     * @param LoaderResolver $resolver A LoaderResolver instance
     */
    public function setResolver(LoaderResolver $resolver)
    {
        $this->resolver = $resolver;
    }

    /**
     * Adds definitions and parameters from a resource.
     *
     * @param mixed $resource A Resource
     */
    public function import($resource)
    {
        $this->resolve($resource)->load($resource);
    }

    /**
     * Finds a loader able to load an imported resource.
     *
     * @param mixed $resource A Resource
     *
     * @return LoaderInterface A LoaderInterface instance
     *
     * @throws \InvalidArgumentException if no loader is found
     */
    public function resolve($resource)
    {
        $loader = false;
        if ($this->supports($resource)) {
            $loader = $this;
        } elseif (null !== $this->resolver) {
            $loader = $this->resolver->resolve($resource);
        }

        if (false === $loader) {
            throw new \InvalidArgumentException(sprintf('Unable to load the "%s" container resource.', is_string($resource) ? $resource : (is_object($resource) ? get_class($resource) : 'RESOURCE')));
        }

        return $loader;
    }
}
