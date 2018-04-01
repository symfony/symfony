<?php

namespace Symphony\Component\HttpKernel\Tests\Exception;

use Symphony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class AccessDeniedHttpExceptionTest extends HttpExceptionTest
{
    protected function createException()
    {
        return new AccessDeniedHttpException();
    }
}
