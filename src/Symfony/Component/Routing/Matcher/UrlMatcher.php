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

use Symfony\Component\ExpressionLanguage\ExpressionFunctionProviderInterface;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Exception\MethodNotAllowedException;
use Symfony\Component\Routing\Exception\NoConfigurationException;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

/**
 * UrlMatcher matches URL based on a set of routes.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class UrlMatcher implements UrlMatcherInterface, RequestMatcherInterface
{
    const REQUIREMENT_MATCH = 0;
    const REQUIREMENT_MISMATCH = 1;
    const ROUTE_MATCH = 2;

    protected $context;

    /**
     * Collects HTTP methods that would be allowed for the request.
     */
    protected $allow = array();

    /**
     * Collects URI schemes that would be allowed for the request.
     *
     * @internal
     */
    protected $allowSchemes = array();

    protected $routes;
    protected $request;
    protected $expressionLanguage;

    /**
     * @var ExpressionFunctionProviderInterface[]
     */
    protected $expressionLanguageProviders = array();

    public function __construct(RouteCollection $routes, RequestContext $context)
    {
        $this->routes = $routes;
        $this->context = $context;
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
        $this->allow = $this->allowSchemes = array();

        if ($ret = $this->matchCollection(rawurldecode($pathinfo), $this->routes)) {
            return $ret;
        }

        if ('/' === $pathinfo && !$this->allow) {
            throw new NoConfigurationException();
        }

        throw 0 < \count($this->allow)
            ? new MethodNotAllowedException(array_unique($this->allow))
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

    public function addExpressionLanguageProvider(ExpressionFunctionProviderInterface $provider)
    {
        $this->expressionLanguageProviders[] = $provider;
    }

    /**
     * Tries to match a URL with a set of routes.
     *
     * @param string          $pathinfo The path info to be parsed
     * @param RouteCollection $routes   The set of routes
     *
     * @return array An array of parameters
     *
     * @throws NoConfigurationException  If no routing configuration could be found
     * @throws ResourceNotFoundException If the resource could not be found
     * @throws MethodNotAllowedException If the resource was found but the request method is not allowed
     */
    protected function matchCollection($pathinfo, RouteCollection $routes)
    {
        // HEAD and GET are equivalent as per RFC
        if ('HEAD' === $method = $this->context->getMethod()) {
            $method = 'GET';
        }
        $supportsTrailingSlash = '/' !== $pathinfo && '' !== $pathinfo && $this instanceof RedirectableUrlMatcherInterface;

        foreach ($routes as $name => $route) {
            $compiledRoute = $route->compile();
            $staticPrefix = $compiledRoute->getStaticPrefix();
            $requiredMethods = $route->getMethods();

            // check the static prefix of the URL first. Only use the more expensive preg_match when it matches
            if ('' === $staticPrefix || 0 === strpos($pathinfo, $staticPrefix)) {
                // no-op
            } elseif (!$supportsTrailingSlash || ($requiredMethods && !\in_array('GET', $requiredMethods)) || 'GET' !== $method) {
                continue;
            } elseif ('/' === $staticPrefix[-1] && substr($staticPrefix, 0, -1) === $pathinfo) {
                return $this->allow = $this->allowSchemes = array();
            } elseif ('/' === $pathinfo[-1] && substr($pathinfo, 0, -1) === $staticPrefix) {
                return $this->allow = $this->allowSchemes = array();
            } else {
                continue;
            }
            $regex = $compiledRoute->getRegex();

            if ($supportsTrailingSlash) {
                $pos = strrpos($regex, '$');
                $hasTrailingSlash = '/' === $regex[$pos - 1];
                $regex = substr_replace($regex, '/?$', $pos - $hasTrailingSlash, 1 + $hasTrailingSlash);
            }

            if (!preg_match($regex, $pathinfo, $matches)) {
                continue;
            }

            if ($supportsTrailingSlash) {
                if ('/' === $pathinfo[-1]) {
                    if (preg_match($regex, substr($pathinfo, 0, -1), $m)) {
                        $matches = $m;
                    } else {
                        $hasTrailingSlash = true;
                    }
                }
                if ($hasTrailingSlash !== ('/' === $pathinfo[-1])) {
                    if ((!$requiredMethods || \in_array('GET', $requiredMethods)) && 'GET' === $method) {
                        return $this->allow = $this->allowSchemes = array();
                    }
                    continue;
                }
            }

            $hostMatches = array();
            if ($compiledRoute->getHostRegex() && !preg_match($compiledRoute->getHostRegex(), $this->context->getHost(), $hostMatches)) {
                continue;
            }

            $status = $this->handleRouteRequirements($pathinfo, $name, $route);

            if (self::REQUIREMENT_MISMATCH === $status[0]) {
                continue;
            }

            $hasRequiredScheme = !$route->getSchemes() || $route->hasScheme($this->context->getScheme());
            if ($requiredMethods) {
                if (!\in_array($method, $requiredMethods)) {
                    if ($hasRequiredScheme) {
                        $this->allow = array_merge($this->allow, $requiredMethods);
                    }

                    continue;
                }
            }

            if (!$hasRequiredScheme) {
                $this->allowSchemes = array_merge($this->allowSchemes, $route->getSchemes());

                continue;
            }

            return $this->getAttributes($route, $name, array_replace($matches, $hostMatches, isset($status[1]) ? $status[1] : array()));
        }

        return array();
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
        $defaults = $route->getDefaults();
        if (isset($defaults['_canonical_route'])) {
            $name = $defaults['_canonical_route'];
            unset($defaults['_canonical_route']);
        }
        $attributes['_route'] = $name;

        return $this->mergeDefaults($attributes, $defaults);
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
        if ($route->getCondition() && !$this->getExpressionLanguage()->evaluate($route->getCondition(), array('context' => $this->context, 'request' => $this->request ?: $this->createRequest($pathinfo)))) {
            return array(self::REQUIREMENT_MISMATCH, null);
        }

        return array(self::REQUIREMENT_MATCH, null);
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
            if (!\is_int($key) && null !== $value) {
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
            $this->expressionLanguage = new ExpressionLanguage(null, $this->expressionLanguageProviders);
        }

        return $this->expressionLanguage;
    }

    /**
     * @internal
     */
    protected function createRequest($pathinfo)
    {
        if (!class_exists('Symfony\Component\HttpFoundation\Request')) {
            return null;
        }

        return Request::create($this->context->getScheme().'://'.$this->context->getHost().$this->context->getBaseUrl().$pathinfo, $this->context->getMethod(), $this->context->getParameters(), array(), array(), array(
            'SCRIPT_FILENAME' => $this->context->getBaseUrl(),
            'SCRIPT_NAME' => $this->context->getBaseUrl(),
        ));
    }
}
