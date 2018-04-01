<?php

namespace Symphony\Component\HttpKernel\Tests\Exception;

use Symphony\Component\HttpKernel\Exception\GoneHttpException;

class GoneHttpExceptionTest extends HttpExceptionTest
{
    protected function createException()
    {
        return new GoneHttpException();
    }
}
