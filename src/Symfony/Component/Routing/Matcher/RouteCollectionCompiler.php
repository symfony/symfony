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

use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

/**
 * RouteCollection creates a PHP array representation of a RouteCollection instance.
 *
 * This representation is used by Matcher instances.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class RouteCollectionCompiler
{
    /**
     * Compiles a RouteCollection instance.
     *
     * @param RouteCollection $routes A RouteCollection instance
     *
     * @return array A PHP array representing the matcher data
     */
    public function compile(RouteCollection $routes)
    {
        // we need to deep clone the routes as we modify the structure to optimize the dump
        return $this->compileRoutes(clone $routes);
    }

    private function compileRoutes(RouteCollection $routes, $parentPrefix = null)
    {
        $code = array();

        $routeIterator = $routes->getIterator();
        $keys = array_keys($routeIterator->getArrayCopy());
        $keysCount = count($keys);

        $i = 0;
        foreach ($routeIterator as $name => $route) {
            $i++;

            if (!$route instanceof RouteCollection) {
                $code[] = $this->compileRoute($route, $name, $parentPrefix);

                continue;
            }

            $prefix = $route->getPrefix();
            $optimizable = $prefix && count($route->all()) > 1 && false === strpos($route->getPrefix(), '{');
            if (!$optimizable) {
                $code = array_merge($code, $this->compileRoutes($route, $prefix));

                continue;
            }

            for ($j = $i; $j < $keysCount; $j++) {
                if (null === $keys[$j]) {
                    continue;
                }

                $testRoute = $routeIterator->offsetGet($keys[$j]);
                $testPrefix = $testRoute instanceof RouteCollection ? $testRoute->getPrefix() : $testRoute->getPattern();

                if (0 === strpos($testPrefix, $prefix)) {
                    $routeIterator->offsetUnset($keys[$j]);

                    if ($testRoute instanceof RouteCollection) {
                        $route->addCollection($testRoute);
                    } else {
                        $route->add($keys[$j], $testRoute);
                    }

                    $i++;
                    $keys[$j] = null;
                }
            }

            if ($prefix !== $parentPrefix) {
                $code[$prefix] = $this->compileRoutes($route, $prefix);
            }
        }

        return $code;
    }

    private function compileRoute(Route $route, $name, $parentPrefix = null)
    {
        $compiledRoute = $route->compile();
        $methods = array();
        if ($req = $route->getRequirement('_method')) {
            $methods = explode('|', strtoupper($req));
            // GET and HEAD are equivalent
            if (in_array('GET', $methods) && !in_array('HEAD', $methods)) {
                $methods[] = 'HEAD';
            }
        }
        $supportsTrailingSlash = !$methods || in_array('HEAD', $methods);
        $dump = array(
            'name' => $name,
        );

        if ($methods) {
            $dump['methods'] = $methods;
        }

        if ($route->getRequirement('_scheme')) {
            $dump['scheme'] = $route->getRequirement('_scheme');
        }

        if ($route->getDefaults()) {
            $dump['defaults'] = $route->getDefaults();
        }

        if (!count($compiledRoute->getVariables()) && false !== preg_match('#^(.)\^(?P<url>.*?)\$\1#', str_replace(array("\n", ' '), '', $compiledRoute->getRegex()), $m)) {
            $dump['static'] = true;
            $dump['prefix'] = str_replace('\\', '', $m['url']);
            if ($supportsTrailingSlash && '/' === substr($dump['prefix'], -1)) {
                $dump['prefix'] = rtrim($dump['prefix']);
                $dump['trailing_slash'] = true;
            }
        } else {
            if ($compiledRoute->getStaticPrefix() && $compiledRoute->getStaticPrefix() != $parentPrefix) {
                $dump['prefix'] = $compiledRoute->getStaticPrefix();
            }

            $regex = str_replace(array("\n", ' '), '', $compiledRoute->getRegex());
            if ($supportsTrailingSlash && $pos = strpos($regex, '/$')) {
                $regex = substr($regex, 0, $pos).'/?$'.substr($regex, $pos + 2);
                $dump['trailing_slash'] = true;
            }
            $dump['regex'] = $regex;
        }

        return $dump;
    }
}
