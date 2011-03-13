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
 *
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
     * It expects a url path parameter.
     * By default, the response status code is 301.
     *
     * If the url is empty, the status code will be 410.
     * If the permanent path parameter is set, the status code will be 302.
     *
     * @param string  $url       The url to redirect to
     * @param Boolean $permanent Whether the redirect is permanent or not
     *
     * @return Response A Response instance
     */
    public function urlRedirectAction($url, $permanent = false)
    {
        if (!$url) {
            return new Response(null, 410);
        }

        return new RedirectResponse($url, $permanent ? 301 : 302);
    }
}
