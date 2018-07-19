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
use Symfony\Component\Validator\Exception\InvalidArgumentException;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

/**
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class RangeValidator extends ConstraintValidator
{
    /**
     * {@inheritdoc}
     */
    public function validate($value, Constraint $constraint)
    {
        if (!$constraint instanceof Range) {
            throw new UnexpectedTypeException($constraint, __NAMESPACE__.'\Range');
        }

        if (null === $value) {
            return;
        }

        if (!is_numeric($value) && !$value instanceof \DateTimeInterface) {
            $this->context->buildViolation($constraint->invalidMessage)
                ->setParameter('{{ value }}', $this->formatValue($value, self::PRETTY_DATE))
                ->setCode(Range::INVALID_CHARACTERS_ERROR)
                ->addViolation();

            return;
        }

        $min = $constraint->min;
        $max = $constraint->max;

        // Convert strings to DateTimes if comparing another DateTime
        // This allows to compare with any date/time value supported by
        // the DateTime constructor:
        // http://php.net/manual/en/datetime.formats.php
        if ($value instanceof \DateTimeInterface) {
            if (is_string($min)) {
                $min = new \DateTime($min);
            }

            if (is_string($max)) {
                $max = new \DateTime($max);
            }
        }

        if (null !== $constraint->max && $value > $max) {
            $this->context->buildViolation($constraint->maxMessage)
                ->setParameter('{{ value }}', $this->formatValue($value, self::PRETTY_DATE))
                ->setParameter('{{ limit }}', $this->formatValue($max, self::PRETTY_DATE))
                ->setCode(Range::TOO_HIGH_ERROR)
                ->addViolation();

            return;
        }

        if (null !== $constraint->min && $value < $min) {
            $this->context->buildViolation($constraint->minMessage)
                ->setParameter('{{ value }}', $this->formatValue($value, self::PRETTY_DATE))
                ->setParameter('{{ limit }}', $this->formatValue($min, self::PRETTY_DATE))
                ->setCode(Range::TOO_LOW_ERROR)
                ->addViolation();

            return;
        }

        if (null === ($step = $constraint->step) && ($step < $min || $step > $max)) {
            throw new InvalidArgumentException('Step should not be inferior to max and should be superior to min.');
        }

        // This feature does not work with DateTime so we check that the value is not a DateTime.
        if (!$value instanceof \DateTimeInterface && !\in_array($value, range($min, $max, $step), false)) {
            $this->context->buildViolation($constraint->stepMessage)
                ->setParameter('{{ value }}', $this->formatValue($value, self::PRETTY_DATE))
                ->setParameter('{{ min }}', $this->formatValue($min, self::PRETTY_DATE))
                ->setParameter('{{ max }}', $this->formatValue($max, self::PRETTY_DATE))
                ->setParameter('{{ step }}', $this->formatValue($step, self::PRETTY_DATE))
                ->setCode(Range::INVALID_STEP_ERROR)
                ->addViolation();
        }
    }
}
