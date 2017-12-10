<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\Controller\Layout;

use Symfony\Component\HttpKernel\Controller\ActionReference;
use Symfony\Component\HttpKernel\Controller\ControllerLayoutInterface;
use Symfony\Component\HttpKernel\Exception\ControllerLayoutException;
use Symfony\Component\HttpKernel\KernelInterface;

/**
 * Class for generic *Bundle/Controller/*Controller::*Action layout.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Pavel Batanov <pavel@batanov.me>
 */
final class GenericControllerLayout implements ControllerLayoutInterface
{
    /**
     * @var KernelInterface
     */
    private $kernel;

    /**
     * GenericControllerLayout constructor.
     *
     * @param KernelInterface $kernel
     */
    public function __construct(KernelInterface $kernel)
    {
        $this->kernel = $kernel;
    }

    public function parse(string $controller): ActionReference
    {
        if (0 === preg_match('#^(.*?\\\\Controller\\\\(.+)Controller)::(.+)Action$#', $controller, $match)) {
            throw new \InvalidArgumentException(
                sprintf('The "%s" controller is not a valid "class::method" string.', $controller)
            );
        }

        $className = $match[1];
        $controllerName = $match[2];
        $actionName = $match[3];
        foreach ($this->kernel->getBundles() as $name => $bundle) {
            if (0 !== strpos($className, $bundle->getNamespace())) {
                continue;
            }

            return new ActionReference($this->kernel->getBundle($name), $controllerName, $actionName);
        }

        throw ControllerLayoutException::unknownBundleForController($controller);
    }

    /** {@inheritdoc} */
    public function build(ActionReference $reference): string
    {
        $try = $reference->bundle->getNamespace().'\\Controller\\'.$reference->controller.'Controller';

        if (!class_exists($try)) {
            throw ControllerLayoutException::unknownControllerClass($reference, $try);
        }

        return $try.'::'.$reference->action.'Action';
    }
}
