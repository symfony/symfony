<?php

namespace Symfony\Components\RequestHandler\Exception;

class ForbiddenHttpException extends HttpException
{
  public function __construct($message = '')
  {
    if (!$message)
    {
      $message = 'Forbidden';
    }

    parent::__construct($message, 403);
  }
}
