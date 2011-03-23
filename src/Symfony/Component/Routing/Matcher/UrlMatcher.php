<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Routing\Matcher;

use Symfony\Component\Routing\Matcher\Exception\MethodNotAllowedException;
use Symfony\Component\Routing\Matcher\Exception\NotFoundException;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

/**
 * UrlMatcher matches URL based on a set of routes.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class UrlMatcher implements UrlMatcherInterface
{
    protected $defaults;
    protected $context;

    private $routes;

    /**
     * Constructor.
     *
     * @param RouteCollection $routes   A RouteCollection instance
     * @param array           $context  The context
     * @param array           $defaults The default values
     */
    public function __construct(RouteCollection $routes, array $context = array(), array $defaults = array())
    {
        $this->routes = $routes;
        $this->context = $context;
        $this->defaults = $defaults;
    }

    /**
     * Sets the request context.
     *
     * @param array $context  The context
     */
    public function setContext(array $context = array())
    {
        $this->context = $context;
    }

    /**
     * Tries to match a URL with a set of routes.
     *
     * @param  string $pathinfo The path info to be parsed
     *
     * @return array An array of parameters
     *
     * @throws NotFoundException         If the resource could not be found
     * @throws MethodNotAllowedException If the resource was found but the request method is not allowed
     */
    public function match($pathinfo)
    {
        $allow = array();

        foreach ($this->routes->all() as $name => $route) {
            $compiledRoute = $route->compile();

            // check the static prefix of the URL first. Only use the more expensive preg_match when it matches
            if ('' !== $compiledRoute->getStaticPrefix() && 0 !== strpos($pathinfo, $compiledRoute->getStaticPrefix())) {
                continue;
            }

            if (!preg_match($compiledRoute->getRegex(), $pathinfo, $matches)) {
                continue;
            }

            // check HTTP method requirement
            if (isset($this->context['method']) && $route->getRequirement('_method') && ($req = explode('|', $route->getRequirement('_method'))) && !in_array(strtolower($this->context['method']), array_map('strtolower', $req))) {
                $allow = array_merge($allow, $req);
                continue;
            }

            return array_merge($this->mergeDefaults($matches, $route->getDefaults()), array('_route' => $name));
        }

        throw 0 < count($allow)
            ? new MethodNotAllowedException(array_unique(array_map('strtolower', $allow)))
            : new NotFoundException();
    }

    protected function mergeDefaults($params, $defaults)
    {
        $parameters = array_merge($this->defaults, $defaults);
        foreach ($params as $key => $value) {
            if (!is_int($key)) {
                $parameters[$key] = urldecode($value);
            }
        }

        return $parameters;
    }
}
