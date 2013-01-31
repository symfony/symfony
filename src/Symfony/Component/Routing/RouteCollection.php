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
     * @deprecated since version 2.2, will be removed in 2.3
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
     *
     * @api
     */
    public function addCollection(RouteCollection $collection)
    {
        // This is to keep BC for getParent() and getRoot(). It does not prevent
        // infinite loops by recursive referencing. But we don't need that logic
        // anymore as the tree logic has been deprecated and we are just widening
        // the accepted range.
        $collection->parent = $this;

        // this is to keep BC
        $numargs = func_num_args();
        if ($numargs > 1) {
            $collection->addPrefix($this->prefix . func_get_arg(1));
            if ($numargs > 2) {
                $collection->addDefaults(func_get_arg(2));
                if ($numargs > 3) {
                    $collection->addRequirements(func_get_arg(3));
                    if ($numargs > 4) {
                        $collection->addOptions(func_get_arg(4));
                    }
                }
            }
        } else {
            // the sub-collection must have the prefix of the parent (current instance) prepended because it does not
            // necessarily already have it applied (depending on the order RouteCollections are added to each other)
            // this will be removed when the BC layer for getPrefix() is removed
            $collection->addPrefix($this->prefix);
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
     * Adds a prefix to the path of all child routes.
     *
     * @param string $prefix       An optional prefix to add before each pattern of the route collection
     * @param array  $defaults     An array of default values
     * @param array  $requirements An array of requirements
     *
     * @api
     */
    public function addPrefix($prefix, array $defaults = array(), array $requirements = array())
    {
        $prefix = trim(trim($prefix), '/');

        if ('' === $prefix) {
            return;
        }

        // a prefix must start with a single slash and must not end with a slash
        $this->prefix = '/' . $prefix . $this->prefix;

        // this is to keep BC
        $options = func_num_args() > 3 ? func_get_arg(3) : array();

        foreach ($this->routes as $route) {
            $route->setPath('/' . $prefix . $route->getPath());
            $route->addDefaults($defaults);
            $route->addRequirements($requirements);
            $route->addOptions($options);
        }
    }

    /**
     * Returns the prefix that may contain placeholders.
     *
     * @return string The prefix
     *
     * @deprecated since version 2.2, will be removed in 2.3
     */
    public function getPrefix()
    {
        return $this->prefix;
    }

    /**
     * Sets the host pattern on all routes.
     *
     * @param string $pattern      The pattern
     * @param array  $defaults     An array of default values
     * @param array  $requirements An array of requirements
     */
    public function setHost($pattern, array $defaults = array(), array $requirements = array())
    {
        foreach ($this->routes as $route) {
            $route->setHost($pattern);
            $route->addDefaults($defaults);
            $route->addRequirements($requirements);
        }
    }

    /**
     * Adds defaults to all routes.
     *
     * An existing default value under the same name in a route will be overridden.
     *
     * @param array $defaults An array of default values
     */
    public function addDefaults(array $defaults)
    {
        if ($defaults) {
            foreach ($this->routes as $route) {
                $route->addDefaults($defaults);
            }
        }
    }

    /**
     * Adds requirements to all routes.
     *
     * An existing requirement under the same name in a route will be overridden.
     *
     * @param array $requirements An array of requirements
     */
    public function addRequirements(array $requirements)
    {
        if ($requirements) {
            foreach ($this->routes as $route) {
                $route->addRequirements($requirements);
            }
        }
    }

    /**
     * Adds options to all routes.
     *
     * An existing option value under the same name in a route will be overridden.
     *
     * @param array $options An array of options
     */
    public function addOptions(array $options)
    {
        if ($options) {
            foreach ($this->routes as $route) {
                $route->addOptions($options);
            }
        }
    }

    /**
     * Sets the schemes (e.g. 'https') all child routes are restricted to.
     *
     * @param string|array $schemes The scheme or an array of schemes
     */
    public function setSchemes($schemes)
    {
        foreach ($this->routes as $route) {
            $route->setSchemes($schemes);
        }
    }

    /**
     * Sets the HTTP methods (e.g. 'POST') all child routes are restricted to.
     *
     * @param string|array $methods The method or an array of methods
     */
    public function setMethods($methods)
    {
        foreach ($this->routes as $route) {
            $route->setMethods($methods);
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
