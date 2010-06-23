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
use Symfony\Components\HttpKernel\Request;
use Symfony\Components\HttpKernel\Response;
use Symfony\Components\EventDispatcher\EventDispatcher;
use Symfony\Components\EventDispatcher\Event;

class TestHttpKernel extends HttpKernel
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

        $this->dispatcher = new EventDispatcher();
        $this->dispatcher->connect('core.load_controller', array($this, 'loadController'));
    }

    public function loadController(Event $event)
    {
        $event->setReturnValue(array(array($this, 'callController'), array($event['request'])));

        return true;
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
