<?php

/*
 * This file is part of the symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Tests\Component\HttpKernel\Cache;

use Symfony\Component\HttpKernel\HttpKernel;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpKernel\Controller\ControllerResolverInterface;

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
