<?php

namespace Symfony\Framework\WebBundle\Controller;

use Symfony\Components\HttpKernel\LoggerInterface;
use Symfony\Components\DependencyInjection\ContainerInterface;
use Symfony\Components\HttpKernel\HttpKernelInterface;
use Symfony\Components\HttpKernel\Request;

/*
 * This file is part of the Symfony framework.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

/**
 * ControllerManager.
 *
 * @package    Symfony
 * @subpackage Framework_WebBundle
 * @author     Fabien Potencier <fabien.potencier@symfony-project.com>
 */
class ControllerManager
{
    protected $container;
    protected $logger;
    protected $esiSupport;

    public function __construct(ContainerInterface $container, LoggerInterface $logger = null)
    {
        $this->container = $container;
        $this->logger = $logger;
        $this->esiSupport = $container->hasService('esi') && $container->getEsiService()->hasSurrogateEsiCapability($container->getRequestService());
    }

    /**
     * Renders a Controller and returns the Response content.
     *
     * Note that this method generates an esi:include tag only when both the standalone
     * option is set to true and the request has ESI capability (@see Symfony\Components\HttpKernel\Cache\ESI).
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
            'ignore_errors' => true,
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
        } else {
            $options['path']['_controller'] = $controller;
            $options['path']['_format'] = $request->getRequestFormat();
            $subRequest = $request->duplicate($options['query'], null, $options['path']);
        }

        try {
            return $this->container->getKernelService()->handle($subRequest, HttpKernelInterface::EMBEDDED_REQUEST, true);
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
     * Creates the Controller instance associated with the controller string
     *
     * @param string $controller A controller name (a string like BlogBundle:Post:index)
     *
     * @return array An array composed of the Controller instance and the Controller method
     *
     * @throws \InvalidArgumentException|\LogicException If the controller can't be found
     */
    public function findController($controller)
    {
        list($bundle, $controller, $action) = explode(':', $controller);
        $class = null;
        $logs = array();
        foreach (array_keys($this->container->getParameter('kernel.bundle_dirs')) as $namespace) {
            $try = $namespace.'\\'.$bundle.'\\Controller\\'.$controller.'Controller';
            if (!class_exists($try)) {
                if (null !== $this->logger) {
                    $logs[] = sprintf('Failed finding controller "%s:%s" from namespace "%s" (%s)', $bundle, $controller, $namespace, $try);
                }
            } else {
                if (!in_array($namespace.'\\'.$bundle.'\\'.$bundle, array_map(function ($bundle) { return get_class($bundle); }, $this->container->getKernelService()->getBundles()))) {
                    throw new \LogicException(sprintf('To use the "%s" controller, you first need to enable the Bundle "%s" in your Kernel class.', $try, $namespace.'\\'.$bundle));
                }

                $class = $try;

                break;
            }
        }

        if (null === $class) {
            if (null !== $this->logger) {
                foreach ($logs as $log) {
                    $this->logger->info($log);
                }
            }

            throw new \InvalidArgumentException(sprintf('Unable to find controller "%s:%s".', $bundle, $controller));
        }

        $controller = new $class($this->container);

        $method = $action.'Action';
        if (!method_exists($controller, $method)) {
            throw new \InvalidArgumentException(sprintf('Method "%s::%s" does not exist.', $class, $method));
        }

        if (null !== $this->logger) {
            $this->logger->info(sprintf('Using controller "%s::%s"%s', $class, $method, isset($file) ? sprintf(' from file "%s"', $file) : ''));
        }

        return array($controller, $method);
    }

    /**
     * @throws \RuntimeException When value for argument given is not provided
     */
    public function getMethodArguments(array $path, $controller, $method)
    {
        $r = new \ReflectionObject($controller);
        $arguments = array();
        foreach ($r->getMethod($method)->getParameters() as $param) {
            if (array_key_exists($param->getName(), $path)) {
                $arguments[] = $path[$param->getName()];
            } elseif ($param->isDefaultValueAvailable()) {
                $arguments[] = $param->getDefaultValue();
            } else {
                throw new \RuntimeException(sprintf('Controller "%s::%s()" requires that you provide a value for the "$%s" argument (because there is no default value or because there is a non optional argument after this one).', get_class($controller), $method, $param->getName()));
            }
        }

        return $arguments;
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
