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

use Symfony\Component\HttpKernel\Bundle\BundleInterface;
use Symfony\Component\HttpKernel\KernelInterface;

/**
 * ControllerNameParser converts controller from the short notation a:b:c
 * (BlogBundle:Post:index) to a fully-qualified class::method string
 * (Bundle\BlogBundle\Controller\PostController::indexAction).
 *
 * @author Fabien Potencier <fabien@symfony.com>
 *
 * @deprecated since Symfony 4.1
 */
class ControllerNameParser
{
    protected $kernel;

    public function __construct(KernelInterface $kernel, bool $triggerDeprecation = true)
    {
        $this->kernel = $kernel;

        if ($triggerDeprecation) {
            @trigger_error(sprintf('The "%s" class is deprecated since Symfony 4.1.', __CLASS__), E_USER_DEPRECATED);
        }
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
        if (2 > \func_num_args() || func_get_arg(1)) {
            @trigger_error(sprintf('The "%s" class is deprecated since Symfony 4.1.', __CLASS__), E_USER_DEPRECATED);
        }

        $parts = explode(':', $controller);
        if (3 !== \count($parts) || \in_array('', $parts, true)) {
            throw new \InvalidArgumentException(sprintf('The "%s" controller is not a valid "a:b:c" controller string.', $controller));
        }

        $originalController = $controller;
        list($bundleName, $controller, $action) = $parts;
        $controller = str_replace('/', '\\', $controller);

        try {
            // this throws an exception if there is no such bundle
            $bundle = $this->kernel->getBundle($bundleName);
        } catch (\InvalidArgumentException $e) {
            $message = sprintf(
                'The "%s" (from the _controller value "%s") does not exist or is not enabled in your kernel!',
                $bundleName,
                $originalController
            );

            if ($alternative = $this->findAlternative($bundleName)) {
                $message .= sprintf(' Did you mean "%s:%s:%s"?', $alternative, $controller, $action);
            }

            throw new \InvalidArgumentException($message, 0, $e);
        }

        $try = $bundle->getNamespace().'\\Controller\\'.$controller.'Controller';
        if (class_exists($try)) {
            return $try.'::'.$action.'Action';
        }

        throw new \InvalidArgumentException(sprintf('The _controller value "%s:%s:%s" maps to a "%s" class, but this class was not found. Create this class or check the spelling of the class and its namespace.', $bundleName, $controller, $action, $try));
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
        @trigger_error(sprintf('The %s class is deprecated since Symfony 4.1.', __CLASS__), E_USER_DEPRECATED);

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

    /**
     * Attempts to find a bundle that is *similar* to the given bundle name.
     */
    private function findAlternative(string $nonExistentBundleName): ?string
    {
        $bundleNames = array_map(function (BundleInterface $b) {
            return $b->getName();
        }, $this->kernel->getBundles());

        $alternative = null;
        $shortest = null;
        foreach ($bundleNames as $bundleName) {
            // if there's a partial match, return it immediately
            if (false !== strpos($bundleName, $nonExistentBundleName)) {
                return $bundleName;
            }

            $lev = levenshtein($nonExistentBundleName, $bundleName);
            if ($lev <= \strlen($nonExistentBundleName) / 3 && (null === $alternative || $lev < $shortest)) {
                $alternative = $bundleName;
                $shortest = $lev;
            }
        }

        return $alternative;
    }
}
