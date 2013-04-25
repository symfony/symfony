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

use Symfony\Component\HttpKernel\KernelInterface;

/**
 * ControllerNameParser converts controller from the short notation a:b:c
 * (BlogBundle:Post:index) to a fully-qualified class::method string
 * (Bundle\BlogBundle\Controller\PostController::indexAction).
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class ControllerNameParser
{
    protected $kernel;

    /**
     * Constructor.
     *
     * @param KernelInterface $kernel A KernelInterface instance
     */
    public function __construct(KernelInterface $kernel)
    {
        $this->kernel = $kernel;
    }

    /**
     * Converts a short notation a:b:c to a class::method.
     *
     * @param string $controller A short notation controller (a:b:c)
     *
     * @return string A string in the class::method notation
     *
     * @throws \InvalidArgumentException when the specified bundle is not enabled
     *                                   or the controller cannot be found
     */
    public function parse($controller)
    {
        if (3 != count($parts = explode(':', $controller))) {
            throw new \InvalidArgumentException(sprintf('The "%s" controller is not a valid "a:b:c" controller string.', $controller));
        }

        list($bundle, $controller, $action) = $parts;
        $controller = str_replace('/', '\\', $controller);
        $bundles = array();

        // this throws an exception if there is no such bundle
        foreach ($this->kernel->getBundle($bundle, false) as $b) {
            $try = $b->getNamespace().'\\Controller\\'.$controller.'Controller';
            if (class_exists($try)) {
                return $try.'::'.$action.'Action';
            }

            $bundles[] = $b->getName();
            $msg = sprintf('Unable to find controller "%s:%s" - class "%s" does not exist.', $bundle, $controller, $try);
        }

        if (count($bundles) > 1) {
            $msg = sprintf('Unable to find controller "%s:%s" in bundles %s.', $bundle, $controller, implode(', ', $bundles));
        }

        throw new \InvalidArgumentException($msg);
    }

    /**
     * Converts a class::method notation to a short one (a:b:c).
     *
     * @param string $controller A string in the class::method notation
     *
     * @return string A short notation controller (a:b:c)
     *
     * @throws \InvalidArgumentException when the controller is not valid or cannot be found in any bundle
     */
    public function build($controller)
    {
        if (0 === preg_match('#^(.*?\\\\Controller\\\\(.+)Controller)::(.+)Action$#', $controller, $match)) {
            throw new \InvalidArgumentException(sprintf('The "%s" controller is not a valid "class::method" string.', $controller));
        }

        $className = $match[1];
        $controllerName = $match[2];
        $actionName = $match[3];
        foreach ($this->kernel->getBundles() as $name => $bundle) {
            if (0 !== strpos($className, $bundle->getNamespace())) {
                continue;
            }

            return sprintf('%s:%s:%s', $name, $controllerName, $actionName);
        }

        throw new \InvalidArgumentException(sprintf('Unable to find a bundle that defines controller "%s".', $controller));
    }
}
