<?php

namespace Symfony\Component\HttpKernel\Tests\Exception;

use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

class UnprocessableEntityHttpExceptionTest extends HttpExceptionTest
{
    protected function createException()
    {
        return new UnprocessableEntityHttpException();
    }
}
