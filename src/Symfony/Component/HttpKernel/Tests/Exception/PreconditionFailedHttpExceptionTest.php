<?php

namespace Symfony\Component\HttpKernel\Tests\Exception;

use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\PreconditionFailedHttpException;

class PreconditionFailedHttpExceptionTest extends HttpExceptionTest
{
    protected function createException(string $message = '', \Throwable $previous = null, int $code = 0, array $headers = []): HttpException
    {
        return new PreconditionFailedHttpException($message, $previous, $code, $headers);
    }
}
