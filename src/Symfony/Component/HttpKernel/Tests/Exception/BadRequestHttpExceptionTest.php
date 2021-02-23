<?php

namespace Symfony\Component\HttpKernel\Tests\Exception;

use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;

class BadRequestHttpExceptionTest extends HttpExceptionTest
{
    protected function createException(string $message = '', \Throwable $previous = null, int $code = 0, array $headers = []): HttpException
    {
        return new BadRequestHttpException($message, $previous, $code, $headers);
    }
}
