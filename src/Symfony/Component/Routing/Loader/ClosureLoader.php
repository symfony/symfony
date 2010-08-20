<?php

namespace Symfony\Component\Routing\Loader;

use Symfony\Component\Routing\Resource\FileResource;

/*
 * This file is part of the Symfony framework.
 *
 * The Closure must return a RouteCollection instance.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

/**
 * ClosureLoader loads routes from a PHP closure.
 *
 * @author     Fabien Potencier <fabien.potencier@symfony-project.com>
 */
class ClosureLoader extends Loader
{
    /**
     * Loads a Closure.
     *
     * @param \Closure $resource The resource
     */
    public function load($closure)
    {
        return call_user_func($closure);
    }

    /**
     * Returns true if this class supports the given resource.
     *
     * @param  mixed $resource A resource
     *
     * @return Boolean true if this class supports the given resource, false otherwise
     */
    public function supports($resource)
    {
        return $resource instanceof \Closure;
    }
}
