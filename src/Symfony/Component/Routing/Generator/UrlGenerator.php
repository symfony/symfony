<?php

namespace Symfony\Component\Routing\Generator;

use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

/*
 * This file is part of the Symfony framework.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

/**
 * UrlGenerator generates URL based on a set of routes.
 *
 * @author Fabien Potencier <fabien.potencier@symfony-project.com>
 */
class UrlGenerator implements UrlGeneratorInterface
{
    protected $routes;
    protected $defaults;
    protected $context;
    protected $cache;

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
        $this->cache = array();
    }

    /**
     * Generates a URL from the given parameters.
     *
     * @param  string  $name       The name of the route
     * @param  array   $parameters An array of parameters
     * @param  Boolean $absolute   Whether to generate an absolute URL
     *
     * @return string The generated URL
     *
     * @throws \InvalidArgumentException When route doesn't exist
     */
    public function generate($name, array $parameters, $absolute = false)
    {
        if (null === $route = $this->routes->get($name)) {
            throw new \InvalidArgumentException(sprintf('Route "%s" does not exist.', $name));
        }

        if (!isset($this->cache[$name])) {
            $this->cache[$name] = $route->compile();
        }

        return $this->doGenerate($this->cache[$name]->getVariables(), $route->getDefaults(), $route->getRequirements(), $this->cache[$name]->getTokens(), $parameters, $name, $absolute);
    }

    /**
     * @throws \InvalidArgumentException When route has some missing mandatory parameters
     */
    protected function doGenerate($variables, $defaults, $requirements, $tokens, $parameters, $name, $absolute)
    {
        $defaults = array_merge($this->defaults, $defaults);
        $tparams = array_merge($defaults, $parameters);

        // all params must be given
        if ($diff = array_diff_key($variables, $tparams)) {
            throw new \InvalidArgumentException(sprintf('The "%s" route has some missing mandatory parameters (%s).', $name, implode(', ', $diff)));
        }

        $url = '';
        $optional = true;
        foreach ($tokens as $token) {
            if ('variable' === $token[0]) {
                if (false === $optional || !isset($defaults[$token[3]]) || (isset($parameters[$token[3]]) && $parameters[$token[3]] != $defaults[$token[3]])) {
                    // check requirement
                    if (isset($requirements[$token[3]]) && !preg_match('#^'.$requirements[$token[3]].'$#', $tparams[$token[3]])) {
                        throw new \InvalidArgumentException(sprintf('Parameter "%s" for route "%s" must match "%s" ("%s" given).', $token[3], $name, $requirements[$token[3]], $tparams[$token[3]]));
                    }

                    $url = $token[1].urlencode($tparams[$token[3]]).$url;
                    $optional = false;
                }
            } elseif ('text' === $token[0]) {
                $url = $token[1].$token[2].$url;
                $optional = false;
            } else {
                // handle custom tokens
                if ($segment = call_user_func_array(array($this, 'generateFor'.ucfirst(array_shift($token))), array_merge(array($optional, $tparams), $token))) {
                    $url = $segment.$url;
                    $optional = false;
                }
            }
        }

        if (!$url) {
            $url = '/';
        }

        // add a query string if needed
        if ($extra = array_diff_key($parameters, $variables, $defaults)) {
            $url .= '?'.http_build_query($extra);
        }

        $url = (isset($this->context['base_url']) ? $this->context['base_url'] : '').$url;

        if ($absolute && isset($this->context['host'])) {
            $url = 'http'.(isset($this->context['is_secure']) && $this->context['is_secure'] ? 's' : '').'://'.$this->context['host'].$url;
        }

        return $url;
    }
}
