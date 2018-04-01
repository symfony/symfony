<?php

namespace Symphony\Component\HttpKernel\Tests\Exception;

use Symphony\Component\HttpKernel\Exception\NotAcceptableHttpException;

class NotAcceptableHttpExceptionTest extends HttpExceptionTest
{
    protected function createException()
    {
        return new NotAcceptableHttpException();
    }
}
