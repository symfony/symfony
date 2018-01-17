<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Routing\Loader\Configurator;

use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

/**
 * @author Nicolas Grekas <p@tchwork.com>
 */
class CollectionConfigurator
{
    use Traits\AddTrait;
    use Traits\RouteTrait;

    private $parent;

    public function __construct(RouteCollection $parent, string $name)
    {
        $this->parent = $parent;
        $this->name = $name;
        $this->collection = new RouteCollection();
        $this->route = new Route('');
    }

    public function __destruct()
    {
        $this->collection->addPrefix(rtrim($this->route->getPath(), '/'));
        $this->parent->addCollection($this->collection);
    }

    /**
     * Adds a route.
     */
    final public function add(string $name, string $path): RouteConfigurator
    {
        $this->collection->add($this->name.$name, $route = clone $this->route);

        return new RouteConfigurator($this->collection, $route->setPath($path), $this->name);
    }

    /**
     * Creates a sub-collection.
     *
     * @return self
     */
    final public function collection($name = '')
    {
        return new self($this->collection, $this->name.$name);
    }

    /**
     * Sets the prefix to add to the path of all child routes.
     *
     * @return $this
     */
    final public function prefix(string $prefix)
    {
        $this->route->setPath($prefix);

        return $this;
    }
}
