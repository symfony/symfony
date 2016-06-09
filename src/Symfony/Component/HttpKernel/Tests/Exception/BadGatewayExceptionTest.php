<?php

namespace Symfony\Component\HttpKernel\Tests\Exception;

use Symfony\Component\HttpKernel\Exception\BadGatewayException;

class BadGatewayExceptionTest extends HttpExceptionTest
{
    protected function createException()
    {
        return new BadGatewayException();
    }
}
