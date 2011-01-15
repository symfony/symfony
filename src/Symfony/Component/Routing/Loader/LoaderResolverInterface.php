<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Routing\Loader;

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
