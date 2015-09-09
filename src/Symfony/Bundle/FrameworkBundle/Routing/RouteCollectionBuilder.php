<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Routing;

use Symfony\Component\Config\Loader\Loader;
use Symfony\Component\Config\Resource\ResourceInterface;
use Symfony\Component\Routing\RouteCollection;

/**
 * Helps add and import routes into a RouteCollection.
 *
 * @author Ryan Weaver <ryan@knpuniversity.com>
 */
class RouteCollectionBuilder
{
    private $loader;

    private $routes = array();

    private $resources = array();

    private $defaults = array();

    /**
     * @param Loader $loader
     */
    public function __construct(Loader $loader)
    {
        $this->loader = $loader;
    }

    /**
     * Import an external routing resource, like a file.
     *
     * @param mixed $resource
     * @param string $prefix
     * @param string $type
     * @return RouteCollection
     */
    public function import($resource, $prefix = null, $type = null)
    {
        /** @var RouteCollection $subCollection */
        $subCollection = $this->loader->import($resource, $type);
        $subCollection->addPrefix($prefix);

        $this->routes[] = $subCollection;

        // return the collection so more options can be added to it
        return $subCollection;
    }

    /**
     * Adds a route and returns it for future modification.
     *
     * @param string $path          The route path
     * @param string $controller    The route controller string
     * @param string $name          The name to give this route
     * @return Route
     */
    public function add($path, $controller, $name = null)
    {
        $route = new Route($path);
        $route->setController($controller);
        $route->setName($name);

        $this->routes[] = $route;

        return $route;
    }

    /**
     * Add a raw RouteCollection
     *
     * @param RouteCollection $collection
     */
    public function addCollection(RouteCollection $collection)
    {
        $this->routes[] = $collection;
    }

    /**
     * Add some default values to all routes.
     *
     * @param array $defaults
     */
    public function addDefaults(array $defaults)
    {
        $this->defaults = $defaults;
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
     * Creates the final ArrayCollection, returns it, and clears everything.
     *
     * @return RouteCollection
     */
    public function flush()
    {
        $routes = new RouteCollection();

        foreach ($this->routes as $route) {
            if ($route instanceof Route) {
                // add the single route
                if (!$name = $route->getName()) {
                    // auto-generate a route name
                    $name = $route->generateRouteName();
                }

                $routes->add($name, $route);
            } else {
                // $route is actually a RouteCollection
                $routes->addCollection($route);
            }
        }

        $routes->addDefaults($this->defaults);

        foreach ($this->resources as $resource) {
            $routes->addResource($resource);
        }

        // reset all the values
        $this->defaults = array();
        $this->resources = array();
        $this->routes = array();

        return $routes;
    }
}
