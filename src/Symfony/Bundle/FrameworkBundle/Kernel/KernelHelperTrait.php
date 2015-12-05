<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Kernel;

use Symfony\Bundle\FrameworkBundle\Exception\LogicException;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\HttpKernelInterface;

/**
 * HttpKernel integration for controller classes.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Alexander M. Turek <me@derrabus.de>
 */
trait KernelHelperTrait
{
    /**
     * @var RequestStack
     */
    protected $requestStack;

    /**
     * @var HttpKernelInterface
     */
    protected $httpKernel;

    /**
     * @return RequestStack
     */
    protected function getRequestStack()
    {
        if ($this->requestStack === null) {
            if (!isset($this->container)) {
                throw new LogicException('Unable to retrieve the request stack. Please either set the $requestStack property or make'.__CLASS__.' container-aware.');
            }

            $this->requestStack = $this->container->get('request_stack');
        }

        return $this->requestStack;
    }

    /**
     * @return HttpKernelInterface
     */
    protected function getHttpKernel()
    {
        if ($this->httpKernel === null) {
            if (!isset($this->container)) {
                throw new LogicException('Unable to retrieve the HTTP kernel. Please either set the $httpKernel property or make'.__CLASS__.' container-aware.');
            }

            $this->httpKernel = $this->container->get('http_kernel');
        }

        return $this->httpKernel;
    }

    /**
     * Forwards the request to another controller.
     *
     * @param string $controller The controller name (a string like BlogBundle:Post:index)
     * @param array  $path       An array of path parameters
     * @param array  $query      An array of query parameters
     *
     * @return Response A Response instance
     */
    protected function forward($controller, array $path = array(), array $query = array())
    {
        $path['_controller'] = $controller;
        $subRequest = $this->getRequestStack()->getCurrentRequest()->duplicate($query, null, $path);

        return $this->getHttpKernel()->handle($subRequest, HttpKernelInterface::SUB_REQUEST);
    }
}
