<?php

namespace Symfony\Framework\FoundationBundle\Controller;

use Symfony\Framework\FoundationBundle\Controller;

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
 * @package    Symfony
 * @subpackage Framework_FoundationBundle
 * @author     Fabien Potencier <fabien.potencier@symfony-project.com>
 */
class RedirectController extends Controller
{
    /*
     * Redirects to another route.
     *
     * It expects a route path parameter.
     * By default, the response status code is 301.
     *
     * If the route empty, the status code will be 410.
     * If the permanent path parameter is set, the status code will be 302.
     */
    public function redirectAction($route, $permanent = false)
    {
        if (!$route) {
            $response = $this->container->getResponseService();
            $response->setStatusCode(410);

            return $response;
        }

        $code = $permanent ? 301 : 302;

        $parameters = $this->getRequest()->getPathParameters();
        unset($parameters['_route'], $parameters['route']);

        return $this->redirect($this->container->getRouterService()->generate($route, $parameters), $code);
    }
}
