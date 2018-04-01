<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\HttpKernel\Tests;

use Symphony\Component\HttpKernel\Controller\ArgumentResolverInterface;
use Symphony\Component\HttpKernel\HttpKernel;
use Symphony\Component\HttpFoundation\Request;
use Symphony\Component\HttpFoundation\Response;
use Symphony\Component\HttpKernel\Controller\ControllerResolverInterface;
use Symphony\Component\EventDispatcher\EventDispatcher;

class TestHttpKernel extends HttpKernel implements ControllerResolverInterface, ArgumentResolverInterface
{
    public function __construct()
    {
        parent::__construct(new EventDispatcher(), $this, null, $this);
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
        return new Response('Request: '.$request->getRequestUri());
    }
}
