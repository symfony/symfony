<?php

namespace Symfony\Component\DependencyInjection\Loader;

/*
 * This file is part of the Symfony framework.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

/**
 * LoaderResolverInterface selects a loader for a given resource.
 *
 * @author     Fabien Potencier <fabien.potencier@symfony-project.com>
 */
interface LoaderResolverInterface
{
    /**
     * Returns a loader able to load the resource.
     *
     * @param mixed  $resource A resource
     *
     * @return LoaderInterface A LoaderInterface instance
     */
    function resolve($resource);
}
