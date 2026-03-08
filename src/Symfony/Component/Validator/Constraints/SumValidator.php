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
 * @author Kasper Hansen <kasper.h90@gmail.com>
 */
class SumValidator extends ConstraintValidator
{
    private const FLOAT_TOLERANCE = 1e-12;

    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof Sum) {
            throw new UnexpectedTypeException($constraint, Sum::class);
        }

        if (null === $value) {
            return;
        }

        if (!is_iterable($value)) {
            throw new UnexpectedValueException($value, 'iterable');
        }

        $sum = 0.0;

        foreach ($value as $item) {
            if (!is_numeric($item)) {
                $this->context->buildViolation($constraint->notNumericMessage)
                    ->setParameter('{{ value }}', $this->formatValue($item))
                    ->setInvalidValue($value)
                    ->setCode(Sum::NOT_NUMERIC_ERROR)
                    ->addViolation();

                return;
            }

            $sum += (float) $item;
        }

        $min = $constraint->min;
        $max = $constraint->max;

        $exactlyOptionEnabled = null !== $min
            && null !== $max
            && abs($min - $max) <= self::FLOAT_TOLERANCE;

        if ($exactlyOptionEnabled) {
            if (abs($sum - $min) > self::FLOAT_TOLERANCE) {
                $this->context->buildViolation($constraint->exactMessage)
                    ->setParameter('{{ sum }}', $sum)
                    ->setParameter('{{ limit }}', $min)
                    ->setInvalidValue($value)
                    ->setCode(Sum::NOT_EQUAL_SUM_ERROR)
                    ->addViolation();
            }

            return;
        }

        if (null !== $max && $sum > $max) {
            $this->context->buildViolation($constraint->maxMessage)
                ->setParameter('{{ sum }}', $sum)
                ->setParameter('{{ limit }}', $max)
                ->setInvalidValue($value)
                ->setCode(Sum::TOO_HIGH_ERROR)
                ->addViolation();

            return;
        }

        if (null !== $min && $sum < $min) {
            $this->context->buildViolation($constraint->minMessage)
                ->setParameter('{{ sum }}', $sum)
                ->setParameter('{{ limit }}', $min)
                ->setInvalidValue($value)
                ->setCode(Sum::TOO_LOW_ERROR)
                ->addViolation();
        }
    }
}
