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
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Redirects a request to another URL.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class RedirectController extends ContainerAware
{
    /**
     * Redirects to another route with the given name.
     *
     * The response status code is 301 if the permanent parameter is false (default),
     * and 302 if the redirection is permanent.
     *
     * In case the route name is empty, the status code will be 404 when permanent is false
     * and 410 otherwise.
     *
     * @param string  $route     The route name to redirect to
     * @param Boolean $permanent Whether the redirection is permanent
     *
     * @return Response A Response instance
     */
    public function redirectAction($route, $permanent = false)
    {
        if ('' == $route) {
            return new Response(null, $permanent ? 410 : 404);
        }

        $attributes = $this->container->get('request')->attributes->get('_route_params');
        unset($attributes['route'], $attributes['permanent']);

        return new RedirectResponse($this->container->get('router')->generate($route, $attributes, UrlGeneratorInterface::ABSOLUTE_URL), $permanent ? 301 : 302);
    }

    /**
     * Redirects to a URL.
     *
     * The response status code is 301 if the permanent parameter is false (default),
     * and 302 if the redirection is permanent.
     *
     * In case the path is empty, the status code will be 404 when permanent is false
     * and 410 otherwise.
     *
     * @param string       $path      The absolute path or URL to redirect to
     * @param Boolean      $permanent Whether the redirect is permanent or not
     * @param string|null  $scheme    The URL scheme (null to keep the current one)
     * @param integer|null $httpPort  The HTTP port (null to keep the current one for the same scheme or the configured port in the container)
     * @param integer|null $httpsPort The HTTPS port (null to keep the current one for the same scheme or the configured port in the container)
     *
     * @return Response A Response instance
     */
    public function urlRedirectAction($path, $permanent = false, $scheme = null, $httpPort = null, $httpsPort = null)
    {
        if ('' == $path) {
            return new Response(null, $permanent ? 410 : 404);
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
