<?php

namespace Symfony\Component\HttpKernel\Tests\Exception;

use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class AccessDeniedHttpExceptionTest extends HttpExceptionTest
{
    protected function createException()
    {
        return new AccessDeniedHttpException();
    }
}
