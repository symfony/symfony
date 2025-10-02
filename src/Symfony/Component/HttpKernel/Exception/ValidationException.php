<?php

namespace Symfony\Component\HttpKernel\Exception;

use Symfony\Component\Validator\ConstraintViolationListInterface;

class ValidationException extends HttpException
{
    public function __construct(
        private mixed $value,
        private ConstraintViolationListInterface $violations,
        int $statusCode = 422,
        string $message = 'Validation failed',
        ?\Throwable $previous = null,
        array $headers = [],
        int $code = 0,
    ) {
        parent::__construct($statusCode, $message, $previous, $headers, $code);
    }

    public function getValue(): mixed
    {
        return $this->value;
    }

    public function getViolations(): ConstraintViolationListInterface
    {
        return $this->violations;
    }
}
