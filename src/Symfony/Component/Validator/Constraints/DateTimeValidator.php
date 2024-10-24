<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Validator\Constraints;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Exception\UnexpectedValueException;

/**
 * @author Bernhard Schussek <bschussek@gmail.com>
 * @author Diego Saint Esteben <diego@saintesteben.me>
 */
class DateTimeValidator extends DateValidator
{
    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof DateTime) {
            throw new UnexpectedTypeException($constraint, DateTime::class);
        }

        if (null === $value || '' === $value) {
            return;
        }

        if (!\is_scalar($value) && !$value instanceof \Stringable) {
            throw new UnexpectedValueException($value, 'string');
        }

        $value = (string) $value;

        \DateTimeImmutable::createFromFormat($constraint->format, $value);

        $errors = \DateTimeImmutable::getLastErrors() ?: ['error_count' => 0, 'warnings' => []];

        if (0 < $errors['error_count']) {
            $this->context->buildViolation($constraint->message)
                ->setParameter('{{ value }}', $this->formatValue($value))
                ->setParameter('{{ format }}', $this->formatValue($constraint->format))
                ->setCode(DateTime::INVALID_FORMAT_ERROR)
                ->addViolation();

            return;
        }

        if (str_ends_with($constraint->format, '+')) {
            $errors['warnings'] = array_filter($errors['warnings'], fn ($warning) => 'Trailing data' !== $warning);
        }

        foreach ($errors['warnings'] as $warning) {
            if ('The parsed date was invalid' === $warning) {
                $this->context->buildViolation($constraint->message)
                    ->setParameter('{{ value }}', $this->formatValue($value))
                    ->setParameter('{{ format }}', $this->formatValue($constraint->format))
                    ->setCode(DateTime::INVALID_DATE_ERROR)
                    ->addViolation();
            } elseif ('The parsed time was invalid' === $warning) {
                $this->context->buildViolation($constraint->message)
                    ->setParameter('{{ value }}', $this->formatValue($value))
                    ->setParameter('{{ format }}', $this->formatValue($constraint->format))
                    ->setCode(DateTime::INVALID_TIME_ERROR)
                    ->addViolation();
            } else {
                $this->context->buildViolation($constraint->message)
                    ->setParameter('{{ value }}', $this->formatValue($value))
                    ->setParameter('{{ format }}', $this->formatValue($constraint->format))
                    ->setCode(DateTime::INVALID_FORMAT_ERROR)
                    ->addViolation();
            }
        }
    }
}
