<?php

namespace Symfony\Bundle\FrameworkBundle;

use Symfony\Components\DependencyInjection\ContainerInterface;
use Symfony\Components\HttpFoundation\Response;

/*
 * This file is part of the Symfony framework.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

/**
 * FrameworkBundle Controller gives you convenient access to all commonly needed services.
 *
 * @author     Fabien Potencier <fabien.potencier@symfony-project.com>
 */
class Controller implements \ArrayAccess
{
    protected $container;

    /**
     * Constructor.
     *
     * @param ContainerInterface $container A ContainerInterface instance
     */
    function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    /**
     * Creates a Response instance.
     *
     * @param string  $content The Response body
     * @param integer $status  The status code
     * @param array   $headers An array of HTTP headers
     *
     * @return Response A Response instance
     */
    public function createResponse($content = '', $status = 200, array $headers = array())
    {
        $response = $this->container->get('response');
        $response->setContent($content);
        $response->setStatusCode($status);
        foreach ($headers as $name => $value) {
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
        return $this->container->get('router')->generate($route, $parameters, $absolute);
    }

    /**
     * Forwards the request to another controller.
     *
     * @param  string  $controller The controller name (a string like BlogBundle:Post:index)
     * @param  array   $path       An array of path parameters
     * @param  array   $query      An array of query parameters
     *
     * @return Response A Response instance
     */
    public function forward($controller, array $path = array(), array $query = array())
    {
        return $this->container->get('controller_resolver')->forward($controller, $path, $query);
    }

    /**
     * Returns an HTTP redirect Response.
     *
     * @return Response A Response instance
     */
    public function redirect($url, $status = 302)
    {
        $response = $this->container->get('response');
        $response->setRedirect($url, $status);
        return $response;
    }

    /**
     * Returns a rendered view.
     *
     * @param string $view       The view name
     * @param array  $parameters An array of parameters to pass to the view
     *
     * @return string The renderer view
     */
    public function renderView($view, array $parameters = array())
    {
        return $this->container->get('templating')->render($view, $parameters);
    }

    /**
     * Renders a view.
     *
     * @param string   $view The view name
     * @param array    $parameters An array of parameters to pass to the view
     * @param Response $response A response instance
     *
     * @return Response A Response instance
     */
    public function render($view, array $parameters = array(), Response $response = null)
    {
        return $this->container->get('templating')->renderResponse($view, $parameters, $response);
    }

    /**
     * Returns true if the service id is defined (implements the ArrayAccess interface).
     *
     * @param  string  $id The service id
     *
     * @return Boolean true if the service id is defined, false otherwise
     */
    public function offsetExists($id)
    {
        return $this->container->has($id);
    }

    /**
     * Gets a service by id (implements the ArrayAccess interface).
     *
     * @param  string $id The service id
     *
     * @return mixed  The parameter value
     */
    public function offsetGet($id)
    {
        return $this->container->get($id);
    }

    /**
     * Sets a service (implements the ArrayAccess interface).
     *
     * @param string $id    The service id
     * @param object $value The service
     */
    public function offsetSet($id, $value)
    {
        throw new \LogicException(sprintf('You can\'t set a service from a controller (%s).', $id));
    }

    /**
     * Removes a service (implements the ArrayAccess interface).
     *
     * @param string $id The service id
     */
    public function offsetUnset($id)
    {
        throw new \LogicException(sprintf('You can\'t unset a service from a controller (%s).', $id));
    }
}
