<?php

namespace Symfony\Component\HttpKernel\Tests\Exception;

use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\UnsupportedMediaTypeHttpException;

class UnsupportedMediaTypeHttpExceptionTest extends HttpExceptionTest
{
    protected function createException(string $message = '', \Throwable $previous = null, int $code = 0, array $headers = []): HttpException
    {
        return new UnsupportedMediaTypeHttpException($message, $previous, $code, $headers);
    }
}
