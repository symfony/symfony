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

use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Controller\ArgumentResolverInterface;
use Symfony\Component\HttpKernel\Controller\ControllerResolverInterface;
use Symfony\Component\HttpKernel\HttpKernel;
use Symfony\Component\HttpKernel\HttpKernelInterface;

class TestHttpKernel extends HttpKernel implements ControllerResolverInterface, ArgumentResolverInterface
{
    protected $body;
    protected $status;
    protected $headers;
    protected $called = false;
    protected $customizer;
    protected $catch = false;
    protected $backendRequest;

    public function __construct($body, $status, $headers, \Closure $customizer = null)
    {
        $this->body = $body;
        $this->status = $status;
        $this->headers = $headers;
        $this->customizer = $customizer;

        parent::__construct(new EventDispatcher(), $this, null, $this);
    }

    public function assert(\Closure $callback)
    {
        $trustedConfig = array(Request::getTrustedProxies(), Request::getTrustedHeaderSet());

        list($trustedProxies, $trustedHeaderSet, $backendRequest) = $this->backendRequest;
        Request::setTrustedProxies($trustedProxies, $trustedHeaderSet);

        try {
            $callback($backendRequest);
        } finally {
            list($trustedProxies, $trustedHeaderSet) = $trustedConfig;
            Request::setTrustedProxies($trustedProxies, $trustedHeaderSet);
        }
    }

    public function handle(Request $request, $type = HttpKernelInterface::MASTER_REQUEST, $catch = false)
    {
        $this->catch = $catch;
        $this->backendRequest = array(Request::getTrustedProxies(), Request::getTrustedHeaderSet(), $request);

        return parent::handle($request, $type, $catch);
    }

    public function isCatchingExceptions()
    {
        return $this->catch;
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

        $response = new Response($this->body, $this->status, $this->headers);

        if (null !== $customizer = $this->customizer) {
            $customizer($request, $response);
        }

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
