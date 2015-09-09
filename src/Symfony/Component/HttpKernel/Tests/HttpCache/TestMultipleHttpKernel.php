<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\Tests\HttpCache;

use Symfony\Component\HttpKernel\HttpKernel;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpKernel\Tests\TestArgumentResolverManager;
use Symfony\Component\HttpKernel\Tests\TestControllerResolver;

class TestMultipleHttpKernel extends HttpKernel
{
    protected $bodies = array();
    protected $statuses = array();
    protected $headers = array();
    protected $call = false;
    protected $backendRequest;

    public function __construct($responses)
    {
        foreach ($responses as $response) {
            $this->bodies[] = $response['body'];
            $this->statuses[] = $response['status'];
            $this->headers[] = $response['headers'];
        }

        parent::__construct(new EventDispatcher(), new TestControllerResolver($this), null, new TestArgumentResolverManager());
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
        $this->call = false;
    }
}
