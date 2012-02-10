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
use Symfony\Component\Routing\RouteCollection;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\Route;

/**
 * UrlMatcher matches URL based on a set of routes.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 *
 * @api
 */
class UrlMatcher implements UrlMatcherInterface
{
    const PROCESS_NEXT_ROUTE    = 0;
    const PROCESS_CURRENT_ROUTE = 1;

    protected $context;
    protected $allow;
    protected $logger;
    protected $matchedRoute;

    private $routes;

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
    }

    public function setLogger(LoggableInterface $logger = null)
    {
        $this->logger = $logger;
    }

    /**
     * Sets the request context.
     *
     * @param RequestContext $context The context
     *
     * @api
     */
    public function setContext(RequestContext $context)
    {
        $this->context = $context;
    }

    /**
     * Gets the request context.
     *
     * @return RequestContext The context
     */
    public function getContext()
    {
        return $this->context;
    }

    /**
     * {@inheritDoc}
     *
     * @api
     */
    public function match($pathinfo)
    {
        $this->allow = array();
        $this->matchedRoute = null;

        if ($ret = $this->matchCollection(urldecode($pathinfo), $this->routes)) {
            return $ret;
        }

        throw 0 < count($this->allow)
            ? new MethodNotAllowedException(array_unique(array_map('strtoupper', $this->allow)))
            : new ResourceNotFoundException();
    }

    protected function matchCollection($pathinfo, RouteCollection $routes)
    {
        foreach ($routes as $name => $route) {
            if ($route instanceof RouteCollection) {
                if (!$this->logger && false === strpos($route->getPrefix(), '{') && $route->getPrefix() !== substr($pathinfo, 0, strlen($route->getPrefix()))) {
                    continue;
                }

                if (!$ret = $this->matchCollection($pathinfo, $route)) {
                    continue;
                }

                return $ret;
            }

            $compiledRoute = $route->compile();

            // check the static prefix of the URL first. Only use the more expensive preg_match when it matches
            if ('' !== $compiledRoute->getStaticPrefix() && 0 !== strpos($pathinfo, $compiledRoute->getStaticPrefix())) {
                if ($this->logger) {
                    $this->logger->log(
                        sprintf('Pattern "%s" does not match', $route->getPattern()),
                        LoggableInterface::ROUTE_DOES_NOT_MATCH,
                        $pathinfo,
                        $name,
                        $route
                    );
                }
                continue;
            }

            if (!preg_match($compiledRoute->getRegex(), $pathinfo, $matches)) {
                if ($this->logger) {
                    // does it match without any requirements?
                    $fakeRoute = new Route($route->getPattern(), $route->getDefaults(), array(), $route->getOptions());
                    if (!preg_match($fakeRoute->compile()->getRegex(), $pathinfo)) {
                        $this->logger->log(
                            sprintf('Pattern "%s" does not match', $route->getPattern()),
                            LoggableInterface::ROUTE_DOES_NOT_MATCH,
                            $pathinfo,
                            $name,
                            $route
                        );

                        continue;
                    }

                    foreach ($route->getRequirements() as $n => $regex) {
                        $fakeRoute = new Route($route->getPattern(), $route->getDefaults(), array($n => $regex), $route->getOptions());
                        $fakeRoute = $fakeRoute->compile();

                        if (in_array($n, $fakeRoute->getVariables()) && !preg_match($fakeRoute->getRegex(), $pathinfo)) {
                            $this->logger->log(
                                sprintf('Requirement for "%s" does not match (%s)', $n, $regex),
                                LoggableInterface::ROUTE_ALMOST_MATCHES,
                                $pathinfo,
                                $name,
                                $route);
                        }

                        continue 2;
                    }
                }

                continue;
            }

            if (self::PROCESS_NEXT_ROUTE === $this->handleRouteRequirements($pathinfo, $name, $route)) {
                continue;
            }

            if ($this->logger) {
                $this->logger->log(
                    'Route matches!',
                    LoggableInterface::ROUTE_MATCHES,
                    $pathinfo,
                    $name,
                    $route
                );
            }

            $this->matchedRoute = $route;
            return array_merge($this->mergeDefaults($matches, $route->getDefaults()), array('_route' => $name));
        }
    }

    protected function handleRouteRequirements($pathinfo, $name, Route $route)
    {
        // check HTTP method requirement
        if ($req = $route->getRequirement('_method')) {
            // HEAD and GET are equivalent as per RFC
            if ('HEAD' === $method = $this->context->getMethod()) {
                $method = 'GET';
            }

            if (!in_array($method, $req = explode('|', strtoupper($req)))) {
                $this->allow = array_merge($this->allow, $req);

                if ($this->logger) {
                    $this->logger->log(
                        sprintf('Method "%s" does not match the requirement ("%s")', $this->context->getMethod(), implode(', ', $req)),
                        LoggableInterface::ROUTE_ALMOST_MATCHES,
                        $pathinfo,
                        $name,
                        $route
                    );
                }

                return self::PROCESS_NEXT_ROUTE;
            }
        }

        return self::PROCESS_CURRENT_ROUTE;
    }

    protected function mergeDefaults($params, $defaults)
    {
        $parameters = $defaults;
        foreach ($params as $key => $value) {
            if (!is_int($key)) {
                $parameters[$key] = rawurldecode($value);
            }
        }

        return $parameters;
    }
}
