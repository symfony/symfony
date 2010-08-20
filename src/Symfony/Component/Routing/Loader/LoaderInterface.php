<?php

namespace Symfony\Component\Routing\Loader;

use Symfony\Component\Routing\Loader\LoaderResolver;

/*
 * This file is part of the Symfony framework.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

/**
 * LoaderInterface is the interface that all loaders classes must implement.
 *
 * @author     Fabien Potencier <fabien.potencier@symfony-project.com>
 */
interface LoaderInterface
{
    /**
     * Loads a resource.
     *
     * @param  mixed $resource A resource
     *
     * @return RouteCollection A RouteCollection instance
     */
    function load($resource);

    /**
     * Returns true if this class supports the given resource.
     *
     * @param  mixed $resource A resource
     *
     * @return Boolean true if this class supports the given resource, false otherwise
     */
    function supports($resource);

    /**
     * Gets the loader resolver.
     *
     * @return LoaderResolver A LoaderResolver instance
     */
    function getResolver();

    /**
     * Sets the loader resolver.
     *
     * @param LoaderResolver $resolver A LoaderResolver instance
     */
    function setResolver(LoaderResolver $resolver);
}
