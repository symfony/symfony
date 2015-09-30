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

use Symfony\Component\Config\Exception\FileLoaderLoadException;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\Config\Resource\ResourceInterface;
use Symfony\Component\Routing\RouteCollection;
use Symfony\Component\Routing\Route;

/**
 * Helps add and import routes into a RouteCollection.
 *
 * @author Ryan Weaver <ryan@knpuniversity.com>
 */
class RouteCollectionBuilder
{
    /**
     * A mixture of different objects that hold routes.
     *
     * @var Route[]|RouteCollectionBuilder[]
     */
    private $routes = array();

    private $loader;
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
     * @param LoaderInterface $loader
     */
    public function __construct(LoaderInterface $loader = null)
    {
        $this->loader = $loader;
    }

    /**
     * Import an external routing resource, like a file.
     *
     * Returns a RouteCollectionBuilder so you can continue to tweak options on the routes.
     *
     * @param mixed  $resource
     * @param string $prefix
     * @param string $type
     *
     * @return RouteCollectionBuilder
     */
    public function import($resource, $prefix = null, $type = null)
    {
        /** @var RouteCollection $subCollection */
        $subCollection = $this->resolve($resource, $type)->load($resource, $type);

        return $this->addRouteCollection($subCollection, $prefix);
    }

    /**
     * Adds a route and returns it for future modification.
     *
     * @param string      $path       The route path
     * @param string      $controller The route controller string
     * @param string|null $name       The name to give this route
     *
     * @return Route
     */
    public function add($path, $controller, $name = null)
    {
        $route = new Route($path);
        $route->setDefault('_controller', $controller);
        $this->addRoute($route, $name);

        return $route;
    }

    /**
     * Returns a RouteCollectionBuilder that can be configured and then added with addBuilder().
     *
     * @param string $prefix A prefix to apply to all routes added to this collection
     *
     * @return RouteCollectionBuilder
     */
    public function createBuilder($prefix = null)
    {
        $builder = new self($this->loader);
        $builder->setPrefix($prefix);

        return $builder;
    }

    /**
     * Add a RouteCollectionBuilder.
     *
     * @param RouteCollectionBuilder $builder
     */
    public function addBuilder(RouteCollectionBuilder $builder)
    {
        $this->routes[] = $builder;
    }

    /**
     * Adds a RouteCollection directly and returns those routes in a RouteCollectionBuilder.
     *
     * @param RouteCollection $collection
     * @param string|null     $prefix
     *
     * @return $this
     */
    public function addRouteCollection(RouteCollection $collection, $prefix = null)
    {
        // create a builder from the RouteCollection
        $builder = $this->createBuilder($prefix);
        foreach ($collection->all() as $name => $route) {
            $builder->addRoute($route, $name);
        }

        foreach ($collection->getResources() as $resource) {
            $builder->addResource($resource);
        }

        $this->addBuilder($builder);

        return $builder;
    }

    /**
     * Adds a Route object to the builder.
     *
     * @param Route       $route
     * @param string|null $name
     *
     * @return $this
     */
    public function addRoute(Route $route, $name = null)
    {
        if (null === $name) {
            // used as a flag to know which routes will need a name later
            $name = '_unnamed_route_'.spl_object_hash($route);
        }

        $this->routes[$name] = $route;

        return $this;
    }

    /**
     * Sets a prefix (e.g. /admin) to be used with all embedded routes.
     *
     * @param string $prefix
     *
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
     *
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
     *
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
     * @param mixed  $value
     *
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
     * @param mixed  $regex
     *
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
     * @param mixed  $value
     *
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
     *
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
     *
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
     * Set a controller class that all added embedded routes should use.
     *
     * With this, the controller for embedded routes can just be a method name.
     * If an embedded route has a full controller (e.g. class::methodName), the
     * controllerClass won't be applied to that route.
     *
     * @param string $controllerClass
     *
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
    public function build()
    {
        $routeCollection = new RouteCollection();

        foreach ($this->routes as $name => $route) {
            if ($route instanceof Route) {
                // auto-generate the route name if it's been marked
                if ('_unnamed_route_' === substr($name, 0, 15)) {
                    $name = $this->generateRouteName($route);
                }

                $this->ensureRouteController($route);

                $route->setDefaults(array_merge($this->defaults, $route->getDefaults()));
                $route->setOptions(array_merge($this->options, $route->getOptions()));

                // we're extra careful here to avoid re-setting deprecated _method and _scheme
                foreach ($this->requirements as $key => $val) {
                    if (!$route->hasRequirement($key)) {
                        $route->setRequirement($key, $val);
                    }
                }

                if (null !== $this->prefix) {
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
            } else {
                /* @var self $route */

                $subCollection = $route->build();
                $subCollection->addPrefix($this->prefix);

                $routeCollection->addCollection($subCollection);
            }
        }

        foreach ($this->resources as $resource) {
            $routeCollection->addResource($resource);
        }

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

    /**
     * Generates a route name based on details of this route.
     *
     * @return string
     */
    private function generateRouteName(Route $route)
    {
        $methods = implode('_', $route->getMethods()).'_';

        $routeName = $methods.$route->getPath();
        $routeName = str_replace(array('/', ':', '|', '-'), '_', $routeName);
        $routeName = preg_replace('/[^a-z0-9A-Z_.]+/', '', $routeName);

        // Collapse consecutive underscores down into a single underscore.
        $routeName = preg_replace('/_+/', '_', $routeName);

        return $routeName;
    }

    /**
     * Finds a loader able to load an imported resource.
     *
     * @param mixed       $resource A resource
     * @param string|null $type     The resource type or null if unknown
     *
     * @return LoaderInterface A LoaderInterface instance
     *
     * @throws FileLoaderLoadException If no loader is found
     */
    private function resolve($resource, $type = null)
    {
        if (null === $this->loader) {
            throw new \BadMethodCallException('Cannot import other routing resources: you must pass a LoaderInterface when constructing RouteCollectionBuilder.');
        }

        if ($this->loader->supports($resource, $type)) {
            return $this->loader;
        }

        $loader = null === $this->loader->getResolver() ? false : $this->loader->getResolver()->resolve($resource, $type);

        if (false === $loader) {
            throw new FileLoaderLoadException($resource);
        }

        return $loader;
    }
}
