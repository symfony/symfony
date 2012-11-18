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
 * A RouteCollection represents a set of Route instances as a tree structure.
 *
 * When adding a route, it overrides existing routes with the
 * same name defined in the instance or its children and parents.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Tobias Schultze <http://tobion.de>
 *
 * @api
 */
class RouteCollection implements \IteratorAggregate, \Countable
{
    private $routes;
    private $resources;
    private $prefix;
    private $parent;

    /**
     * Constructor.
     *
     * @api
     */
    public function __construct()
    {
        $this->routes = array();
        $this->resources = array();
        $this->prefix = '';
    }

    public function __clone()
    {
        foreach ($this->routes as $name => $route) {
            $this->routes[$name] = clone $route;
            if ($route instanceof RouteCollection) {
                $this->routes[$name]->setParent($this);
            }
        }
    }

    /**
     * Gets the parent RouteCollection.
     *
     * @return RouteCollection|null The parent RouteCollection or null when it's the root
     */
    public function getParent()
    {
        return $this->parent;
    }

    /**
     * Gets the root RouteCollection of the tree.
     *
     * @return RouteCollection The root RouteCollection
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
     * Gets the current RouteCollection as an Iterator that includes all routes and child route collections.
     *
     * @return \ArrayIterator An \ArrayIterator interface
     */
    public function getIterator()
    {
        return new \ArrayIterator($this->routes);
    }

    /**
     * Gets the number of Routes in this collection.
     *
     * @return int The number of routes in this collection, including nested collections
     */
    public function count()
    {
        $count = 0;
        foreach ($this->routes as $route) {
            $count += $route instanceof RouteCollection ? count($route) : 1;
        }

        return $count;
    }

    /**
     * Adds a route.
     *
     * @param string $name  The route name
     * @param Route  $route A Route instance
     *
     * @throws \InvalidArgumentException When route name contains non valid characters
     *
     * @api
     */
    public function add($name, Route $route)
    {
        if (!preg_match('/^[a-z0-9A-Z_.]+$/', $name)) {
            throw new \InvalidArgumentException(sprintf('The provided route name "%s" contains non valid characters. A route name must only contain digits (0-9), letters (a-z and A-Z), underscores (_) and dots (.).', $name));
        }

        $this->remove($name);

        $this->routes[$name] = $route;
    }

    /**
     * Returns all routes in this collection and its children.
     *
     * @return array An array of routes
     */
    public function all()
    {
        $routes = array();
        foreach ($this->routes as $name => $route) {
            if ($route instanceof RouteCollection) {
                $routes = array_merge($routes, $route->all());
            } else {
                $routes[$name] = $route;
            }
        }

        return $routes;
    }

    /**
     * Gets a route by name defined in this collection or its children.
     *
     * @param string $name The route name
     *
     * @return Route|null A Route instance or null when not found
     */
    public function get($name)
    {
        if (isset($this->routes[$name])) {
            return $this->routes[$name] instanceof RouteCollection ? null : $this->routes[$name];
        }

        foreach ($this->routes as $routes) {
            if ($routes instanceof RouteCollection && null !== $route = $routes->get($name)) {
                return $route;
            }
        }

        return null;
    }

    /**
     * Removes a route or an array of routes by name from all connected
     * collections (this instance and all parents and children).
     *
     * @param string|array $name The route name or an array of route names
     */
    public function remove($name)
    {
        $root = $this->getRoot();

        foreach ((array) $name as $n) {
            $root->removeRecursively($n);
        }
    }

    /**
     * Adds a route collection to the current set of routes (at the end of the current set).
     *
     * @param RouteCollection $collection   A RouteCollection instance
     * @param string          $prefix       An optional prefix to add before each pattern of the route collection
     * @param array           $defaults     An array of default values
     * @param array           $requirements An array of requirements
     * @param array           $options      An array of options
     *
     * @throws \InvalidArgumentException When the RouteCollection already exists in the tree
     *
     * @api
     */
    public function addCollection(RouteCollection $collection, $prefix = '', $defaults = array(), $requirements = array(), $options = array())
    {
        // prevent infinite loops by recursive referencing
        $root = $this->getRoot();
        if ($root === $collection || $root->hasCollection($collection)) {
            throw new \InvalidArgumentException('The RouteCollection already exists in the tree.');
        }

        // remove all routes with the same names in all existing collections
        $this->remove(array_keys($collection->all()));

        $collection->setParent($this);
        // the sub-collection must have the prefix of the parent (current instance) prepended because it does not
        // necessarily already have it applied (depending on the order RouteCollections are added to each other)
        $collection->addPrefix($this->getPrefix() . $prefix, $defaults, $requirements, $options);
        $this->routes[] = $collection;
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
            if ($route instanceof RouteCollection) {
                // we add the slashes so the prefix is not lost by trimming in the sub-collection
                $route->addPrefix('/' . $prefix . '/', $defaults, $requirements, $options);
            } else {
                if ('' !== $prefix) {
                    $route->setPattern('/' . $prefix . $route->getPattern());
                }
                $route->addDefaults($defaults);
                $route->addRequirements($requirements);
                $route->addOptions($options);
            }
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
     * Returns an array of resources loaded to build this collection.
     *
     * @return ResourceInterface[] An array of resources
     */
    public function getResources()
    {
        $resources = $this->resources;
        foreach ($this->routes as $routes) {
            if ($routes instanceof RouteCollection) {
                $resources = array_merge($resources, $routes->getResources());
            }
        }

        return array_unique($resources);
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

    /**
     * Sets the parent RouteCollection. It's only used internally from one RouteCollection
     * to another. It makes no sense to be available as part of the public API.
     *
     * @param RouteCollection $parent The parent RouteCollection
     */
    private function setParent(RouteCollection $parent)
    {
        $this->parent = $parent;
    }

    /**
     * Removes a route by name from this collection and its children recursively.
     *
     * @param string $name The route name
     *
     * @return Boolean true when found
     */
    private function removeRecursively($name)
    {
        // It is ensured by the adders (->add and ->addCollection) that there can
        // only be one route per name in all connected collections. So we can stop
        // iterating recursively on the first hit.
        if (isset($this->routes[$name])) {
            unset($this->routes[$name]);

            return true;
        }

        foreach ($this->routes as $routes) {
            if ($routes instanceof RouteCollection && $routes->removeRecursively($name)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Checks whether the given RouteCollection is already set in any child of the current instance.
     *
     * @param RouteCollection $collection A RouteCollection instance
     *
     * @return Boolean
     */
    private function hasCollection(RouteCollection $collection)
    {
        foreach ($this->routes as $routes) {
            if ($routes === $collection || $routes instanceof RouteCollection && $routes->hasCollection($collection)) {
                return true;
            }
        }

        return false;
    }
}
