<?php

namespace Symfony\Component\Routing\Loader;

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
 * @author Fabien Potencier <fabien.potencier@symfony-project.com>
 */
class ClosureLoader extends Loader
{
    /**
     * Loads a Closure.
     *
     * @param \Closure $closure A Closure
     * @param string   $type    The resource type
     */
    public function load($closure, $type = null)
    {
        return call_user_func($closure);
    }

    /**
     * Returns true if this class supports the given resource.
     *
     * @param mixed  $resource A resource
     * @param string $type     The resource type
     *
     * @return boolean True if this class supports the given resource, false otherwise
     */
    public function supports($resource, $type = null)
    {
        return $resource instanceof \Closure && (!$type || 'closure' === $type);
    }
}
