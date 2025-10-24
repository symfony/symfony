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
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\Exception\ValidationFailedException;

class ValidationExceptionTest extends HttpExceptionTest
{
    protected function createException(string $message = '', ?\Throwable $previous = null, int $code = 0, array $headers = []): HttpException
    {
        return new ValidationException('invalid value', new ConstraintViolationList(), 422, $headers, $previous, $code);
    }

    public function testGetValue()
    {
        $value = ['test' => 'data'];
        $exception = new ValidationException($value, new ConstraintViolationList());

        $this->assertSame($value, $exception->getValue());
    }

    public function testGetViolations()
    {
        $value = 'test';
        $violations = new ConstraintViolationList();
        $exception = new ValidationException($value, $violations);

        $this->assertSame($violations, $exception->getViolations());
    }

    public function testDefaultPreviousIsValidationFailedException()
    {
        $value = 'test';
        $violations = new ConstraintViolationList();
        $exception = new ValidationException($value, $violations);

        $this->assertInstanceOf(ValidationFailedException::class, $exception->getPrevious());
        $this->assertSame($value, $exception->getPrevious()->getValue());
        $this->assertSame($violations, $exception->getPrevious()->getViolations());
    }
}

