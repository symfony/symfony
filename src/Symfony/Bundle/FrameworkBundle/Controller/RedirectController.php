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
     * If the route empty, the status code will be 410.
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

        return new RedirectResponse($this->container->get('router')->generate($route, $attributes), $permanent ? 301 : 302);
    }

    /**
     * Redirects to a URL.
     *
     * By default, the response status code is 301.
     *
     * If the path is empty, the status code will be 410.
     * If the permanent flag is set, the status code will be 302.
     *
     * @param string  $path      The path to redirect to
     * @param Boolean $permanent Whether the redirect is permanent or not
     * @param Boolean $scheme    The URL scheme (null to keep the current one)
     * @param integer $httpPort  The HTTP port
     * @param integer $httpsPort The HTTPS port
     *
     * @return Response A Response instance
     */
    public function urlRedirectAction($path, $permanent = false, $scheme = null, $httpPort = 80, $httpsPort = 443)
    {
        if (!$path) {
            return new Response(null, 410);
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
        if ('http' === $scheme && 80 != $httpPort) {
            $port = ':'.$httpPort;
        } elseif ('https' === $scheme && 443 != $httpsPort) {
            $port = ':'.$httpsPort;
        }

        $url = $scheme.'://'.$request->getHost().$port.$request->getBaseUrl().$path.$qs;

        return new RedirectResponse($url, $permanent ? 301 : 302);
    }
}
