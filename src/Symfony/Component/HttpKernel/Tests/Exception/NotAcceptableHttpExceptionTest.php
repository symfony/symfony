<?php

namespace Symfony\Component\HttpKernel\Tests\Exception;

use Symfony\Component\HttpKernel\Exception\NotAcceptableHttpException;

class NotAcceptableHttpExceptionTest extends HttpExceptionTest
{
    protected function createException()
    {
        return new NotAcceptableHttpException();
    }
}
