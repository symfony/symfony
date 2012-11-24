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

use Symfony\Component\DependencyInjection\ContainerAware;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Redirects a request to another URL.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class RedirectController extends ContainerAware
{
    /**
     * Redirects to another route.
     *
     * It expects a route path parameter.
     * By default, the response status code is 301.
     *
     * If the route is empty, the status code will be 410.
     * If the permanent path parameter is set, the status code will be 302.
     *
     * @param string  $route     The route pattern to redirect to
     * @param Boolean $permanent Whether the redirect is permanent or not
     *
     * @return Response A Response instance
     */
    public function redirectAction($route, $permanent = false)
    {
        if (!$route) {
            return new Response(null, 410);
        }

        $attributes = $this->container->get('request')->attributes->all();
        unset($attributes['_route'], $attributes['route'], $attributes['permanent'] );

        return new RedirectResponse($this->container->get('router')->generate($route, $attributes, true), $permanent ? 301 : 302);
    }

    /**
     * Redirects to a URL.
     *
     * By default, the response status code is 301.
     *
     * If the path is empty, the status code will be 410.
     * If the permanent flag is set, the status code will be 302.
     *
     * @param string       $path      The path to redirect to
     * @param Boolean      $permanent Whether the redirect is permanent or not
     * @param string|null  $scheme    The URL scheme (null to keep the current one)
     * @param integer|null $httpPort  The HTTP port (null to keep the current one for the same scheme or the configured port in the container)
     * @param integer|null $httpsPort The HTTPS port (null to keep the current one for the same scheme or the configured port in the container)
     *
     * @return Response A Response instance
     */
    public function urlRedirectAction($path, $permanent = false, $scheme = null, $httpPort = null, $httpsPort = null)
    {
        if (!$path) {
            return new Response(null, 410);
        }

        $statusCode = $permanent ? 301 : 302;

        // redirect if the path is a full URL
        if (parse_url($path, PHP_URL_SCHEME)) {
            return new RedirectResponse($path, $statusCode);
        }

        $request = $this->container->get('request');
        if (null === $scheme) {
            $scheme = $request->getScheme();
        }

        $qs = $request->getQueryString();
        if ($qs) {
            $qs = '?'.$qs;
        }

        $port = '';
        if ('http' === $scheme) {
            if (null === $httpPort) {
                if ('http' === $request->getScheme()) {
                    $httpPort = $request->getPort();
                } elseif ($this->container->hasParameter('request_listener.http_port')) {
                    $httpPort = $this->container->getParameter('request_listener.http_port');
                }
            }

            if (null !== $httpPort && 80 != $httpPort) {
                $port = ":$httpPort";
            }
        } elseif ('https' === $scheme) {
            if (null === $httpsPort) {
                if ('https' === $request->getScheme()) {
                    $httpsPort = $request->getPort();
                } elseif ($this->container->hasParameter('request_listener.https_port')) {
                    $httpsPort = $this->container->getParameter('request_listener.https_port');
                }
            }

            if (null !== $httpsPort && 443 != $httpsPort) {
                $port = ":$httpsPort";
            }
        }

        $url = $scheme.'://'.$request->getHost().$port.$request->getBaseUrl().$path.$qs;

        return new RedirectResponse($url, $statusCode);
    }
}
