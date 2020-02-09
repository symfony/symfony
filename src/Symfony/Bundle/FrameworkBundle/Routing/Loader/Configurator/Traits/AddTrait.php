<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Routing\Loader\Configurator\Traits;

use Symfony\Bundle\FrameworkBundle\Routing\Loader\Configurator\RouteConfigurator;
use Symfony\Component\Routing\Loader\Configurator\CollectionConfigurator;
use Symfony\Component\Routing\Loader\Configurator\RouteConfigurator as BaseRouteConfigurator;

trait AddTrait
{
    /**
     * Adds a route.
     *
     * @param string|array $path the path, or the localized paths of the route
     *
     * @return RouteConfigurator
     */
    public function add(string $name, $path): BaseRouteConfigurator
    {
        $parentConfigurator = $this instanceof CollectionConfigurator ? $this : ($this instanceof RouteConfigurator ? $this->parentConfigurator : null);
        $route = $this->createLocalizedRoute($this->collection, $name, $path, $this->name, $this->prefixes);

        return new RouteConfigurator($this->collection, $route, $this->name, $parentConfigurator, $this->prefixes);
    }

    /**
     * Adds a route.
     *
     * @param string|array $path the path, or the localized paths of the route
     *
     * @return RouteConfigurator
     */
    final public function __invoke(string $name, $path): BaseRouteConfigurator
    {
        return $this->add($name, $path);
    }
}
