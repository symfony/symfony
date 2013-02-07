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
class CountValidator extends ConstraintValidator
{
    /**
     * {@inheritDoc}
     */
    public function validate($value, Constraint $constraint)
    {
        if (null === $value) {
            return;
        }

        if (!is_array($value) && !$value instanceof \Countable) {
            throw new UnexpectedTypeException($value, 'array or \Countable');
        }

        $count = count($value);

        if ($constraint->min == $constraint->max && $count != $constraint->min) {
            $this->context->addViolation($constraint->exactMessage, array(
                '{{ count }}' => $count,
                '{{ limit }}' => $constraint->min,
            ), $value, (int) $constraint->min);

            return;
        }

        if (null !== $constraint->max && $count > $constraint->max) {
            $this->context->addViolation($constraint->maxMessage, array(
                '{{ count }}' => $count,
                '{{ limit }}' => $constraint->max,
            ), $value, (int) $constraint->max);

            return;
        }

        if (null !== $constraint->min && $count < $constraint->min) {
            $this->context->addViolation($constraint->minMessage, array(
                '{{ count }}' => $count,
                '{{ limit }}' => $constraint->min,
            ), $value, (int) $constraint->min);
        }
    }
}
