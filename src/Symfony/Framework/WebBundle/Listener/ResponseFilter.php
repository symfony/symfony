<?php

namespace Symfony\Framework\WebBundle\Listener;

use Symfony\Components\EventDispatcher\EventDispatcher;
use Symfony\Components\EventDispatcher\Event;
use Symfony\Components\RequestHandler\Request;
use Symfony\Components\RequestHandler\Response;

/*
 * This file is part of the Symfony framework.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

/**
 * ResponseFilter.
 *
 * @package    Symfony
 * @subpackage Framework_WebBundle
 * @author     Fabien Potencier <fabien.potencier@symfony-project.com>
 */
class ResponseFilter
{
  protected $dispatcher;

  public function __construct(EventDispatcher $dispatcher)
  {
    $this->dispatcher = $dispatcher;
  }

  public function register()
  {
    $this->dispatcher->connect('core.response', array($this, 'filter'));
  }

  public function filter(Event $event, Response $response)
  {
    if (!$event->getParameter('main_request') || $response->hasHeader('Content-Type'))
    {
      return $response;
    }

    $request = $event->getParameter('request');
    $format = $request->getRequestFormat();
    if ((null !== $format) && $mimeType = $request->getMimeType($format))
    {
      $response->setHeader('Content-Type', $mimeType);
    }

    return $response;
  }
}
