<?php

namespace Symfony\Component\HttpKernel\Tests\Exception;

use Symfony\Component\HttpKernel\Exception\GatewayTimeoutException;

class GatewayTimeoutExceptionTest extends HttpExceptionTest
{
    protected function createException()
    {
        return new GatewayTimeoutException();
    }
}
