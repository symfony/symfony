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
 * When adding a route at the end of the collection, an existing route
 * with the same name is removed first. So there can only be one route
 * with a given name.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Tobias Schultze <http://tobion.de>
 *
 * @api
 */
class RouteCollection implements \IteratorAggregate, \Countable
{
    /**
     * @var Route[]
     */
    private $routes = array();

    /**
     * @var array
     */
    private $resources = array();

    /**
     * @var string
     */
    private $prefix = '';

    /**
     * @var RouteCollection|null
     * @deprecated since version 2.2, will be removed in 2.3
     */
    private $parent;

    public function __clone()
    {
        foreach ($this->routes as $name => $route) {
            $this->routes[$name] = clone $route;
        }
    }

    /**
     * Gets the parent RouteCollection.
     *
     * @return RouteCollection|null The parent RouteCollection or null when it's the root
     *
     * @deprecated since version 2.2, will be removed in 2.3
     */
    public function getParent()
    {
        return $this->parent;
    }

    /**
     * Gets the root RouteCollection.
     *
     * @return RouteCollection The root RouteCollection
     *
     * @deprecated since version 2.2, will be removed in 2.3
     */
    public function getRoot()
    {
        $parent = $this;
        while ($parent->getParent()) {
            $parent = $parent->getParent();
        }

        return $parent;
    }

    /**
     * Gets the current RouteCollection as an Iterator that includes all routes.
     * 
     * It implements \IteratorAggregate.
     *
     * @see all()
     *
     * @return \ArrayIterator An \ArrayIterator object for iterating over routes
     */
    public function getIterator()
    {
        return new \ArrayIterator($this->routes);
    }

    /**
     * Gets the number of Routes in this collection.
     *
     * @return int The number of routes
     */
    public function count()
    {
        return count($this->routes);
    }

    /**
     * Adds a route.
     *
     * @param string $name  The route name
     * @param Route  $route A Route instance
     *
     * @api
     */
    public function add($name, Route $route)
    {
        unset($this->routes[$name]);

        $this->routes[$name] = $route;
    }

    /**
     * Returns all routes in this collection.
     *
     * @return Route[] An array of routes
     */
    public function all()
    {
        return $this->routes;
    }

    /**
     * Gets a route by name.
     *
     * @param string $name The route name
     *
     * @return Route|null A Route instance or null when not found
     */
    public function get($name)
    {
        return isset($this->routes[$name]) ? $this->routes[$name] : null;
    }

    /**
     * Removes a route or an array of routes by name from the collection
     *
     * For BC it's also removed from the root, which will not be the case in 2.3
     * as the RouteCollection won't be a tree structure.
     *
     * @param string|array $name The route name or an array of route names
     */
    public function remove($name)
    {
        // just for BC
        $root = $this->getRoot();

        foreach ((array) $name as $n) {
            unset($root->routes[$n]);
            unset($this->routes[$n]);
        }
    }

    /**
     * Adds a route collection at the end of the current set by appending all
     * routes of the added collection.
     *
     * @param RouteCollection $collection      A RouteCollection instance
     * @param string          $prefix          An optional prefix to add before each pattern of the route collection
     * @param array           $defaults        An array of default values
     * @param array           $requirements    An array of requirements
     * @param array           $options         An array of options
     * @param string          $hostnamePattern Hostname pattern
     *
     * @api
     */
    public function addCollection(RouteCollection $collection, $prefix = '', $defaults = array(), $requirements = array(), $options = array(), $hostnamePattern = '')
    {
        // This is to keep BC for getParent() and getRoot(). It does not prevent
        // infinite loops by recursive referencing. But we don't need that logic
        // anymore as the tree logic has been deprecated and we are just widening
        // the accepted range.
        $collection->parent = $this;

        // the sub-collection must have the prefix of the parent (current instance) prepended because it does not
        // necessarily already have it applied (depending on the order RouteCollections are added to each other)
        $collection->addPrefix($this->getPrefix() . $prefix, $defaults, $requirements, $options);

        if ('' !== $hostnamePattern) {
            $collection->setHostnamePattern($hostnamePattern);
        }

        // we need to remove all routes with the same names first because just replacing them
        // would not place the new route at the end of the merged array
        foreach ($collection->all() as $name => $route) {
            unset($this->routes[$name]);
            $this->routes[$name] = $route;
        }

        $this->resources = array_merge($this->resources, $collection->getResources());
    }

    /**
     * Adds a prefix to all routes in the current set.
     *
     * @param string $prefix       An optional prefix to add before each pattern of the route collection
     * @param array  $defaults     An array of default values
     * @param array  $requirements An array of requirements
     * @param array  $options      An array of options
     *
     * @api
     */
    public function addPrefix($prefix, $defaults = array(), $requirements = array(), $options = array())
    {
        $prefix = trim(trim($prefix), '/');

        if ('' === $prefix && empty($defaults) && empty($requirements) && empty($options)) {
            return;
        }

        // a prefix must start with a single slash and must not end with a slash
        if ('' !== $prefix) {
            $this->prefix = '/' . $prefix . $this->prefix;
        }

        foreach ($this->routes as $route) {
            if ('' !== $prefix) {
                $route->setPattern('/' . $prefix . $route->getPattern());
            }
            $route->addDefaults($defaults);
            $route->addRequirements($requirements);
            $route->addOptions($options);
        }
    }

    /**
     * Returns the prefix that may contain placeholders.
     *
     * @return string The prefix
     */
    public function getPrefix()
    {
        return $this->prefix;
    }

    /**
     * Sets the hostname pattern on all routes.
     *
     * @param string $pattern The pattern
     */
    public function setHostnamePattern($pattern)
    {
        foreach ($this->routes as $route) {
            $route->setHostnamePattern($pattern);
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
