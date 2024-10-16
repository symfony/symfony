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
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Exception\UnexpectedValueException;

/**
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class DateValidator extends ConstraintValidator
{
    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof Date) {
            throw new UnexpectedTypeException($constraint, Date::class);
        }

        if (null === $value || '' === $value) {
            return;
        }

        if (!\is_scalar($value) && !$value instanceof \Stringable) {
            throw new UnexpectedValueException($value, 'string');
        }

        $value = (string) $value;

        if (!\in_array($constraint->format, Date::ACCEPTED_DATE_FORMATS)) {
            $this->context->buildViolation($constraint->messageDateFormatNotAccepted)
                ->setParameter('{{ value }}', $this->formatValue($constraint->format))
                ->setParameter('{{ formats }}', $this->formatValues(Date::ACCEPTED_DATE_FORMATS))
                ->setCode(Date::NOT_SUPPORTED_DATE_FORMAT_ERROR)
                ->addViolation();

            return;
        }

        $asDateTimeImmutable = \DateTimeImmutable::createFromFormat($constraint->format, $value);

        if (!$asDateTimeImmutable) {
            $this->context->buildViolation($constraint->message)
                ->setParameter('{{ value }}', $this->formatValue($value))
                ->setCode(Date::INVALID_FORMAT_ERROR)
                ->addViolation();
        } elseif ($asDateTimeImmutable->format($constraint->format) !== $value) {
            $this->context->buildViolation($constraint->message)
                ->setParameter('{{ value }}', $this->formatValue($value))
                ->setCode(Date::INVALID_DATE_ERROR)
                ->addViolation();
        }
    }
}
