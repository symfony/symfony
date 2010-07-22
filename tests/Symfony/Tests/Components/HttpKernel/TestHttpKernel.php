<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Tests\Components\HttpKernel;

use Symfony\Components\HttpKernel\HttpKernel;
use Symfony\Components\HttpFoundation\Request;
use Symfony\Components\HttpFoundation\Response;
use Symfony\Components\EventDispatcher\EventDispatcher;
use Symfony\Components\HttpKernel\Controller\ControllerResolverInterface;

class TestHttpKernel extends HttpKernel implements ControllerResolverInterface
{
    public function __construct()
    {
        parent::__construct(new EventDispatcher(), $this);
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
