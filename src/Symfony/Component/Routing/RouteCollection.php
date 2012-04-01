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
 * When adding a route, it overrides existing routes with the
 * same name defined in the instance or its children and parents.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Tobias Schultze <http://tobion.de>
 *
 * @api
 */
class RouteCollection implements \IteratorAggregate
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
     * Sets the parent RouteCollection.
     *
     * @param RouteCollection $parent The parent RouteCollection
     */
    public function setParent(RouteCollection $parent)
    {
        $this->parent = $parent;
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

        $this->removeCompletely($name);

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
     * @param  string      $name  The route name
     *
     * @return Route|null  $route A Route instance or null when not found
     */
    public function get($name)
    {
        if (isset($this->routes[$name])) {
            return $this->routes[$name];
        } else {
            foreach ($this->routes as $routes) {
                if ($routes instanceof RouteCollection && null !== $route = $routes->get($name)) {
                    return $route;
                }
            }
        }
        
        return null;
    }

    /**
     * Removes a route by name from all connected collections (this instance and all parents and children).
     *
     * @param string $name The route name
     */
    public function removeCompletely($name)
    {
        $parent = $this;
        while ($parent->getParent()) {
            $parent = $parent->getParent();
        }

        $parent->remove($name);
    }
    
    /**
     * Removes a route by name from this collection and its children.
     *
     * @param string $name The route name
     */
    public function remove($name)
    {
        // the route can only be in this RouteCollection or one of its children (not both) because the
        // adders (->add and ->addCollection) make sure there is only one route per name in all collections     
        if (isset($this->routes[$name])) {
            unset($this->routes[$name]);
        } else {
            foreach ($this->routes as $routes) {
                if ($routes instanceof RouteCollection) {
                    $routes->remove($name);
                }
            }
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
     * @api
     */
    public function addCollection(RouteCollection $collection, $prefix = '', $defaults = array(), $requirements = array(), $options = array())
    {
        $collection->setParent($this);
        $collection->addPrefix($prefix, $defaults, $requirements, $options);

        // remove all routes with the same name in all existing collections
        foreach (array_keys($collection->all()) as $name) {
            $this->removeCompletely($name);
        }

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
        // a prefix must not end with a slash
        $prefix = rtrim($prefix, '/');

        // a prefix must start with a slash
        if ('' !== $prefix && '/' !== $prefix[0]) {
            $prefix = '/'.$prefix;
        }

        $this->prefix = $prefix.$this->prefix;

        foreach ($this->routes as $name => $route) {
            if ($route instanceof RouteCollection) {
                $route->addPrefix($prefix, $defaults, $requirements, $options);
            } else {
                $route->setPattern($prefix.$route->getPattern());
                $route->addDefaults($defaults);
                $route->addRequirements($requirements);
                $route->addOptions($options);
            }
        }
    }

    /**
     * Returns the prefix that may contain placeholders. 
     * When given, it must start with a slash and must not end with a slash.
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
        foreach ($this as $routes) {
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
}
