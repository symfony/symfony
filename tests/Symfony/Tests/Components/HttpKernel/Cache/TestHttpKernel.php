<?php

/*
 * This file is part of the symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Tests\Components\HttpKernel\Cache;

use Symfony\Components\HttpKernel\HttpKernel;
use Symfony\Components\HttpFoundation\Request;
use Symfony\Components\HttpFoundation\Response;
use Symfony\Components\EventDispatcher\EventDispatcher;
use Symfony\Components\HttpKernel\Controller\ControllerResolverInterface;

class TestHttpKernel extends HttpKernel implements ControllerResolverInterface
{
    protected $body;
    protected $status;
    protected $headers;
    protected $called;
    protected $customizer;

    public function __construct($body, $status, $headers, \Closure $customizer = null)
    {
        $this->body = $body;
        $this->status = $status;
        $this->headers = $headers;
        $this->customizer = $customizer;
        $this->called = false;

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
        $this->called = true;

        $response = new Response($this->body, $this->status, $this->headers);

        if (null !== $this->customizer) {
            call_user_func($this->customizer, $request, $response);
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
