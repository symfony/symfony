<?php

namespace Symfony\Component\HttpKernel\Tests\Exception;

use Symfony\Component\HttpKernel\Exception\LengthRequiredHttpException;

class LengthRequiredHttpExceptionTest extends HttpExceptionTest
{
    protected function createException(string $message = null, \Throwable $previous = null, ?int $code = 0, array $headers = [])
    {
        return new LengthRequiredHttpException($message, $previous, $code, $headers);
    }
}
