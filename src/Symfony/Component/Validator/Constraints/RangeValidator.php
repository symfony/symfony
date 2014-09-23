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

        if (!is_numeric($value) && !$value instanceof \DateTime && !$value instanceof \DateTimeInterface) {
            $this->context->addViolation($constraint->invalidMessage, array(
                '{{ value }}' => $this->formatValue($value),
            ));

            return;
        }

        $min = $constraint->min;
        $max = $constraint->max;

        // Convert strings to DateTimes if comparing another DateTime
        // This allows to compare with any date/time value supported by
        // the DateTime constructor:
        // http://php.net/manual/en/datetime.formats.php
        if ($value instanceof \DateTime || $value instanceof \DateTimeInterface) {
            if (is_string($min)) {
                $min = new \DateTime($min);
            }

            if (is_string($max)) {
                $max = new \DateTime($max);
            }
        }

        if (null !== $constraint->max && $value > $max) {
            $this->context->addViolation($constraint->maxMessage, array(
                '{{ value }}' => $value,
                '{{ limit }}' => $this->formatValue($max, self::PRETTY_DATE),
            ));

            return;
        }

        if (null !== $constraint->min && $value < $min) {
            $this->context->addViolation($constraint->minMessage, array(
                '{{ value }}' => $value,
                '{{ limit }}' => $this->formatValue($min, self::PRETTY_DATE),
            ));
        }
    }
}
