<?php

namespace Symfony\Component\HttpKernel\Tests\Exception;

use Symfony\Component\HttpKernel\Exception\LengthRequiredHttpException;

class LengthRequiredHttpExceptionTest extends HttpExceptionTest
{
    protected function createException()
    {
        return new LengthRequiredHttpException();
    }
}
