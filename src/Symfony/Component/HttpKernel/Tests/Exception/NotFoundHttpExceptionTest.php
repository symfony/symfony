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

use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class NotFoundHttpExceptionTest extends HttpExceptionTest
{
    protected function createException(string $message = '', ?\Throwable $previous = null, int $code = 0, array $headers = []): HttpException
    {
        return new NotFoundHttpException($message, $previous, $code, $headers);
    }
}
