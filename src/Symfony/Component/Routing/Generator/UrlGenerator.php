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
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\Exception\InvalidParameterException;
use Symfony\Component\Routing\Exception\RouteNotFoundException;
use Symfony\Component\Routing\Exception\MissingMandatoryParametersException;
use Symfony\Component\HttpKernel\Log\LoggerInterface;

/**
 * UrlGenerator generates a URL based on a set of routes.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 *
 * @api
 */
class UrlGenerator implements UrlGeneratorInterface, ConfigurableRequirementsInterface
{
    protected $context;
    protected $strictRequirements = true;
    protected $logger;

    /**
     * This array defines the characters (besides alphanumeric ones) that will not be percent-encoded in the path segment of the generated URL.
     *
     * PHP's rawurlencode() encodes all chars except "a-zA-Z0-9-._~" according to RFC 3986. But we want to allow some chars
     * to be used in their literal form (reasons below). Other chars inside the path must of course be encoded, e.g.
     * "?" and "#" (would be interpreted wrongly as query and fragment identifier),
     * "'" and """ (are used as delimiters in HTML).
     */
    protected $decodedChars = array(
        // the slash can be used to designate a hierarchical structure and we want allow using it with this meaning
        // some webservers don't allow the slash in encoded form in the path for security reasons anyway
        // see http://stackoverflow.com/questions/4069002/http-400-if-2f-part-of-get-url-in-jboss
        '%2F' => '/',
        // the following chars are general delimiters in the URI specification but have only special meaning in the authority component
        // so they can safely be used in the path in unencoded form
        '%40' => '@',
        '%3A' => ':',
        // these chars are only sub-delimiters that have no predefined meaning and can therefore be used literally
        // so URI producing applications can use these chars to delimit subcomponents in a path segment without being encoded for better readability
        '%3B' => ';',
        '%2C' => ',',
        '%3D' => '=',
        '%2B' => '+',
        '%21' => '!',
        '%2A' => '*',
        '%7C' => '|',
    );

    protected $routes;

    /**
     * Constructor.
     *
     * @param RouteCollection $routes  A RouteCollection instance
     * @param RequestContext  $context The context
     * @param LoggerInterface $logger  A logger instance
     *
     * @api
     */
    public function __construct(RouteCollection $routes, RequestContext $context, LoggerInterface $logger = null)
    {
        $this->routes = $routes;
        $this->context = $context;
        $this->logger = $logger;
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
    public function setStrictRequirements($enabled)
    {
        $this->strictRequirements = (Boolean) $enabled;
    }

    /**
     * {@inheritdoc}
     */
    public function isStrictRequirements()
    {
        return $this->strictRequirements;
    }

    /**
     * {@inheritDoc}
     */
    public function generate($name, $parameters = array(), $absolute = false)
    {
        if (null === $route = $this->routes->get($name)) {
            throw new RouteNotFoundException(sprintf('Route "%s" does not exist.', $name));
        }

        // the Route has a cache of its own and is not recompiled as long as it does not get modified
        $compiledRoute = $route->compile();

        return $this->doGenerate($compiledRoute->getVariables(), $route->getDefaults(), $route->getRequirements(), $compiledRoute->getTokens(), $parameters, $name, $absolute);
    }

    /**
     * @throws MissingMandatoryParametersException When route has some missing mandatory parameters
     * @throws InvalidParameterException When a parameter value is not correct
     */
    protected function doGenerate($variables, $defaults, $requirements, $tokens, $parameters, $name, $absolute)
    {
        $variables = array_flip($variables);

        $originParameters = $parameters;
        $parameters = array_replace($this->context->getParameters(), $parameters);
        $tparams = array_replace($defaults, $parameters);

        // all params must be given
        if ($diff = array_diff_key($variables, $tparams)) {
            throw new MissingMandatoryParametersException(sprintf('The "%s" route has some missing mandatory parameters ("%s").', $name, implode('", "', array_keys($diff))));
        }

        $url = '';
        $optional = true;
        foreach ($tokens as $token) {
            if ('variable' === $token[0]) {
                if (false === $optional || !array_key_exists($token[3], $defaults) || (isset($parameters[$token[3]]) && (string) $parameters[$token[3]] != (string) $defaults[$token[3]])) {
                    if (!$isEmpty = in_array($tparams[$token[3]], array(null, '', false), true)) {
                        // check requirement
                        if ($tparams[$token[3]] && !preg_match('#^'.$token[2].'$#', $tparams[$token[3]])) {
                            $message = sprintf('Parameter "%s" for route "%s" must match "%s" ("%s" given).', $token[3], $name, $token[2], $tparams[$token[3]]);
                            if ($this->strictRequirements) {
                                throw new InvalidParameterException($message);
                            }

                            if ($this->logger) {
                                $this->logger->err($message);
                            }

                            return null;
                        }
                    }

                    if (!$isEmpty || !$optional) {
                        $url = $token[1].$tparams[$token[3]].$url;
                    }

                    $optional = false;
                }
            } elseif ('text' === $token[0]) {
                $url = $token[1].$url;
                $optional = false;
            }
        }

        if ('' === $url) {
            $url = '/';
        }

        // do not encode the contexts base url as it is already encoded (see Symfony\Component\HttpFoundation\Request)
        $url = $this->context->getBaseUrl().strtr(rawurlencode($url), $this->decodedChars);

        // the path segments "." and ".." are interpreted as relative reference when resolving a URI; see http://tools.ietf.org/html/rfc3986#section-3.3
        // so we need to encode them as they are not used for this purpose here
        // otherwise we would generate a URI that, when followed by a user agent (e.g. browser), does not match this route
        $url = strtr($url, array('/../' => '/%2E%2E/', '/./' => '/%2E/'));
        if ('/..' === substr($url, -3)) {
            $url = substr($url, 0, -2) . '%2E%2E';
        } elseif ('/.' === substr($url, -2)) {
            $url = substr($url, 0, -1) . '%2E';
        }

        // add a query string if needed
        $extra = array_diff_key($originParameters, $variables, $defaults);
        if ($extra && $query = http_build_query($extra, '', '&')) {
            $url .= '?'.$query;
        }

        if ($this->context->getHost()) {
            $scheme = $this->context->getScheme();
            if (isset($requirements['_scheme']) && ($req = strtolower($requirements['_scheme'])) && $scheme != $req) {
                $absolute = true;
                $scheme = $req;
            }

            if ($absolute) {
                $port = '';
                if ('http' === $scheme && 80 != $this->context->getHttpPort()) {
                    $port = ':'.$this->context->getHttpPort();
                } elseif ('https' === $scheme && 443 != $this->context->getHttpsPort()) {
                    $port = ':'.$this->context->getHttpsPort();
                }

                $url = $scheme.'://'.$this->context->getHost().$port.$url;
            }
        }

        return $url;
    }
}
