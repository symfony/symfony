<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Controller;

use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Redirects a request to another URL.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 *
 * @final
 */
class RedirectController
{
    private ?UrlGeneratorInterface $router;
    private ?int $httpPort;
    private ?int $httpsPort;

    public function __construct(?UrlGeneratorInterface $router = null, ?int $httpPort = null, ?int $httpsPort = null)
    {
        $this->router = $router;
        $this->httpPort = $httpPort;
        $this->httpsPort = $httpsPort;
    }

    /**
     * Redirects to another route with the given name.
     *
     * The response status code is 302 if the permanent parameter is false (default),
     * and 301 if the redirection is permanent.
     *
     * In case the route name is empty, the status code will be 404 when permanent is false
     * and 410 otherwise.
     *
     * @param string     $route             The route name to redirect to
     * @param bool       $permanent         Whether the redirection is permanent
     * @param bool|array $ignoreAttributes  Whether to ignore attributes or an array of attributes to ignore
     * @param bool       $keepRequestMethod Whether redirect action should keep HTTP request method
     *
     * @throws HttpException In case the route name is empty
     */
    public function redirectAction(Request $request, string $route, bool $permanent = false, bool|array $ignoreAttributes = false, bool $keepRequestMethod = false, bool $keepQueryParams = false): Response
    {
        if ('' == $route) {
            throw new HttpException($permanent ? 410 : 404);
        }

        $attributes = [];
        if (false === $ignoreAttributes || \is_array($ignoreAttributes)) {
            $attributes = $request->attributes->get('_route_params');

            if ($keepQueryParams) {
                if ($query = $request->server->get('QUERY_STRING')) {
                    $query = HeaderUtils::parseQuery($query);
                } else {
                    $query = $request->query->all();
                }

                $attributes = array_merge($query, $attributes);
            }

            unset($attributes['route'], $attributes['permanent'], $attributes['ignoreAttributes'], $attributes['keepRequestMethod'], $attributes['keepQueryParams']);
            if ($ignoreAttributes) {
                $attributes = array_diff_key($attributes, array_flip($ignoreAttributes));
            }
        }

        if ($keepRequestMethod) {
            $statusCode = $permanent ? 308 : 307;
        } else {
            $statusCode = $permanent ? 301 : 302;
        }

        return new RedirectResponse($this->router->generate($route, $attributes, UrlGeneratorInterface::ABSOLUTE_URL), $statusCode);
    }

    /**
     * Redirects to a URL.
     *
     * The response status code is 302 if the permanent parameter is false (default),
     * and 301 if the redirection is permanent.
     *
     * In case the path is empty, the status code will be 404 when permanent is false
     * and 410 otherwise.
     *
     * @param string      $path              The absolute path or URL to redirect to
     * @param bool        $permanent         Whether the redirect is permanent or not
     * @param string|null $scheme            The URL scheme (null to keep the current one)
     * @param int|null    $httpPort          The HTTP port (null to keep the current one for the same scheme or the default configured port)
     * @param int|null    $httpsPort         The HTTPS port (null to keep the current one for the same scheme or the default configured port)
     * @param bool        $keepRequestMethod Whether redirect action should keep HTTP request method
     *
     * @throws HttpException In case the path is empty
     */
    public function urlRedirectAction(Request $request, string $path, bool $permanent = false, ?string $scheme = null, ?int $httpPort = null, ?int $httpsPort = null, bool $keepRequestMethod = false): Response
    {
        if ('' == $path) {
            throw new HttpException($permanent ? 410 : 404);
        }

        if ($keepRequestMethod) {
            $statusCode = $permanent ? 308 : 307;
        } else {
            $statusCode = $permanent ? 301 : 302;
        }

        // redirect if the path is a full URL
        if (parse_url($path, \PHP_URL_SCHEME)) {
            return new RedirectResponse($path, $statusCode);
        }

        $scheme ??= $request->getScheme();

        if ($qs = $request->server->get('QUERY_STRING') ?: $request->getQueryString()) {
            if (!str_contains($path, '?')) {
                $qs = '?'.$qs;
            } else {
                $qs = '&'.$qs;
            }
        }

        $port = '';
        if ('http' === $scheme) {
            if (null === $httpPort) {
                if ('http' === $request->getScheme()) {
                    $httpPort = $request->getPort();
                } else {
                    $httpPort = $this->httpPort;
                }
            }

            if (null !== $httpPort && 80 != $httpPort) {
                $port = ":$httpPort";
            }
        } elseif ('https' === $scheme) {
            if (null === $httpsPort) {
                if ('https' === $request->getScheme()) {
                    $httpsPort = $request->getPort();
                } else {
                    $httpsPort = $this->httpsPort;
                }
            }

            if (null !== $httpsPort && 443 != $httpsPort) {
                $port = ":$httpsPort";
            }
        }

        $url = $scheme.'://'.$request->getHost().$port.$request->getBaseUrl().$path.$qs;

        return new RedirectResponse($url, $statusCode);
    }

    public function __invoke(Request $request): Response
    {
        $p = $request->attributes->get('_route_params', []);

        if (\array_key_exists('route', $p)) {
            if (\array_key_exists('path', $p)) {
                throw new \RuntimeException(sprintf('Ambiguous redirection settings, use the "path" or "route" parameter, not both: "%s" and "%s" found respectively in "%s" routing configuration.', $p['path'], $p['route'], $request->attributes->get('_route')));
            }

            return $this->redirectAction($request, $p['route'], $p['permanent'] ?? false, $p['ignoreAttributes'] ?? false, $p['keepRequestMethod'] ?? false, $p['keepQueryParams'] ?? false);
        }

        if (\array_key_exists('path', $p)) {
            return $this->urlRedirectAction($request, $p['path'], $p['permanent'] ?? false, $p['scheme'] ?? null, $p['httpPort'] ?? null, $p['httpsPort'] ?? null, $p['keepRequestMethod'] ?? false);
        }

        throw new \RuntimeException(sprintf('The parameter "path" or "route" is required to configure the redirect action in "%s" routing configuration.', $request->attributes->get('_route')));
    }
}
