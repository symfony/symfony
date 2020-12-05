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

use Psr\Log\LoggerInterface;
use Symfony\Component\Routing\Exception\InvalidParameterException;
use Symfony\Component\Routing\Exception\MissingMandatoryParametersException;
use Symfony\Component\Routing\Exception\RouteNotFoundException;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\RouteCollection;

/**
 * UrlGenerator can generate a URL or a path for any route in the RouteCollection
 * based on the passed parameters.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Tobias Schultze <http://tobion.de>
 */
class UrlGenerator implements UrlGeneratorInterface, ConfigurableRequirementsInterface
{
    // Character classes as defined in RFC 3986 2.2 and 3
    // @see https://tools.ietf.org/html/rfc3986#page-22 ff

    /**
     * these chars are only sub-delimiters that have no predefined meaning and can therefore be used literally
     * so URI producing applications can use these chars to delimit subcomponents in a path segment without being
     * encoded for better readability.
     *
     * @see https://tools.ietf.org/html/rfc3986#page-11 2
     */
    protected const SUB_DELIMITERS = [
        '%21' => '!',
        '%24' => '$',
        '%26' => '&',
        '%27' => '\'', // TODO: we may want an option to include this as original implementation claims usage in HTML, although this is wrong and htmlspecialchars et. al should be used here
        '%28' => '(',
        '%29' => ')',
        '%2A' => '*',
        '%2B' => '+',
        '%2C' => ',',
        '%3B' => ';',
        '%3D' => '=',
    ];

    /**
     * URL path segment separator.
     *
     * @see https://tools.ietf.org/html/rfc3986#page-22 3.3
     */
    protected const PATH_SEPARATOR = ['%2F' => '/'];

    /**
     * URL path allowed characters.
     *
     * the following chars are general delimiters in the URI specification but have only special meaning in the
     * authority component so they can safely be used in the path in unencoded form
     *
     * @see https://tools.ietf.org/html/rfc3986#page-22 3.3
     */
    protected const PATH_QUERY_FRAGMENT_DECODED = [
        '%40' => '@',
        '%3A' => ':',
    ];

    /**
     * URL query and fragment allowed characters.
     *
     * @see https://tools.ietf.org/html/rfc3986#page-22 3.4 and 3.5
     */
    protected const QUERY_FRAGMENT_DECODED = [
        // the following chars are general delimiters in the URI specification but have only special meaning in the authority component
        // so they can safely be used in the path in unencoded form
        '%2F' => '/',
        '%3F' => '?',
    ];

    /**
     * URL query and fragment special characters used as space respectively form urlencoded key value pairs.
     */
    protected const QUERY_FRAGMENT_SPECIAL = [
        '%24' => '+',
        '%26' => '&',
        '%3D' => '=',
    ];

    protected $routes;
    protected $context;

    /**
     * @var bool|null
     */
    protected $strictRequirements = true;

    protected $logger;

    private $defaultLocale;

    public function __construct(RouteCollection $routes, RequestContext $context, LoggerInterface $logger = null, string $defaultLocale = null)
    {
        $this->routes = $routes;
        $this->context = $context;
        $this->logger = $logger;
        $this->defaultLocale = $defaultLocale;
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
    public function setStrictRequirements(?bool $enabled)
    {
        $this->strictRequirements = $enabled;
    }

    /**
     * {@inheritdoc}
     */
    public function isStrictRequirements()
    {
        return $this->strictRequirements;
    }

    /**
     * {@inheritdoc}
     */
    public function generate(string $name, array $parameters = [], int $referenceType = self::ABSOLUTE_PATH)
    {
        $route = null;
        $locale = $parameters['_locale']
            ?? $this->context->getParameter('_locale')
            ?: $this->defaultLocale;

        if (null !== $locale) {
            do {
                if (null !== ($route = $this->routes->get($name.'.'.$locale)) && $route->getDefault('_canonical_route') === $name) {
                    break;
                }
            } while (false !== $locale = strstr($locale, '_', true));
        }

        if (null === $route = $route ?? $this->routes->get($name)) {
            throw new RouteNotFoundException(sprintf('Unable to generate a URL for the named route "%s" as such route does not exist.', $name));
        }

        // the Route has a cache of its own and is not recompiled as long as it does not get modified
        $compiledRoute = $route->compile();

        $defaults = $route->getDefaults();
        $variables = $compiledRoute->getVariables();

        if (isset($defaults['_canonical_route']) && isset($defaults['_locale'])) {
            if (!\in_array('_locale', $variables, true)) {
                unset($parameters['_locale']);
            } elseif (!isset($parameters['_locale'])) {
                $parameters['_locale'] = $defaults['_locale'];
            }
        }

        return $this->doGenerate($variables, $defaults, $route->getRequirements(), $compiledRoute->getTokens(), $parameters, $name, $referenceType, $compiledRoute->getHostTokens(), $route->getSchemes());
    }

    /**
     * @throws MissingMandatoryParametersException When some parameters are missing that are mandatory for the route
     * @throws InvalidParameterException           When a parameter value for a placeholder is not correct because
     *                                             it does not match the requirement
     *
     * @return string
     */
    protected function doGenerate(array $variables, array $defaults, array $requirements, array $tokens, array $parameters, string $name, int $referenceType, array $hostTokens, array $requiredSchemes = [])
    {
        $variables = array_flip($variables);
        $mergedParams = array_replace($defaults, $this->context->getParameters(), $parameters);

        // all params must be given
        if ($diff = array_diff_key($variables, $mergedParams)) {
            throw new MissingMandatoryParametersException(sprintf('Some mandatory parameters are missing ("%s") to generate a URL for route "%s".', implode('", "', array_keys($diff)), $name));
        }

        $url = '';
        $optional = true;
        $message = 'Parameter "{parameter}" for route "{route}" must match "{expected}" ("{given}" given) to generate a corresponding URL.';
        foreach ($tokens as $token) {
            if ('variable' === $token[0]) {
                $varName = $token[3];
                // variable is not important by default
                $important = $token[5] ?? false;

                if (!$optional || $important || !\array_key_exists($varName, $defaults) || (null !== $mergedParams[$varName] && (string) $mergedParams[$varName] !== (string) $defaults[$varName])) {
                    // check requirement (while ignoring look-around patterns)
                    if (null !== $this->strictRequirements && !preg_match('#^'.preg_replace('/\(\?(?:=|<=|!|<!)((?:[^()\\\\]+|\\\\.|\((?1)\))*)\)/', '', $token[2]).'$#i'.(empty($token[4]) ? '' : 'u'), $mergedParams[$token[3]])) {
                        if ($this->strictRequirements) {
                            throw new InvalidParameterException(strtr($message, ['{parameter}' => $varName, '{route}' => $name, '{expected}' => $token[2], '{given}' => $mergedParams[$varName]]));
                        }

                        if ($this->logger) {
                            $this->logger->error($message, ['parameter' => $varName, 'route' => $name, 'expected' => $token[2], 'given' => $mergedParams[$varName]]);
                        }

                        return '';
                    }

                    $url = $token[1].self::encodeVariable($mergedParams[$varName]).$url;
                    $optional = false;
                }
            } else {
                // static text
                $url = self::encodePath($token[1]).$url;
                $optional = false;
            }
        }

        if ('' === $url) {
            $url = '/';
        }

        // the path segments "." and ".." are interpreted as relative reference when resolving a URI; see http://tools.ietf.org/html/rfc3986#section-3.3
        // so we need to encode them as they are not used for this purpose here
        // otherwise we would generate a URI that, when followed by a user agent (e.g. browser), does not match this route
        $url = strtr($url, ['/../' => '/%2E%2E/', '/./' => '/%2E/']);
        if ('/..' === substr($url, -3)) {
            $url = substr($url, 0, -2).'%2E%2E';
        } elseif ('/.' === substr($url, -2)) {
            $url = substr($url, 0, -1).'%2E';
        }

        $schemeAuthority = '';
        $host = $this->context->getHost();
        $scheme = $this->context->getScheme();

        if ($requiredSchemes) {
            if (!\in_array($scheme, $requiredSchemes, true)) {
                $referenceType = self::ABSOLUTE_URL;
                $scheme = current($requiredSchemes);
            }
        }

        if ($hostTokens) {
            $routeHost = '';
            foreach ($hostTokens as $token) {
                if ('variable' === $token[0]) {
                    // check requirement (while ignoring look-around patterns)
                    if (null !== $this->strictRequirements && !preg_match('#^'.preg_replace('/\(\?(?:=|<=|!|<!)((?:[^()\\\\]+|\\\\.|\((?1)\))*)\)/', '', $token[2]).'$#i'.(empty($token[4]) ? '' : 'u'), $mergedParams[$token[3]])) {
                        if ($this->strictRequirements) {
                            throw new InvalidParameterException(strtr($message, ['{parameter}' => $token[3], '{route}' => $name, '{expected}' => $token[2], '{given}' => $mergedParams[$token[3]]]));
                        }

                        if ($this->logger) {
                            $this->logger->error($message, ['parameter' => $token[3], 'route' => $name, 'expected' => $token[2], 'given' => $mergedParams[$token[3]]]);
                        }

                        return '';
                    }

                    $routeHost = $token[1].$mergedParams[$token[3]].$routeHost;
                } else {
                    $routeHost = $token[1].$routeHost;
                }
            }

            if ($routeHost !== $host) {
                $host = $routeHost;
                if (self::ABSOLUTE_URL !== $referenceType) {
                    $referenceType = self::NETWORK_PATH;
                }
            }
        }

        if (self::ABSOLUTE_URL === $referenceType || self::NETWORK_PATH === $referenceType) {
            if ('' !== $host || ('' !== $scheme && 'http' !== $scheme && 'https' !== $scheme)) {
                $port = '';
                if ('http' === $scheme && 80 !== $this->context->getHttpPort()) {
                    $port = ':'.$this->context->getHttpPort();
                } elseif ('https' === $scheme && 443 !== $this->context->getHttpsPort()) {
                    $port = ':'.$this->context->getHttpsPort();
                }

                $schemeAuthority = self::NETWORK_PATH === $referenceType || '' === $scheme ? '//' : "$scheme://";
                $schemeAuthority .= $host.$port;
            }
        }

        if (self::RELATIVE_PATH === $referenceType) {
            $url = self::getRelativePath($this->context->getPathInfo(), $url);
        } else {
            $url = $schemeAuthority.$this->context->getBaseUrl().$url;
        }

        // add a query string if needed
        $extra = array_udiff_assoc(array_diff_key($parameters, $variables), $defaults, function ($a, $b) {
            return $a == $b ? 0 : 1;
        });

        // extract fragment
        $fragment = $defaults['_fragment'] ?? '';

        if (isset($extra['_fragment'])) {
            $fragment = $extra['_fragment'];
            unset($extra['_fragment']);
        }

        if ($extra && $query = http_build_query($extra, '', '&', \PHP_QUERY_RFC3986)) {
            $url .= '?'.strtr($query, self::decodeQueryFragmentChars());
        }

        if ('' !== $fragment) {
            $url .= '#'.strtr(rawurlencode($fragment), self::decodeQueryFragmentChars());
        }

        return $url;
    }

    /**
     * Returns the target path as relative reference from the base path.
     *
     * Only the URIs path component (no schema, host etc.) is relevant and must be given, starting with a slash.
     * Both paths must be absolute and not contain relative parts.
     * Relative URLs from one resource to another are useful when generating self-contained downloadable document archives.
     * Furthermore, they can be used to reduce the link size in documents.
     *
     * Example target paths, given a base path of "/a/b/c/d":
     * - "/a/b/c/d"     -> ""
     * - "/a/b/c/"      -> "./"
     * - "/a/b/"        -> "../"
     * - "/a/b/c/other" -> "other"
     * - "/a/x/y"       -> "../../x/y"
     *
     * @param string $basePath   The base path
     * @param string $targetPath The target path
     *
     * @return string The relative target path
     */
    public static function getRelativePath(string $basePath, string $targetPath)
    {
        if ($basePath === $targetPath) {
            return '';
        }

        $sourceDirs = explode('/', isset($basePath[0]) && '/' === $basePath[0] ? substr($basePath, 1) : $basePath);
        $targetDirs = explode('/', isset($targetPath[0]) && '/' === $targetPath[0] ? substr($targetPath, 1) : $targetPath);
        array_pop($sourceDirs);
        $targetFile = array_pop($targetDirs);

        foreach ($sourceDirs as $i => $dir) {
            if (isset($targetDirs[$i]) && $dir === $targetDirs[$i]) {
                unset($sourceDirs[$i], $targetDirs[$i]);
            } else {
                break;
            }
        }

        $targetDirs[] = $targetFile;
        $path = str_repeat('../', \count($sourceDirs)).implode('/', $targetDirs);

        // A reference to the same base directory or an empty subdirectory must be prefixed with "./".
        // This also applies to a segment with a colon character (e.g., "file:colon") that cannot be used
        // as the first segment of a relative-path reference, as it would be mistaken for a scheme name
        // (see http://tools.ietf.org/html/rfc3986#section-4.2).
        return '' === $path || '/' === $path[0]
            || false !== ($colonPos = strpos($path, ':')) && ($colonPos < ($slashPos = strpos($path, '/')) || false === $slashPos)
            ? "./$path" : $path;
    }

    private static function encodePath(string $path): string
    {
        return strtr(rawurlencode($path), self::decodePathChars());
    }

    private static function encodeVariable(string $var): string
    {
        return strtr(rawurlencode($var), self::decodeVariableChars());
    }

    private static function decodePathChars(): array
    {
        return self::PATH_SEPARATOR + self::PATH_QUERY_FRAGMENT_DECODED + self::SUB_DELIMITERS;
    }

    private static function decodeVariableChars(): array
    {
        return self::PATH_QUERY_FRAGMENT_DECODED + self::SUB_DELIMITERS;
    }

    private static function decodeQueryFragmentChars(): array
    {
        return array_diff(self::PATH_QUERY_FRAGMENT_DECODED + self::QUERY_FRAGMENT_DECODED + self::SUB_DELIMITERS, self::QUERY_FRAGMENT_SPECIAL);
    }
}
