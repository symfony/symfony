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

use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\EventDispatcher\Event;

class KernelEvent extends Event
{
    private $kernel;

    private $request;

    private $requestType;

    public function __construct(HttpKernelInterface $kernel, Request $request, $requestType)
    {
        $this->kernel = $kernel;
        $this->request = $request;
        $this->requestType = $requestType;
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
}