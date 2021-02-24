<?php

namespace Symfony\Component\HttpKernel\Tests\Exception;

use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;

class ConflictHttpExceptionTest extends HttpExceptionTest
{
    protected function createException(string $message = '', \Throwable $previous = null, int $code = 0, array $headers = []): HttpException
    {
        return new ConflictHttpException($message, $previous, $code, $headers);
    }
}
