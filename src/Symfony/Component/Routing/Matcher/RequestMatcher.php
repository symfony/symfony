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

use Psr\Http\Message\RequestInterface;
use Symfony\Component\Routing\Exception\MethodNotAllowedException;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;
use Symfony\Component\Routing\Exception\SchemeNotAllowedException;
use Symfony\Component\Routing\RouteCollection;
use Symfony\Component\Routing\Route;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;
use Symfony\Component\ExpressionLanguage\ExpressionFunctionProviderInterface;

/**
 * RequestMatcher tries to find a route that matches the request.
 *
 * @author Tobias Schultze <http://tobion.de>
 */
class RequestMatcher implements RequestMatcherInterface, PsrRequestMatcherInterface
{
    /**
     * @var string[]
     */
    private $allowedMethods = array();

    /**
     * @var string[]
     */
    private $allowedSchemes = array();

    /**
     * @var RouteCollection
     */
    private $routes;

    private $expressionLanguage;

    /**
     * @var ExpressionFunctionProviderInterface[]
     */
    private $expressionLanguageProviders = array();

    /**
     * Constructor.
     *
     * @param RouteCollection $routes  A RouteCollection instance
     */
    public function __construct(RouteCollection $routes)
    {
        $this->routes = $routes;
    }

    /**
     * {@inheritdoc}
     */
    public function matchRequest(Request $request)
    {
        $this->allowedMethods = array();
        $this->allowedSchemes = array();

        if ($ret = $this->matchCollection($request, $this->routes)) {
            return $ret;
        }

        if ($this->allowedMethods) {
            $this->allowedMethods = array_unique($this->allowedMethods);

            throw new MethodNotAllowedException(
                $this->allowedMethods,
                sprintf('No route found for request "%s %s": Method Not Allowed (Allowed: %s)', $request->getMethod(), $request->getPathInfo(), implode(', ', $this->allowedMethods))
            );
        }

        if ($this->allowedSchemes) {
            $this->allowedSchemes = array_unique($this->allowedSchemes);

            throw new SchemeNotAllowedException(
                $this->allowedSchemes,
                sprintf('No route found for request "%s %s": Scheme Not Allowed (Allowed: %s)', $request->getMethod(), $request->getUri(), implode(', ', $this->allowedSchemes))
            );
        }

        throw new ResourceNotFoundException(sprintf('No route found for request "%s %s".', $request->getMethod(), $request->getPathInfo()));
    }

    /**
     * {@inheritdoc}
     */
    public function matchPsrRequest(RequestInterface $request)
    {
        // TODO
    }

    public function addExpressionLanguageProvider(ExpressionFunctionProviderInterface $provider)
    {
        $this->expressionLanguageProviders[] = $provider;
    }

    /**
     * Tries to match a request with a set of routes.
     *
     * @param Request         $request The request
     * @param RouteCollection $routes  The set of routes
     *
     * @return array|null An array of parameters for the matched route or null if none matched
     *
     * @throws ResourceNotFoundException If the resource could not be found
     * @throws MethodNotAllowedException If the resource was found but the request method is not allowed
     */
    private function matchCollection(Request $request, RouteCollection $routes)
    {
        $path = rawurldecode($request->getPathInfo());
        $host = $request->getHost();
        $scheme = $request->getScheme();
        $method = $request->getMethod();
        // HEAD and GET are equivalent as per RFC
        if ('HEAD' === $method) {
            $method = 'GET';
        }

        foreach ($routes->all() as $name => $route) {
            $compiledRoute = $route->compile();

            // Check the static prefix of the path first. Only use the more expensive preg_match when the static prefix is the same.
            if ('' !== $compiledRoute->getStaticPrefix() && 0 !== strpos($path, $compiledRoute->getStaticPrefix())) {
                continue;
            }

            $pathMatches = array();
            if (!preg_match($compiledRoute->getRegex(), $path, $pathMatches)) {
                continue;
            }

            $hostMatches = array();
            if ($compiledRoute->getHostRegex() && !preg_match($compiledRoute->getHostRegex(), $host, $hostMatches)) {
                continue;
            }

            if ($route->getCondition() && !$this->getExpressionLanguage()->evaluate($route->getCondition(), array('request' => $request))) {
                continue;
            }

            if ($route->getMethods() && !in_array($method, $route->getMethods())) {
                $this->allowedMethods = array_merge($this->allowedMethods, $route->getMethods());

                continue;
            }

            if ($route->getSchemes() && !in_array($scheme, $route->getSchemes())) {
                $this->allowedSchemes = array_merge($this->allowedSchemes, $route->getSchemes());

                continue;
            }

            return $this->getAttributes($route, $name, array_replace($pathMatches, $hostMatches));
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
     * Get merged default parameters.
     *
     * @param array $params   The parameters
     * @param array $defaults The defaults
     *
     * @return array Merged default parameters
     */
    private function mergeDefaults($params, $defaults)
    {
        foreach ($params as $key => $value) {
            if (!is_int($key)) {
                $defaults[$key] = $value;
            }
        }

        return $defaults;
    }

    private function getExpressionLanguage()
    {
        if (null === $this->expressionLanguage) {
            if (!class_exists('Symfony\Component\ExpressionLanguage\ExpressionLanguage')) {
                throw new \RuntimeException('Unable to use expressions as the Symfony ExpressionLanguage component is not installed.');
            }

            $this->expressionLanguage = new ExpressionLanguage(null, $this->expressionLanguageProviders);
        }

        return $this->expressionLanguage;
    }
}
