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

use Symfony\Component\HttpKernel\Controller\ActionReference;
use Symfony\Component\HttpKernel\Controller\ControllerLayoutInterface;
use Symfony\Component\HttpKernel\Controller\Layout\GenericControllerLayout;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\HttpKernel\Util\AlternativeBundleNameProvider;

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

    private $layout;

    public function __construct(KernelInterface $kernel, ControllerLayoutInterface $layout = null)
    {
        $this->kernel = $kernel;
        $this->layout = $layout ?: new GenericControllerLayout($this->kernel);
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
        $parts = explode(':', $controller);
        if (3 !== count($parts) || in_array('', $parts, true)) {
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

            $provider = new AlternativeBundleNameProvider($this->kernel);

            if ($alternative = $provider->findAlternative($bundleName)) {
                $message .= sprintf(' Did you mean "%s:%s:%s"?', $alternative, $controller, $action);
            }

            throw new \InvalidArgumentException($message, 0, $e);
        }

        return $this->layout->build(new ActionReference($bundle, $controller, $action));
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
        $reference = $this->layout->parse($controller);

        return sprintf('%s:%s:%s', $reference->getBundle()->getName(), $reference->getController(), $reference->getAction());
    }
}
