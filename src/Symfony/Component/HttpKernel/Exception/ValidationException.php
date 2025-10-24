<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\Exception;

use Symfony\Component\Validator\ConstraintViolationListInterface;
use Symfony\Component\Validator\Exception\ValidationFailedException;

final class ValidationException extends HttpException
{
    public function __construct(
        private mixed $value,
        private ConstraintViolationListInterface $violations,
        int $statusCode = 422,
        array $headers = [],
        ?\Throwable $previous = null,
        int $code = 0,
    ) {
        $message = implode("\n", array_map(static fn ($e) => $e->getMessage(), iterator_to_array($violations)));
        parent::__construct($statusCode, $message, $previous ?? new ValidationFailedException($value, $violations, $previous), $headers, $code);

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
