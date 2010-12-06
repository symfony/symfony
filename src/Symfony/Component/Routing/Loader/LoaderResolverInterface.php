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
 * LoaderResolverInterface selects a loader for a given resource.
 *
 * @author Fabien Potencier <fabien.potencier@symfony-project.com>
 */
interface LoaderResolverInterface
{
    /**
     * Returns a loader able to load the resource.
     *
     * @param mixed  $resource A resource
     * @param string $type     The resource type
     *
     * @return LoaderInterface|false A LoaderInterface instance supporting the resource if one exists, false otherwise
     */
    function resolve($resource, $type = null);
}
