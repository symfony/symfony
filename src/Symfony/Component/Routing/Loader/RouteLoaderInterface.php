<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Routing\Loader;

use Symfony\Component\Config\Loader\Loader;
use Symfony\Component\Routing\RouteCollection;

/**
 * Defines a class that is able to load and return a RouteCollection.
 *
 * @author Ryan Weaver <ryan@knpuniversity.com>
 */
interface RouteLoaderInterface
{
    /**
     * @param Loader $loader
     *
     * @return RouteCollection
     */
    public function getRouteCollection(Loader $loader);
}
