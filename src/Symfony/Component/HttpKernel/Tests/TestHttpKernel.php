<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\Tests;

use Symfony\Component\HttpKernel\HttpKernel;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Controller\ControllerResolverInterface;
use Symfony\Component\EventDispatcher\EventDispatcher;

class TestHttpKernel extends HttpKernel
{
    public function __construct()
    {
        parent::__construct(new EventDispatcher(), new TestControllerResolver($this), null, new TestArgumentResolverManager());
    }

    public function callController(Request $request)
    {
        return new Response('Request: '.$request->getRequestUri());
    }
}
