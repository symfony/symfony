<?php

namespace Symfony\Component\HttpKernel\Tests\Exception;

use Symfony\Component\HttpKernel\Exception\PreconditionFailedHttpException;

class PreconditionFailedHttpExceptionTest extends HttpExceptionTest
{
    protected function createException(string $message = null, \Throwable $previous = null, ?int $code = 0, array $headers = [])
    {
        return new PreconditionFailedHttpException($message, $previous, $code, $headers);
    }
}
