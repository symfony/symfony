<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\HttpKernel\Tests\HttpCache;

use Symphony\Component\HttpKernel\Controller\ArgumentResolverInterface;
use Symphony\Component\HttpKernel\HttpKernel;
use Symphony\Component\HttpKernel\HttpKernelInterface;
use Symphony\Component\HttpFoundation\Request;
use Symphony\Component\HttpFoundation\Response;
use Symphony\Component\HttpKernel\Controller\ControllerResolverInterface;
use Symphony\Component\EventDispatcher\EventDispatcher;

class TestMultipleHttpKernel extends HttpKernel implements ControllerResolverInterface, ArgumentResolverInterface
{
    protected $bodies = array();
    protected $statuses = array();
    protected $headers = array();
    protected $called = false;
    protected $backendRequest;

    public function __construct($responses)
    {
        foreach ($responses as $response) {
            $this->bodies[] = $response['body'];
            $this->statuses[] = $response['status'];
            $this->headers[] = $response['headers'];
        }

        parent::__construct(new EventDispatcher(), $this, null, $this);
    }

    public function getBackendRequest()
    {
        return $this->backendRequest;
    }

    public function handle(Request $request, $type = HttpKernelInterface::MASTER_REQUEST, $catch = false)
    {
        $this->backendRequest = $request;

        return parent::handle($request, $type, $catch);
    }

    public function getController(Request $request)
    {
        return array($this, 'callController');
    }

    public function getArguments(Request $request, $controller)
    {
        return array($request);
    }

    public function callController(Request $request)
    {
        $this->called = true;

        $response = new Response(array_shift($this->bodies), array_shift($this->statuses), array_shift($this->headers));

        return $response;
    }

    public function hasBeenCalled()
    {
        return $this->called;
    }

    public function reset()
    {
        $this->called = false;
    }
}
