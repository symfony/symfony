<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\Routing\Loader\Configurator;

use Symphony\Component\Routing\RouteCollection;

/**
 * @author Nicolas Grekas <p@tchwork.com>
 */
class RouteConfigurator
{
    use Traits\AddTrait;
    use Traits\RouteTrait;

    private $parentConfigurator;

    public function __construct(RouteCollection $collection, $route, string $name = '', CollectionConfigurator $parentConfigurator = null, array $prefixes = null)
    {
        $this->collection = $collection;
        $this->route = $route;
        $this->name = $name;
        $this->parentConfigurator = $parentConfigurator; // for GC control
        $this->prefixes = $prefixes;
    }
}
