<?php

namespace Symfony\Bundle\FrameworkBundle\Controller;

use Symfony\Framework\Kernel;
use Symfony\Components\HttpKernel\LoggerInterface;

/*
 * This file is part of the Symfony framework.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

/**
 * ControllerNameConverter converts controller from the short notation a:b:c
 * (BlogBundle:Post:index) to a fully-qualified class::method string
 * (Bundle\BlogBundle\Controller\PostController::indexAction); and the other
 * way around.
 *
 * @author Fabien Potencier <fabien.potencier@symfony-project.com>
 */
class ControllerNameConverter
{
    protected $kernel;
    protected $logger;
    protected $namespaces;

    /**
     * Constructor.
     *
     * @param Kernel          $kernel A Kernel instance
     * @param LoggerInterface $logger A LoggerInterface instance
     */
    public function __construct(Kernel $kernel, LoggerInterface $logger = null)
    {
        $this->kernel = $kernel;
        $this->logger = $logger;
        $this->namespaces = array_keys($kernel->getBundleDirs());
    }

    /**
     * Converts a class::method string to the short notation a:b:c.
     *
     * @param string $controller A controler (class::method)
     *
     * @return string A short notation controller (a:b:c)
     */
    public function toShortNotation($controller)
    {
        if (2 != count($parts = explode('::', $controller))) {
            throw new \InvalidArgumentException(sprintf('The "%s" controller is not a valid class::method controller string.', $controller));
        }

        list($class, $method) = $parts;

        if (!preg_match('/Action$/', $method)) {
            throw new \InvalidArgumentException(sprintf('The "%s::%s" method does not look like a controller action (it does not end with Action)', $class, $method));
        }
        $action = substr($method, 0, -6);

        if (!preg_match('/Controller\\\(.*)Controller$/', $class, $match)) {
            throw new \InvalidArgumentException(sprintf('The "%s" class does not look like a controller class (it does not end with Controller)', $class));
        }
        $controller = $match[1];

        $bundle = null;
        $namespace = substr($class, 0, strrpos($class, '\\'));
        foreach ($this->namespaces as $prefix) {
            if (0 === $pos = strpos($namespace, $prefix)) {
                // -11 to remove the \Controller suffix (11 characters)
                $bundle = substr($namespace, strlen($prefix) + 1, -11);
            }
        }

        if (null === $bundle) {
            throw new \InvalidArgumentException(sprintf('The "%s" class does not belong to a known bundle namespace.', $class));
        }

        return $bundle.':'.$controller.':'.$action;
    }

    /**
     * Converts a short notation a:b:c ro a class::method.
     *
     * @param string $controller A short notation controller (a:b:c)
     *
     * @param string A controler (class::method)
     */
    public function fromShortNotation($controller)
    {
        if (3 != count($parts = explode(':', $controller))) {
            throw new \InvalidArgumentException(sprintf('The "%s" controller is not a valid a:b:c controller string.', $controller));
        }

        list($bundle, $controller, $action) = $parts;
        $bundle = strtr($bundle, array('/' => '\\'));
        $class = null;
        $logs = array();
        foreach ($this->namespaces as $namespace) {
            $try = $namespace.'\\'.$bundle.'\\Controller\\'.$controller.'Controller';
            if (!class_exists($try)) {
                if (null !== $this->logger) {
                    $logs[] = sprintf('Failed finding controller "%s:%s" from namespace "%s" (%s)', $bundle, $controller, $namespace, $try);
                }
            } else {
                if (!$this->kernel->isClassInActiveBundle($try)) {
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

        return $class.'::'.$action.'Action';
    }
}
