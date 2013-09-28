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
 * @author Andrew Moore <me@andrewmoore.ca>
 */
class DateTimeRangeValidator extends ConstraintValidator
{
    const DATE_PATTERN = '/^(\d{4})-(\d{2})-(\d{2})$/';
    const DATETIME_PATTERN = '/^(\d{4})-(\d{2})-(\d{2}) (0[0-9]|1[0-9]|2[0-3]):([0-5][0-9]):([0-5][0-9])$/';
    const DATETIME_FORMAT = 'Y-m-d H:i:s';

    /**
     * {@inheritDoc}
     */
    public function validate($value, Constraint $constraint)
    {
        if (null === $value || '' === $value) {
            return;
        }

        $dateTimeValue = null;

        if ($value instanceof \DateTime) {
            $dateTimeValue = $value;
        } else {
            if (!is_scalar($value) && !(is_object($value) && method_exists($value, '__toString'))) {
                throw new UnexpectedTypeException($value, 'string');
            }

            $value = (string)$value;

            if (preg_match(static::DATE_PATTERN, $value, $matches) && checkdate($matches[2], $matches[3], $matches[1])) {
                $dateTimeValue = \DateTime::createFromFormat(static::DATETIME_FORMAT, $value . ' 00:00:00', $constraint->timezone);
            } elseif (preg_match(static::DATETIME_PATTERN, $value, $matches) && checkdate($matches[2], $matches[3], $matches[1])) {
                $dateTimeValue = \DateTime::createFromFormat(static::DATETIME_FORMAT, $value, $constraint->timezone);
            } else {
                $this->context->addViolation($constraint->invalidMessage, array('{{ value }}' => $value));
                return;
            }
        }

        if (null !== $constraint->max && $dateTimeValue > $constraint->max) {
            $this->context->addViolation(
                $constraint->maxMessage,
                array(
                    '{{ value }}' => $value,
                    '{{ limit }}' => $constraint->max,
                )
            );

            return;
        }

        if (null !== $constraint->min && $dateTimeValue < $constraint->min) {
            $this->context->addViolation(
                $constraint->minMessage,
                array(
                    '{{ value }}' => $value,
                    '{{ limit }}' => $constraint->min,
                )
            );
        }
    }
}
