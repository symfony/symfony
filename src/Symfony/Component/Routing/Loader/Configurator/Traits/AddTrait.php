<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Routing\Loader\Configurator\Traits;

use Symfony\Component\Routing\Loader\Configurator\RouteConfigurator;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

trait AddTrait
{
    /**
     * @var RouteCollection
     */
    private $collection;

    private $name = '';

    /**
     * Adds a route.
     */
    final public function add(string $name, string $path): RouteConfigurator
    {
        $parentConfigurator = $this instanceof RouteConfigurator ? $this->parentConfigurator : null;
        $this->collection->add($this->name.$name, $route = new Route($path));

        return new RouteConfigurator($this->collection, $route, '', $parentConfigurator);
    }

    /**
     * Adds a route.
     */
    final public function __invoke(string $name, string $path): RouteConfigurator
    {
        return $this->add($name, $path);
    }
}
