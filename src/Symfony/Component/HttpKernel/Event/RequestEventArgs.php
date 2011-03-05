<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\Event;

use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Doctrine\Common\EventArgs;

class RequestEventArgs extends EventArgs
{
    private $kernel;

    private $request;

    private $requestType;

    private $response;

    public function __construct(KernelInterface $kernel, Request $request, $requestType, Response $response = null)
    {
        $this->kernel = $kernel;
        $this->request = $request;
        $this->requestType = $requestType;
        $this->response = $response;
    }

    public function getKernel()
    {
        return $this->kernel;
    }

    public function getRequest()
    {
        return $this->request;
    }

    public function getRequestType()
    {
        return $this->requestType;
    }

    public function getResponse()
    {
        return $this->response;
    }

    public function setResponse(Response $response)
    {
        $this->response = $response;

        $this->stopPropagation();
    }

    public function hasResponse()
    {
        return null !== $this->response;
    }
}