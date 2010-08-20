<?php

namespace Symfony\Bundle\FrameworkBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller;
use Symfony\Component\HttpFoundation\Response;

/*
 * This file is part of the Symfony framework.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

/**
 *
 *
 * @author     Fabien Potencier <fabien.potencier@symfony-project.com>
 */
class RedirectController extends Controller
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
            $response = $this['response'];
            $response->setStatusCode(410);

            return $response;
        }

        $code = $permanent ? 301 : 302;

        $attributes = $this['request']->attributes->all();
        unset($attributes['_route'], $attributes['route']);

        $response = $this['response'];
        $response->setRedirect($this['router']->generate($route, $attributes), $code);

        return $response;
    }
}
