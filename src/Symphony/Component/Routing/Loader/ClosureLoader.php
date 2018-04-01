<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\Routing\Loader;

use Symphony\Component\Config\Loader\Loader;
use Symphony\Component\Routing\RouteCollection;

/**
 * ClosureLoader loads routes from a PHP closure.
 *
 * The Closure must return a RouteCollection instance.
 *
 * @author Fabien Potencier <fabien@symphony.com>
 */
class ClosureLoader extends Loader
{
    /**
     * Loads a Closure.
     *
     * @param \Closure    $closure A Closure
     * @param string|null $type    The resource type
     *
     * @return RouteCollection A RouteCollection instance
     */
    public function load($closure, $type = null)
    {
        return $closure();
    }

    /**
     * {@inheritdoc}
     */
    public function supports($resource, $type = null)
    {
        return $resource instanceof \Closure && (!$type || 'closure' === $type);
    }
}
