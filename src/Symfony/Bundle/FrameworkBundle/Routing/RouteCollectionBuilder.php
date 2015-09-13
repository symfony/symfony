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
 * Helps add and import routes into a RouteCollection..
 *
 * @author Ryan Weaver <ryan@knpuniversity.com>
 */
class RouteCollectionBuilder
{
    private $loader;

    /**
     * A mixture of different objects that hold routes.
     *
     * @var Route[]|RouteCollectionBuilder[]|RouteCollection[]
     */
    private $routes = array();

    private $defaults = array();

    private $prefix;

    private $host;

    private $condition;

    private $requirements = array();

    private $options = array();

    private $schemes;

    private $methods;

    private $resources = array();

    private $controllerClass;

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
     * Returns a RouteCollectionBuilder so you can continue to tweak options on the routes.
     *
     * @param mixed $resource
     * @param string $prefix
     * @param string $type
     * @return RouteCollectionBuilder
     */
    public function import($resource, $prefix = null, $type = null)
    {
        /** @var RouteCollection $subCollection */
        $subCollection = $this->loader->import($resource, $type);

        // turn this into a RouteCollectionBuilder
        $builder = new RouteCollectionBuilder($this->loader);
        $builder->setPrefix($prefix);
        $builder->addRouteCollection($subCollection);
        $this->routes[] = $builder;

        return $builder;
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
        // what if we have some other Route sub-class in our project: CmsRoute
        // is it really ok to copy all the CmsRoute objects to our Route?
        // -> we MUST somehow allow Component Route objects (no name attached)

        $route = new Route($path);
        $route->setController($controller);
        $route->setName($name);
        $this->routes[] = $route;

        return $route;
    }

    /**
     * Returns a RouteCollectionBuilder that can be configured and then added with mount()
     *
     * @return RouteCollectionBuilder
     */
    public function createCollection()
    {
        return new RouteCollectionBuilder($this->loader);
    }

    /**
     * Add a RouteCollectionBuilder.
     *
     * @param $prefix
     * @param RouteCollectionBuilder $routes
     */
    public function mount($prefix, RouteCollectionBuilder $routes)
    {
        $routes->setPrefix($prefix);
        $this->routes[] = $routes;
    }

    /**
     * Adds a RouteCollection directly.
     *
     * @param RouteCollection $collection
     */
    public function addRouteCollection(RouteCollection $collection)
    {
        $this->routes[] = $collection;
    }

    /**
     * Sets a prefix (e.g. /admin) to be used with all embedded routes.
     *
     * @param string $prefix
     * @return $this
     */
    public function setPrefix($prefix)
    {
        $this->prefix = trim(trim($prefix), '/');

        return $this;
    }

    /**
     * Sets the host on all embedded routes (unless already set).
     *
     * @param string $pattern
     * @return $this
     */
    public function setHost($pattern)
    {
        $this->host = $pattern;

        return $this;
    }

    /**
     * Sets a condition on all embedded routes (unless already set).
     *
     * @param string $condition
     * @return $this
     */
    public function setCondition($condition)
    {
        $this->condition = $condition;

        return $this;
    }

    /**
     * Sets a default value that will be added to all embedded routes (unless that
     * default value is already set.
     *
     * @param string $key
     * @param mixed $value
     * @return $this
     */
    public function setDefault($key, $value)
    {
        $this->defaults[$key] = $value;

        return $this;
    }

    /**
     * Sets a requirement that will be added to all embedded routes (unless that
     * requirement is already set.
     *
     * @param string $key
     * @param mixed $value
     * @return $this
     */
    public function setRequirement($key, $regex)
    {
        $this->requirements[$key] = $regex;

        return $this;
    }

    /**
     * Sets an opiton that will be added to all embedded routes (unless that
     * option is already set.
     *
     * @param string $key
     * @param mixed $value
     * @return $this
     */
    public function setOption($key, $value)
    {
        $this->options[$key] = $value;

        return $this;
    }

    /**
     * Sets the schemes on all embedded routes (unless already set).
     *
     * @param array|string $schemes
     * @return $this
     */
    public function setSchemes($schemes)
    {
        $this->schemes = $schemes;

        return $this;
    }

    /**
     * Sets the methods on all embedded routes (unless already set).
     *
     * @param array|string $methods
     * @return $this
     */
    public function setMethods($methods)
    {
        $this->methods = $methods;

        return $this;
    }

    /**
     * Adds a resource for this collection.
     *
     * @return $this
     */
    public function addResource(ResourceInterface $resource)
    {
        $this->resources[] = $resource;

        return $this;
    }

    /**
     * Set a controller class that all added embedded should use.
     *
     * With this, the controller for embedded routes can just be a method name.
     * If an embedded route has a full controller (e.g. class::methodName), the
     * controllerClass won't be applied to that route.
     *
     * @param string $controllerClass
     * @return $this
     */
    public function setControllerClass($controllerClass)
    {
        if (!class_exists($controllerClass)) {
            throw new \InvalidArgumentException(sprintf('The controller class "%s" does not exist.', $controllerClass));
        }

        $this->controllerClass = $controllerClass;

        return $this;
    }

    /**
     * Creates the final ArrayCollection, returns it, and clears everything.
     *
     * @return RouteCollection
     */
    public function flush()
    {
        $routeCollection = new RouteCollection();

        foreach ($this->routes as $route) {
            if ($route instanceof Route) {
                // auto-generate the route name if needed
                if (!$name = $route->getName()) {
                    $name = $route->generateRouteName();
                }

                $this->ensureRouteController($route);

                $route->setDefaults(array_merge($this->defaults, $route->getDefaults()));
                $route->setRequirements(array_merge($this->requirements, $route->getRequirements()));
                $route->setOptions(array_merge($this->options, $route->getOptions()));

                if ($this->prefix) {
                    $route->setPath('/'.$this->prefix.$route->getPath());
                }

                if (!$route->getHost()) {
                    $route->setHost($this->host);
                }

                if (!$route->getCondition()) {
                    $route->setCondition($this->condition);
                }

                if (!$route->getSchemes()) {
                    $route->setSchemes($this->schemes);
                }

                if (!$route->getMethods()) {
                    $route->setMethods($this->methods);
                }

                $routeCollection->add($name, $route);
            } elseif ($route instanceof RouteCollectionBuilder) {
                $subCollection = $route->flush();

                $routeCollection->addCollection($subCollection);
            } else {
                /** @var RouteCollection $route */

                $routeCollection->addCollection($route);
            }
        }

        foreach ($this->resources as $resource) {
            $routeCollection->addResource($resource);
        }

        // reset all the values
        $this->routes = array();
        $this->resources = array();
        $this->defaults = array();
        $this->options = array();
        $this->requirements = array();
        $this->prefix = null;
        $this->host = null;
        $this->condition = null;
        $this->schemes = null;
        $this->methods = null;
        $this->controllerClass = null;

        return $routeCollection;
    }

    /**
     * Attempts to safely prefix controllers with the controller class if necessary.
     *
     * @param Route $route
     */
    private function ensureRouteController(Route $route)
    {
        // only do work if there is a controller class set
        if (!$this->controllerClass) {
            return;
        }

        $controller = $route->getDefault('_controller');

        // only apply controller class to a (non-empty) string
        if (!is_string($controller) || !$controller) {
            return;
        }

        // is the controller already a callable function/class?
        if (method_exists($controller, '__invoke') || function_exists($controller)) {
            return;
        }

        // is this already a controller format (a:b:c, or a:b, or a::b)?
        if (false !== strpos($controller, ':')) {
            return;
        }

        $controller = sprintf('%s::%s', $this->controllerClass, $controller);
        $route->setDefault('_controller', $controller);
    }
}
