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
use Symfony\Component\HttpKernel\Exception\ValidationException;
use Symfony\Component\Validator\ConstraintViolationListInterface;

class ValidationExceptionTest extends HttpExceptionTest
{
    protected function createException(string $message = '', ?\Throwable $previous = null, int $code = 0, array $headers = []): HttpException
    {
        return new ValidationException('invalid value', $this->createMock(ConstraintViolationListInterface::class), 422, $message, $previous, $headers, $code);
    }
}
