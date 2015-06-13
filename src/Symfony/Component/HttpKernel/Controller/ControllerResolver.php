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

use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Controller\ArgumentResolver\RequestArgumentResolver;
use Symfony\Component\HttpKernel\Controller\ArgumentResolver\RequestAttributesArgumentResolver;

/**
 * ControllerResolver.
 *
 * This implementation uses the '_controller' request attribute to determine
 * the controller to execute and uses the request attributes to determine
 * the controller method arguments.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 *
 * @api
 */
class ControllerResolver implements ControllerResolverInterface
{
    private $logger;

    /**
     * @var ArgumentResolverManager
     */
    private $argumentResolverManager;

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
     * @internal
     */
    public function setArgumentResolverManager(ArgumentResolverManager $argumentResolverManager)
    {
        $this->argumentResolverManager = $argumentResolverManager;
    }

    /**
     * {@inheritdoc}
     *
     * This method looks for a '_controller' request attribute that represents
     * the controller name (a string like ClassName::MethodName).
     *
     * @api
     */
    public function getController(Request $request)
    {
        if (!$controller = $request->attributes->get('_controller')) {
            if (null !== $this->logger) {
                $this->logger->warning('Unable to look for the controller as the "_controller" parameter is missing.');
            }

            return false;
        }

        if (is_array($controller)) {
            return $controller;
        }

        if (is_object($controller)) {
            if (method_exists($controller, '__invoke')) {
                return $controller;
            }

            throw new \InvalidArgumentException(sprintf('Controller "%s" for URI "%s" is not callable.', get_class($controller), $request->getPathInfo()));
        }

        if (false === strpos($controller, ':')) {
            if (method_exists($controller, '__invoke')) {
                return $this->instantiateController($controller);
            } elseif (function_exists($controller)) {
                return $controller;
            }
        }

        $callable = $this->createController($controller);

        if (!is_callable($callable)) {
            throw new \InvalidArgumentException(sprintf('Controller "%s" for URI "%s" is not callable.', $controller, $request->getPathInfo()));
        }

        return $callable;
    }

    /**
     * {@inheritdoc}
     *
     * @api
     */
    public function getArguments(Request $request, $controller)
    {
        if (is_array($controller)) {
            $r = new \ReflectionMethod($controller[0], $controller[1]);
        } elseif (is_object($controller) && !$controller instanceof \Closure) {
            $r = new \ReflectionObject($controller);
            $r = $r->getMethod('__invoke');
        } else {
            $r = new \ReflectionFunction($controller);
        }

        $reflector = new \ReflectionMethod($this, 'doGetArguments');
        if ($reflector->getDeclaringClass()->getName() !== __CLASS__) {
            @trigger_error('The ControllerResolverInterface::doGetArguments() method is deprecated since version 2.8 and will be removed in 3.0. Use the ArgumentResolverManager and custom ArgumentResolverInterface implementations instead.', E_USER_DEPRECATED);
        }

        return $this->doGetArguments($request, $controller, $r->getParameters());
    }

    /**
     * @deprecated As of Symfony 2.8, to be removed in Symfony 3.0. Create a custom ArgumentResolverInterface implementation instead.
     */
    protected function doGetArguments(Request $request, $controller, array $parameters)
    {
        return $this->getArgumentResolverManager()->getArguments($request, $controller);
    }

    /**
     * Returns a callable for the given controller.
     *
     * @param string $controller A Controller string
     *
     * @return mixed A PHP callable
     *
     * @throws \InvalidArgumentException
     */
    protected function createController($controller)
    {
        if (false === strpos($controller, '::')) {
            throw new \InvalidArgumentException(sprintf('Unable to find controller "%s".', $controller));
        }

        list($class, $method) = explode('::', $controller, 2);

        if (!class_exists($class)) {
            throw new \InvalidArgumentException(sprintf('Class "%s" does not exist.', $class));
        }

        return array($this->instantiateController($class), $method);
    }

    /**
     * Returns an instantiated controller.
     *
     * @param string $class A class name
     *
     * @return object
     */
    protected function instantiateController($class)
    {
        return new $class();
    }

    private function getArgumentResolverManager()
    {
        if (null === $this->argumentResolverManager) {
            $this->argumentResolverManager = new ArgumentResolverManager(array(
                new RequestArgumentResolver(),
                new RequestAttributesArgumentResolver(),
            ));
        }

        return $this->argumentResolverManager;
    }
}
