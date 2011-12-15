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
use Symfony\Component\Routing\Matcher\RedirectableUrlMatcherInterface;

/**
 * UrlMatcher matches URL based on a set of routes.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 *
 * @api
 */
class UrlMatcher implements UrlMatcherInterface
{
    protected $context;

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

        if ($ret = $this->matchCollection($pathinfo, $this->routes)) {
            return $ret;
        }

        throw 0 < count($this->allow)
            ? new MethodNotAllowedException(array_unique(array_map('strtoupper', $this->allow)))
            : new ResourceNotFoundException();
    }

    protected function matchCollection($pathinfo, RouteCollection $routes)
    {
        $pathinfo = urldecode($pathinfo);

        foreach ($routes as $name => $route) {
            if ($route instanceof RouteCollection) {
                if (false === strpos($route->getPrefix(), '{') && $route->getPrefix() !== substr($pathinfo, 0, strlen($route->getPrefix()))) {
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
                continue;
            }

            if (!preg_match($compiledRoute->getRegex(), $pathinfo, $matches)) {
                continue;
            }

            // check HTTP method requirement
            if ($req = $route->getRequirement('_method')) {
                // HEAD and GET are equivalent as per RFC
                if ('HEAD' === $method = $this->context->getMethod()) {
                    $method = 'GET';
                }

                if (!in_array($method, $req = explode('|', strtoupper($req)))) {
                    $this->allow = array_merge($this->allow, $req);

                    continue;
                }
            }

            // check HTTP scheme requirement
            if ($scheme = $route->getRequirement('_scheme')) {
                if (!$this instanceof RedirectableUrlMatcherInterface) {
                    throw new \LogicException('The "_scheme" requirement is only supported for URL matchers that implement RedirectableUrlMatcherInterface.');
                }

                if ($this->context->getScheme() !== $scheme) {
                    return $this->redirect($pathinfo, $name, $scheme);
                }
            }

            return array_merge($this->mergeDefaults($matches, $route->getDefaults()), array('_route' => $name));
        }
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
