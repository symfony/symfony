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

use Symfony\Component\HttpKernel\Exception\ConcurrentRequestHttpException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;

class ConcurrentRequestHttpExceptionTest extends HttpExceptionTest
{
    public function testExposesTheLock()
    {
        $exception = new ConcurrentRequestHttpException('/import', 'default');

        $this->assertInstanceOf(ConflictHttpException::class, $exception);
        $this->assertSame(409, $exception->getStatusCode());
        $this->assertSame('A concurrent request is already being processed.', $exception->getMessage());
        $this->assertSame('/import', $exception->key);
        $this->assertSame('default', $exception->factory);
    }

    protected function createException(string $message = '', ?\Throwable $previous = null, int $code = 0, array $headers = []): HttpException
    {
        return new ConcurrentRequestHttpException('key', 'default', $previous, $code, $headers);
    }
}
