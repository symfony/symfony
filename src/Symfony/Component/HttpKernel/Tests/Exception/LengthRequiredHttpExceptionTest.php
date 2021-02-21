<?php

namespace Symfony\Component\HttpKernel\Tests\Exception;

use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\LengthRequiredHttpException;

class LengthRequiredHttpExceptionTest extends HttpExceptionTest
{
    protected function createException(string $message = '', \Throwable $previous = null, int $code = 0, array $headers = []): HttpException
    {
        return new LengthRequiredHttpException($message, $previous, $code, $headers);
    }
}
