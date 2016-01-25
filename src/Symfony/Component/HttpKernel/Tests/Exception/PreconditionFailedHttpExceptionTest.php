<?php

namespace Symfony\Component\HttpKernel\Tests\Exception;

use Symfony\Component\HttpKernel\Exception\PreconditionFailedHttpException;

class PreconditionFailedHttpExceptionTest extends HttpExceptionTest
{
    protected function createException()
    {
        return new PreconditionFailedHttpException();
    }
}
