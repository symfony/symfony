<?php

namespace Symphony\Component\HttpKernel\Tests\Exception;

use Symphony\Component\HttpKernel\Exception\LengthRequiredHttpException;

class LengthRequiredHttpExceptionTest extends HttpExceptionTest
{
    protected function createException()
    {
        return new LengthRequiredHttpException();
    }
}
