<?php

namespace Symfony\Bundle\FrameworkBundle\Controller;

use Symfony\Component\HttpKernel\Log\LoggerInterface;
use Symfony\Component\HttpKernel\Controller\ControllerResolver as BaseControllerResolver;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\EventDispatcher\Event;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\ControllerNameConverter;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;

/*
 * This file is part of the Symfony framework.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

/**
 * ControllerResolver.
 *
 * @author Fabien Potencier <fabien.potencier@symfony-project.com>
 */
class ControllerResolver extends BaseControllerResolver
{
    protected $container;
    protected $converter;
    protected $esiSupport;

    /**
     * Constructor.
     *
     * @param ContainerInterface      $container A ContainerInterface instance
     * @param ControllerNameConverter $converter A ControllerNameConverter instance
     * @param LoggerInterface         $logger    A LoggerInterface instance
     */
    public function __construct(ContainerInterface $container, ControllerNameConverter $converter, LoggerInterface $logger = null)
    {
        $this->container = $container;
        $this->converter = $converter;
        $this->esiSupport = $container->has('esi') && $container->getEsiService()->hasSurrogateEsiCapability($container->getRequestService());

        parent::__construct($logger);
    }

    /**
     * Returns a callable for the given controller.
     *
     * @param string $controller A Controller string
     *
     * @return mixed A PHP callable
     */
    protected function createController($controller)
    {
        if (false === strpos($controller, '::')) {
            // must be a controller in the a:b:c notation then
            $controller = $this->converter->fromShortNotation($controller);
        }

        list($class, $method) = explode('::', $controller);

        if (!class_exists($class)) {
            throw new \InvalidArgumentException(sprintf('Class "%s" does not exist.', $class));
        }

        $controller = new $class();
        if ($controller instanceof ContainerAwareInterface) {
            $controller->setContainer($this->container);
        }

        return array($controller, $method);
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
        $path['_controller'] = $controller;
        $subRequest = $this->container->getRequestService()->duplicate($query, null, $path);

        return $this->container->get('kernel')->handle($subRequest, HttpKernelInterface::SUB_REQUEST);
    }

    /**
     * Renders a Controller and returns the Response content.
     *
     * Note that this method generates an esi:include tag only when both the standalone
     * option is set to true and the request has ESI capability (@see Symfony\Component\HttpKernel\Cache\ESI).
     *
     * Available options:
     *
     *  * path: An array of path parameters (only when the first argument is a controller)
     *  * query: An array of query parameters (only when the first argument is a controller)
     *  * ignore_errors: true to return an empty string in case of an error
     *  * alt: an alternative controller to execute in case of an error (can be a controller, a URI, or an array with the controller, the path arguments, and the query arguments)
     *  * standalone: whether to generate an esi:include tag or not when ESI is supported
     *  * comment: a comment to add when returning an esi:include tag
     *
     * @param string $controller A controller name to execute (a string like BlogBundle:Post:index), or a relative URI
     * @param array  $options    An array of options
     *
     * @return string The Response content
     */
    public function render($controller, array $options = array())
    {
        $options = array_merge(array(
            'path'          => array(),
            'query'         => array(),
            'ignore_errors' => !$this->container->getParameter('kernel.debug'),
            'alt'           => array(),
            'standalone'    => false,
            'comment'       => '',
        ), $options);

        if (!is_array($options['alt'])) {
            $options['alt'] = array($options['alt']);
        }

        if ($this->esiSupport && $options['standalone']) {
            $uri = $this->generateInternalUri($controller, $options['path'], $options['query']);

            $alt = '';
            if ($options['alt']) {
                $alt = $this->generateInternalUri($options['alt'][0], isset($options['alt'][1]) ? $options['alt'][1] : array(), isset($options['alt'][2]) ? $options['alt'][2] : array());
            }

            return $this->container->getEsiService()->renderTag($uri, $alt, $options['ignore_errors'], $options['comment']);
        }

        $request = $this->container->getRequestService();

        // controller or URI?
        if (0 === strpos($controller, '/')) {
            $subRequest = Request::create($controller, 'get', array(), $request->cookies->all(), array(), $request->server->all());
            $subRequest->setSession($request->getSession());
        } else {
            $options['path']['_controller'] = $controller;
            $options['path']['_format'] = $request->getRequestFormat();
            $subRequest = $request->duplicate($options['query'], null, $options['path']);
        }

        try {
            $response = $this->container->getKernelService()->handle($subRequest, HttpKernelInterface::SUB_REQUEST, true);

            if (200 != $response->getStatusCode()) {
                throw new \RuntimeException(sprintf('Error when rendering "%s" (Status code is %s).', $request->getUri(), $response->getStatusCode()));
            }

            return $response->getContent();
        } catch (\Exception $e) {
            if ($options['alt']) {
                $alt = $options['alt'];
                unset($options['alt']);
                $options['path'] = isset($alt[1]) ? $alt[1] : array();
                $options['query'] = isset($alt[2]) ? $alt[2] : array();

                return $this->render($alt[0], $options);
            }

            if (!$options['ignore_errors']) {
                throw $e;
            }
        }
    }

    /**
     * Generates an internal URI for a given controller.
     *
     * This method uses the "_internal" route, which should be available.
     *
     * @param string $controller A controller name to execute (a string like BlogBundle:Post:index), or a relative URI
     * @param array  $path       An array of path parameters
     * @param array  $query      An array of query parameters
     *
     * @return string An internal URI
     */
    public function generateInternalUri($controller, array $path = array(), array $query = array())
    {
        if (0 === strpos($controller, '/')) {
            return $controller;
        }

        $uri = $this->container->getRouterService()->generate('_internal', array(
            'controller' => $controller,
            'path'       => $path ? http_build_query($path) : 'none',
            '_format'    => $this->container->getRequestService()->getRequestFormat(),
        ), true);

        if ($query) {
            $uri = $uri.'?'.http_build_query($query);
        }

        return $uri;
    }
}
