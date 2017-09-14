<?php

namespace Symfony\Component\HttpKernel\Tests\Exception;

use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class NotFoundHttpExceptionTest extends HttpExceptionTest
{
    protected function createException()
    {
        return new NotFoundHttpException();
    }
}
