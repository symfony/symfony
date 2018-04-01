<?php

namespace Symphony\Component\HttpKernel\Tests\Exception;

use Symphony\Component\HttpKernel\Exception\ConflictHttpException;

class ConflictHttpExceptionTest extends HttpExceptionTest
{
    protected function createException()
    {
        return new ConflictHttpException();
    }
}
