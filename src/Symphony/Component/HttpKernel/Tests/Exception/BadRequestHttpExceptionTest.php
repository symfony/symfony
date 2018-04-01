<?php

namespace Symphony\Component\HttpKernel\Tests\Exception;

use Symphony\Component\HttpKernel\Exception\BadRequestHttpException;

class BadRequestHttpExceptionTest extends HttpExceptionTest
{
    protected function createException()
    {
        return new BadRequestHttpException();
    }
}
