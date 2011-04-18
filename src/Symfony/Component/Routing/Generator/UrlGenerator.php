<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Routing\Generator;

use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

/**
 * UrlGenerator generates URL based on a set of routes.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class UrlGenerator implements UrlGeneratorInterface
{
    protected $defaults;
    protected $context;

    private $routes;
    private $cache;

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
     * Sets the request context.
     *
     * @param array $context  The context
     */
    public function setContext(array $context = array())
    {
        $this->context = $context;
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
    public function generate($name, array $parameters = array(), $absolute = false)
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

                    if (isset($tparams[$token[3]])) {
                        // %2F is not valid in a URL, so we don't encode it (which is fine as the requirements explicitly allowed it)
                        $url = $token[1].str_replace('%2F', '/', urlencode($tparams[$token[3]])).$url;
                    }

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
            $isSecure = (isset($this->context['is_secure']) && $this->context['is_secure']);
            $port = isset($this->context['port']) ? $this->context['port'] : 80;
            $urlBeginning = 'http'.($isSecure ? 's' : '').'://'.$this->context['host'];
            if (($isSecure && $port != 443) || (!$isSecure && $port != 80)) {
                $urlBeginning .= ':'.$port;
            }
            $url = $urlBeginning.$url;
        }

        return $url;
    }
}
