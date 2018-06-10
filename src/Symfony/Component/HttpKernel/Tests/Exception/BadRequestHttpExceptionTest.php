<?php

namespace Symfony\Component\HttpKernel\Tests\Exception;

use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class BadRequestHttpExceptionTest extends HttpExceptionTest
{
    protected function createException()
    {
        return new BadRequestHttpException();
    }
}
