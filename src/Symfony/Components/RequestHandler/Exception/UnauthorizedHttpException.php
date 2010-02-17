<?php

namespace Symfony\Components\RequestHandler\Exception;

class UnauthorizedHttpException extends HttpException
{
  public function __construct($message = '')
  {
    if (!$message)
    {
      $message = 'Unauthorized';
    }

    parent::__construct($message, 401);
  }
}
