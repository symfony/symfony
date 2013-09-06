<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Config\Loader;

/**
 * LoaderResolverInterface selects a loader for a given resource.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
interface LoaderResolverInterface
{
    /**
     * Returns a loader able to load the resource.
     *
     * @param mixed  $resource A resource
     * @param string $type     The resource type
     *
     * @return LoaderInterface A LoaderInterface instance
     */
    public function resolve($resource, $type = null);
}
