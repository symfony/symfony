<?php

namespace Symfony\Components\RequestHandler\Exception;

class NotFoundHttpException extends HttpException
{
  public function __construct($message = '')
  {
    if (!$message)
    {
      $message = 'Not Found';
    }

    parent::__construct($message, 404);
  }
}
