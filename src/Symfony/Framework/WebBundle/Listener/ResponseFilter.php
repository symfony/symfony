<?php

namespace Symfony\Framework\WebBundle\Listener;

use Symfony\Components\EventDispatcher\EventDispatcher;
use Symfony\Components\EventDispatcher\Event;
use Symfony\Components\RequestHandler\RequestInterface;
use Symfony\Components\RequestHandler\ResponseInterface;

/*
 * This file is part of the symfony framework.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

/**
 * 
 *
 * @package    symfony
 * @author     Fabien Potencier <fabien.potencier@symfony-project.com>
 */
class ResponseFilter
{
  protected $dispatcher;
  protected $request;

  public function __construct(EventDispatcher $dispatcher, RequestInterface $request)
  {
    $this->dispatcher = $dispatcher;
    $this->request = $request;
  }

  public function register()
  {
    $this->dispatcher->connect('core.response', array($this, 'filter'));
  }

  public function filter(Event $event, ResponseInterface $response)
  {
    if (!$event->getParameter('main_request') || $response->hasHeader('Content-Type'))
    {
      return $response;
    }

    $format = $this->request->getRequestFormat();
    if ((null !== $format) && $mimeType = $this->request->getMimeType($format))
    {
      $response->setHeader('Content-Type', $mimeType);
    }

    return $response;
  }
}
