<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\Tests\Exception;

use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;

class ConflictHttpExceptionTest extends HttpExceptionTest
{
    protected function createException(string $message = '', ?\Throwable $previous = null, int $code = 0, array $headers = []): HttpException
    {
        return new ConflictHttpException($message, $previous, $code, $headers);
    }
}
