<?php

namespace Symphony\Component\HttpKernel\Tests\Exception;

use Symphony\Component\HttpKernel\Exception\PreconditionFailedHttpException;

class PreconditionFailedHttpExceptionTest extends HttpExceptionTest
{
    protected function createException()
    {
        return new PreconditionFailedHttpException();
    }
}
