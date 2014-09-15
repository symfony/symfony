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

use Symfony\Component\Routing\Exception\MethodNotAllowedException;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;
use Symfony\Component\Routing\Matcher\Strategy\CompositeStrategy;
use Symfony\Component\Routing\Matcher\Strategy\HostRegexStrategy;
use Symfony\Component\Routing\Matcher\Strategy\HttpMethodStrategy;
use Symfony\Component\Routing\Matcher\Strategy\MatcherStrategy;
use Symfony\Component\Routing\Matcher\Strategy\RegexStrategy;
use Symfony\Component\Routing\Matcher\Strategy\StaticPrefixStrategy;
use Symfony\Component\Routing\RouteCollection;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\Route;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;

/**
 * UrlMatcher matches URL based on a set of routes.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 *
 * @api
 */
class UrlMatcher implements UrlMatcherInterface, RequestMatcherInterface
{
    const REQUIREMENT_MATCH     = 0;
    const REQUIREMENT_MISMATCH  = 1;
    const ROUTE_MATCH           = 2;

    /**
     * @var RequestContext
     */
    protected $context;

    /**
     * @var array
     */
    protected $allow = array();

    /**
     * @var array
     */
    protected $matcherStrategy;

    /**
     * @var RouteCollection
     */
    protected $routes;

    protected $request;
    protected $expressionLanguage;

    /**
     * Constructor.
     *
     * @param RouteCollection $routes  A RouteCollection instance
     * @param RequestContext  $context The context
     *
     * @api
     */
    public function __construct(RouteCollection $routes, RequestContext $context)
    {
        $this->routes = $routes;
        $this->context = $context;

        $this->matcherStrategy = new CompositeStrategy(array(
            new StaticPrefixStrategy(),
            new RegexStrategy(),
            new HostRegexStrategy(),
            new HttpMethodStrategy(),
        ));
    }

    /**
     * {@inheritdoc}
     */
    public function setContext(RequestContext $context)
    {
        $this->context = $context;
    }

    /**
     * {@inheritdoc}
     */
    public function getContext()
    {
        return $this->context;
    }

    /**
     * {@inheritdoc}
     */
    public function match($pathinfo)
    {
        $this->allow = array();

        if ($ret = $this->matchCollection(rawurldecode($pathinfo), $this->routes)) {
            return $ret;
        }

        throw 0 < count($this->allow)
            ? new MethodNotAllowedException(array_unique(array_map('strtoupper', $this->allow)))
            : new ResourceNotFoundException(sprintf('No routes found for "%s".', $pathinfo));
    }

    /**
     * {@inheritdoc}
     */
    public function matchRequest(Request $request)
    {
        $this->request = $request;

        $ret = $this->match($request->getPathInfo());

        $this->request = null;

        return $ret;
    }

    /**
     * @param MatcherStrategy $strategy
     */
    public function setMatcherStrategy(MatcherStrategy $strategy)
    {
        $this->matcherStrategy = $strategy;
    }

    /**
     * Tries to match a URL with a set of routes.
     *
     * @param string          $pathinfo The path info to be parsed
     * @param RouteCollection $routes   The set of routes
     *
     * @return array An array of parameters
     *
     * @throws ResourceNotFoundException If the resource could not be found
     * @throws MethodNotAllowedException If the resource was found but the request method is not allowed
     */
    protected function matchCollection($pathinfo, RouteCollection $routes)
    {
        foreach ($routes as $name => $route) {
            $this->addReqToAllow($route);

            if (!$this->matcherStrategy->matches($pathinfo, $route, $this->context)) {
                continue;
            }

            $status = $this->handleRouteRequirements($pathinfo, $name, $route);

            if (self::ROUTE_MATCH === $status[0]) {
                return $status[1];
            }

            if (self::REQUIREMENT_MISMATCH === $status[0]) {
                continue;
            }

            $compiledRoute = $route->compile();
            preg_match($compiledRoute->getRegex(), $pathinfo, $matches);
            $hostMatches = $this->extractHostMatches($route);

            return $this->getAttributes($route, $name, array_replace($matches, $hostMatches));
        }
    }

    /**
     * Returns an array of values to use as request attributes.
     *
     * As this method requires the Route object, it is not available
     * in matchers that do not have access to the matched Route instance
     * (like the PHP and Apache matcher dumpers).
     *
     * @param Route  $route      The route we are matching against
     * @param string $name       The name of the route
     * @param array  $attributes An array of attributes from the matcher
     *
     * @return array An array of parameters
     */
    protected function getAttributes(Route $route, $name, array $attributes)
    {
        $attributes['_route'] = $name;

        return $this->mergeDefaults($attributes, $route->getDefaults());
    }

    /**
     * Handles specific route requirements.
     *
     * @param string $pathinfo The path
     * @param string $name     The route name
     * @param Route  $route    The route
     *
     * @return array The first element represents the status, the second contains additional information
     */
    protected function handleRouteRequirements($pathinfo, $name, Route $route)
    {
        // expression condition
        if ($route->getCondition() && !$this->getExpressionLanguage()->evaluate($route->getCondition(), array('context' => $this->context, 'request' => $this->request))) {
            return array(self::REQUIREMENT_MISMATCH, null);
        }

        // check HTTP scheme requirement
        $scheme = $this->context->getScheme();
        $status = $route->getSchemes() && !$route->hasScheme($scheme) ? self::REQUIREMENT_MISMATCH : self::REQUIREMENT_MATCH;

        return array($status, null);
    }

    /**
     * Get merged default parameters.
     *
     * @param array $params   The parameters
     * @param array $defaults The defaults
     *
     * @return array Merged default parameters
     */
    protected function mergeDefaults($params, $defaults)
    {
        foreach ($params as $key => $value) {
            if (!is_int($key)) {
                $defaults[$key] = $value;
            }
        }

        return $defaults;
    }

    protected function getExpressionLanguage()
    {
        if (null === $this->expressionLanguage) {
            if (!class_exists('Symfony\Component\ExpressionLanguage\ExpressionLanguage')) {
                throw new \RuntimeException('Unable to use expressions as the Symfony ExpressionLanguage component is not installed.');
            }
            $this->expressionLanguage = new ExpressionLanguage();
        }

        return $this->expressionLanguage;
    }

    /**
     * @param $route
     */
    protected function addReqToAllow($route)
    {
        if ($req = $route->getRequirement('_method')) {
            if (!in_array($this->context->getMethod(), $req = explode('|', strtoupper($req)))) {
                $this->allow = array_merge($this->allow, $req);
            }
        }
    }

    /**
     * @param Route $route
     * @return array
     */
    protected function extractHostMatches(Route $route)
    {
        $compiledRoute = $route->compile();
        if ($compiledRoute->getHostRegex()) {
            $host = $this->context->getHost();
            preg_match($compiledRoute->getHostRegex(), $host, $hostMatches);

            return $hostMatches;
        }

        return array();
    }
}
