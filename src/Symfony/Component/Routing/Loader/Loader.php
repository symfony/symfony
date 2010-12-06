<?php

namespace Symfony\Component\Routing\Loader;

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
 * @author Fabien Potencier <fabien.potencier@symfony-project.com>
 */
abstract class Loader implements LoaderInterface
{
    protected $resolver;

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
     * Adds routes from a resource.
     *
     * @param mixed  $resource A Resource
     * @param string $type     The resource type
     */
    public function import($resource, $type = null)
    {
        $this->resolve($resource, $type)->load($resource);
    }

    /**
     * Finds a loader able to load an imported resource
     *
     * @param mixed  $resource A Resource
     * @param string $type     The resource type
     *
     * @return LoaderInterface A LoaderInterface instance
     *
     * @throws \InvalidArgumentException if no loader is found
     */
    public function resolve($resource, $type = null)
    {
        $loader = false;
        if ($this->supports($resource, $type)) {
            $loader = $this;
        } elseif (null !== $this->resolver) {
            $loader = $this->resolver->resolve($resource, $type);
        }

        if (false === $loader) {
            throw new \InvalidArgumentException(sprintf('Unable to load the "%s" routing resource.', is_string($resource) ? $resource : (is_object($resource) ? get_class($resource) : 'RESOURCE')));
        }

        return $loader;
    }
}
