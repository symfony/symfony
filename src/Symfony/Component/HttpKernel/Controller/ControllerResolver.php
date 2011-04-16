<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\Controller;

use Symfony\Component\HttpKernel\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * ControllerResolver.
 *
 * This implementation uses the '_controller' request attribute to determine
 * the controller to execute and uses the request attributes to determine
 * the controller method arguments.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class ControllerResolver implements ControllerResolverInterface
{
    private $logger;

    /**
     * Constructor.
     *
     * @param LoggerInterface $logger A LoggerInterface instance
     */
    public function __construct(LoggerInterface $logger = null)
    {
        $this->logger = $logger;
    }

    /**
     * Returns the Controller instance associated with a Request.
     *
     * This method looks for a '_controller' request attribute that represents
     * the controller name (a string like ClassName::MethodName).
     *
     * @param Request $request A Request instance
     *
     * @return mixed|Boolean A PHP callable representing the Controller,
     *                       or false if this resolver is not able to determine the controller
     *
     * @throws \InvalidArgumentException|\LogicException If the controller can't be found
     */
    public function getController(Request $request)
    {
        if (!$controller = $request->attributes->get('_controller')) {
            if (null !== $this->logger) {
                $this->logger->err('Unable to look for the controller as the "_controller" parameter is missing');
            }

            return false;
        }

        if (is_array($controller) || method_exists($controller, '__invoke')) {
            return $controller;
        }

        list($controller, $method) = $this->createController($controller);

        if (!method_exists($controller, $method)) {
            throw new \InvalidArgumentException(sprintf('Method "%s::%s" does not exist.', get_class($controller), $method));
        }

        if (null !== $this->logger) {
            $this->logger->info(sprintf('Using controller "%s::%s"', get_class($controller), $method));
        }

        return array($controller, $method);
    }

    /**
     * Returns the arguments to pass to the controller.
     *
     * @param Request $request    A Request instance
     * @param mixed   $controller A PHP callable
     *
     * @throws \RuntimeException When value for argument given is not provided
     */
    public function getArguments(Request $request, $controller)
    {
        $attributes = $request->attributes->all();

        if (is_array($controller)) {
            $r = new \ReflectionMethod($controller[0], $controller[1]);
            $repr = sprintf('%s::%s()', get_class($controller[0]), $controller[1]);
        } elseif (is_object($controller)) {
            $r = new \ReflectionObject($controller);
            $r = $r->getMethod('__invoke');
            $repr = get_class($controller);
        } else {
            $r = new \ReflectionFunction($controller);
            $repr = $controller;
        }

        $arguments = array();
        foreach ($r->getParameters() as $param) {
            if (array_key_exists($param->getName(), $attributes)) {
                $arguments[] = $attributes[$param->getName()];
            } elseif ($param->getClass() && $param->getClass()->isInstance($request)) {
                $arguments[] = $request;
            } elseif ($param->isDefaultValueAvailable()) {
                $arguments[] = $param->getDefaultValue();
            } else {
                throw new \RuntimeException(sprintf('Controller "%s" requires that you provide a value for the "$%s" argument (because there is no default value or because there is a non optional argument after this one).', $repr, $param->getName()));
            }
        }

        return $arguments;
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
            throw new \InvalidArgumentException(sprintf('Unable to find controller "%s".', $controller));
        }

        list($class, $method) = explode('::', $controller);

        if (!class_exists($class)) {
            throw new \InvalidArgumentException(sprintf('Class "%s" does not exist.', $class));
        }

        return array(new $class(), $method);
    }
}
