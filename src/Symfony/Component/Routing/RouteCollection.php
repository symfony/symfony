<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Routing;

use Symfony\Component\Config\Resource\ResourceInterface;

/**
 * A RouteCollection represents a set of Route instances.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class RouteCollection
{
    private $routes;
    private $resources;

    /**
     * Constructor.
     */
    public function __construct()
    {
        $this->routes = array();
        $this->resources = array();
    }

    /**
     * Adds a route.
     *
     * @param string $name  The route name
     * @param Route  $route A Route instance
     *
     * @throws \InvalidArgumentException When route name contains non valid characters
     */
    public function add($name, Route $route)
    {
        if (!preg_match('/^[a-z0-9A-Z_.]+$/', $name)) {
            throw new \InvalidArgumentException(sprintf('Name "%s" contains non valid characters for a route name.', $name));
        }

        $this->routes[$name] = $route;
    }

    /**
     * Returns the array of routes.
     *
     * @return array An array of routes
     */
    public function all()
    {
        return $this->routes;
    }

    /**
     * Gets a route by name.
     *
     * @param  string $name  The route name
     *
     * @return Route  $route A Route instance
     */
    public function get($name)
    {
        return isset($this->routes[$name]) ? $this->routes[$name] : null;
    }

    /**
     * Adds a route collection to the current set of routes (at the end of the current set).
     *
     * @param RouteCollection $collection A RouteCollection instance
     * @param string          $prefix     An optional prefix to add before each pattern of the route collection
     */
    public function addCollection(RouteCollection $collection, $prefix = '')
    {
        $collection->addPrefix($prefix);

        foreach ($collection->getResources() as $resource) {
            $this->addResource($resource);
        }

        $this->routes = array_merge($this->routes, $collection->all());
    }

    /**
     * Adds a prefix to all routes in the current set.
     *
     * @param string          $prefix     An optional prefix to add before each pattern of the route collection
     */
    public function addPrefix($prefix)
    {
        if (!$prefix) {
            return;
        }

        foreach ($this->all() as $route) {
            $route->setPattern($prefix.$route->getPattern());
        }
    }

    /**
     * Returns an array of resources loaded to build this collection.
     *
     * @return ResourceInterface[] An array of resources
     */
    public function getResources()
    {
        return array_unique($this->resources);
    }

    /**
     * Adds a resource for this collection.
     *
     * @param ResourceInterface $resource A resource instance
     */
    public function addResource(ResourceInterface $resource)
    {
        $this->resources[] = $resource;
    }
}
