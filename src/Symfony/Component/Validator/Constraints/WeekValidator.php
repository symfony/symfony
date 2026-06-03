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
 * @author Alexandre Daubois <alex.daubois@gmail.com>
 */
final class WeekValidator extends ConstraintValidator
{
    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof Week) {
            throw new UnexpectedTypeException($constraint, Week::class);
        }

        if (null === $value) {
            return;
        }

        if (!\is_string($value) && !$value instanceof \Stringable) {
            throw new UnexpectedValueException($value, 'string');
        }

        if (!preg_match('/^\d{4}-W(0[1-9]|[1-4][0-9]|5[0-3])$/D', $value)) {
            $this->context->buildViolation($constraint->invalidFormatMessage)
                ->setCode(Week::INVALID_FORMAT_ERROR)
                ->addViolation();

            return;
        }

        [$year, $weekNumber] = explode('-W', $value, 2);
        $weeksInYear = (int) date('W', mktime(0, 0, 0, 12, 28, $year));

        if ($weekNumber > $weeksInYear) {
            $this->context->buildViolation($constraint->invalidWeekNumberMessage)
                ->setCode(Week::INVALID_WEEK_NUMBER_ERROR)
                ->setParameter('{{ value }}', $value)
                ->addViolation();

            return;
        }

        if ($constraint->min) {
            [$minYear, $minWeekNumber] = explode('-W', $constraint->min, 2);
            if ($year < $minYear || ($year === $minYear && $weekNumber < $minWeekNumber)) {
                $this->context->buildViolation($constraint->tooLowMessage)
                    ->setCode(Week::TOO_LOW_ERROR)
                    ->setInvalidValue($value)
                    ->setParameter('{{ min }}', $constraint->min)
                    ->addViolation();

                return;
            }
        }

        if ($constraint->max) {
            [$maxYear, $maxWeekNumber] = explode('-W', $constraint->max, 2);
            if ($year > $maxYear || ($year === $maxYear && $weekNumber > $maxWeekNumber)) {
                $this->context->buildViolation($constraint->tooHighMessage)
                    ->setCode(Week::TOO_HIGH_ERROR)
                    ->setInvalidValue($value)
                    ->setParameter('{{ max }}', $constraint->max)
                    ->addViolation();
            }
        }
    }
}
