<?php

/*
 * This file is part of the symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Tests\Components\RequestHandler\Test;

use Symfony\Components\RequestHandler\RequestHandler;
use Symfony\Components\RequestHandler\Request;
use Symfony\Components\RequestHandler\Response;
use Symfony\Components\EventDispatcher\EventDispatcher;
use Symfony\Components\EventDispatcher\Event;

class TestRequestHandler extends RequestHandler
{
  public function __construct()
  {
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
    return new Response('Request: '.$request->getRequestUri());
  }
}
