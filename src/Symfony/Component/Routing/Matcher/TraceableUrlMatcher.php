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

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Exception\ExceptionInterface;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

/**
 * TraceableUrlMatcher helps debug path info matching by tracing the match.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class TraceableUrlMatcher extends UrlMatcher
{
    const ROUTE_DOES_NOT_MATCH = 0;
    const ROUTE_ALMOST_MATCHES = 1;
    const ROUTE_MATCHES = 2;

    protected $traces;

    public function getTraces($pathinfo)
    {
        $this->traces = [];

        try {
            $this->match($pathinfo);
        } catch (ExceptionInterface $e) {
        }

        return $this->traces;
    }

    public function getTracesForRequest(Request $request)
    {
        $this->request = $request;
        $traces = $this->getTraces($request->getPathInfo());
        $this->request = null;

        return $traces;
    }

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
                $this->addTrace(sprintf('Path "%s" does not match', $route->getPath()), self::ROUTE_DOES_NOT_MATCH, $name, $route);
                continue;
            } elseif ('/' === substr($staticPrefix, -1) && substr($staticPrefix, 0, -1) === $pathinfo) {
                $this->addTrace('Route matches!', self::ROUTE_MATCHES, $name, $route);

                return $this->allow = [];
            } else {
                $this->addTrace(sprintf('Path "%s" does not match', $route->getPath()), self::ROUTE_DOES_NOT_MATCH, $name, $route);
                continue;
            }
            $regex = $compiledRoute->getRegex();

            if ($supportsTrailingSlash && $pos = strpos($regex, '/$')) {
                $regex = substr($regex, 0, $pos).'/?$'.substr($regex, $pos + 2);
                $hasTrailingSlash = true;
            } else {
                $hasTrailingSlash = false;
            }

            if (!preg_match($regex, $pathinfo, $matches)) {
                // does it match without any requirements?
                $r = new Route($route->getPath(), $route->getDefaults(), [], $route->getOptions());
                $cr = $r->compile();
                if (!preg_match($cr->getRegex(), $pathinfo)) {
                    $this->addTrace(sprintf('Path "%s" does not match', $route->getPath()), self::ROUTE_DOES_NOT_MATCH, $name, $route);

                    continue;
                }

                foreach ($route->getRequirements() as $n => $regex) {
                    $r = new Route($route->getPath(), $route->getDefaults(), [$n => $regex], $route->getOptions());
                    $cr = $r->compile();

                    if (\in_array($n, $cr->getVariables()) && !preg_match($cr->getRegex(), $pathinfo)) {
                        $this->addTrace(sprintf('Requirement for "%s" does not match (%s)', $n, $regex), self::ROUTE_ALMOST_MATCHES, $name, $route);

                        continue 2;
                    }
                }

                continue;
            }

            if ($hasTrailingSlash && '/' !== substr($pathinfo, -1)) {
                if ((!$requiredMethods || \in_array('GET', $requiredMethods)) && 'GET' === $method) {
                    $this->addTrace('Route matches!', self::ROUTE_MATCHES, $name, $route);

                    return $this->allow = [];
                }
                $this->addTrace(sprintf('Method "%s" does not match any of the required methods (%s)', $this->context->getMethod(), implode(', ', $requiredMethods)), self::ROUTE_ALMOST_MATCHES, $name, $route);
                continue;
            }

            $hostMatches = [];
            if ($compiledRoute->getHostRegex() && !preg_match($compiledRoute->getHostRegex(), $this->context->getHost(), $hostMatches)) {
                $this->addTrace(sprintf('Host "%s" does not match the requirement ("%s")', $this->context->getHost(), $route->getHost()), self::ROUTE_ALMOST_MATCHES, $name, $route);
                continue;
            }

            $status = $this->handleRouteRequirements($pathinfo, $name, $route);

            if (self::REQUIREMENT_MISMATCH === $status[0]) {
                if ($route->getCondition()) {
                    $this->addTrace(sprintf('Condition "%s" does not evaluate to "true"', $route->getCondition()), self::ROUTE_ALMOST_MATCHES, $name, $route);
                } else {
                    $this->addTrace(sprintf('Scheme "%s" does not match any of the required schemes (%s); the user will be redirected to first required scheme', $this->getContext()->getScheme(), implode(', ', $route->getSchemes())), self::ROUTE_ALMOST_MATCHES, $name, $route);
                }

                continue;
            }

            // check HTTP method requirement
            if ($requiredMethods) {
                if (!\in_array($method, $requiredMethods)) {
                    if (self::REQUIREMENT_MATCH === $status[0]) {
                        $this->allow = array_merge($this->allow, $requiredMethods);
                    }
                    $this->addTrace(sprintf('Method "%s" does not match any of the required methods (%s)', $this->context->getMethod(), implode(', ', $requiredMethods)), self::ROUTE_ALMOST_MATCHES, $name, $route);

                    continue;
                }
            }

            $this->addTrace('Route matches!', self::ROUTE_MATCHES, $name, $route);

            return $this->getAttributes($route, $name, array_replace($matches, $hostMatches, isset($status[1]) ? $status[1] : []));
        }

        return [];
    }

    private function addTrace($log, $level = self::ROUTE_DOES_NOT_MATCH, $name = null, $route = null)
    {
        $this->traces[] = [
            'log' => $log,
            'name' => $name,
            'level' => $level,
            'path' => null !== $route ? $route->getPath() : null,
        ];
    }
}
