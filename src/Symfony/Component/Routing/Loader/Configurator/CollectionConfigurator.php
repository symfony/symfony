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
    private $parentConfigurator;

    public function __construct(RouteCollection $parent, $name, self $parentConfigurator = null)
    {
        $this->parent = $parent;
        $this->name = $name;
        $this->collection = new RouteCollection();
        $this->route = new Route('');
        $this->parentConfigurator = $parentConfigurator; // for GC control
    }

    public function __destruct()
    {
        $this->collection->addPrefix(rtrim($this->route->getPath(), '/'));
        $this->parent->addCollection($this->collection);
    }

    /**
     * Adds a route.
     *
     * @param string $name
     * @param string $path
     *
     * @return RouteConfigurator
     */
    final public function add($name, $path)
    {
        $this->collection->add($this->name.$name, $route = clone $this->route);

        return new RouteConfigurator($this->collection, $route->setPath($path), $this->name, $this);
    }

    /**
     * Creates a sub-collection.
     *
     * @return self
     */
    final public function collection($name = '')
    {
        return new self($this->collection, $this->name.$name, $this);
    }

    /**
     * Sets the prefix to add to the path of all child routes.
     *
     * @param string $prefix
     *
     * @return $this
     */
    final public function prefix($prefix)
    {
        $this->route->setPath($prefix);

        return $this;
    }
}
