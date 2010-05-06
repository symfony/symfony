<?php

namespace Symfony\Framework\WebBundle;

use Symfony\Components\DependencyInjection\ContainerInterface;
use Symfony\Components\HttpKernel\Request;
use Symfony\Components\HttpKernel\Response;

/*
 * This file is part of the Symfony framework.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

/**
 * WebBundle Controller gives you convenient access to all commonly needed services.
 *
 * @package    Symfony
 * @subpackage Framework_WebBundle
 * @author     Fabien Potencier <fabien.potencier@symfony-project.com>
 */
class Controller
{
    protected $container;
    protected $request;

    function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    public function getRequest()
    {
        if (null === $this->request)
        {
            $this->request = $this->container->getRequestService();
        }

        return $this->request;
    }

    public function setRequest(Request $request)
    {
        return $this->request = $request;
    }

    public function getUser()
    {
        return $this->container->getUserService();
    }

    public function getMailer()
    {
        return $this->container->getMailerService();
    }

    public function createResponse($content = '', $status = 200, array $headers = array())
    {
        $response = $this->container->getResponseService();
        $response->setContent($content);
        $response->setStatusCode($status);
        foreach ($headers as $name => $value)
        {
            $response->headers->set($name, $value);
        }

        return $response;
    }

    /**
     * Generates a URL from the given parameters.
     *
     * @param  string  $name       The name of the route
     * @param  array   $parameters An array of parameters
     * @param  Boolean $absolute   Whether to generate an absolute URL
     *
     * @return string The generated URL
     */
    public function generateUrl($route, array $parameters = array(), $absolute = false)
    {
        return $this->container->getRouterService()->generate($route, $parameters);
    }

    public function forward($controller, array $parameters = array())
    {
        return $this->container->getControllerLoaderService()->run($controller, $parameters);
    }

    /**
     * Sends an HTTP redirect response
     */
    public function redirect($url, $status = 302)
    {
        $response = $this->container->getResponseService();
        $response->setStatusCode($status);
        $response->headers->set('Location', $url);

        return $response;
    }

    public function renderView($view, array $parameters = array())
    {
        return $this->container->getTemplatingService()->render($view, $parameters);
    }

    /**
     * Renders a view.
     *
     * @param string   $view       The view name
     * @param array    $parameters An array of parameters to pass to the view
     * @param Response $response   A response instance
     *
     * @return Response A Response instance
     */
    public function render($view, array $parameters = array(), Response $response = null)
    {
        if (null === $response)
        {
            $response = $this->container->getResponseService();
        }

        $response->setContent($this->container->getTemplatingService()->render($view, $parameters));

        return $response;
    }
}
