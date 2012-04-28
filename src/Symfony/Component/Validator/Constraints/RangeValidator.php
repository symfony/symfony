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

/**
 * @author Bernhard Schussek <bschussek@gmail.com>
 *
 * @api
 */
class RangeValidator extends ConstraintValidator
{
    /**
     * Checks if the passed value is valid.
     *
     * @param mixed      $value      The value that should be validated
     * @param Constraint $constraint The constraint for the validation
     *
     * @return Boolean Whether or not the value is valid
     *
     * @api
     */
    public function validate($value, Constraint $constraint)
    {
        if (null === $value) {
            return;
        }

        if (!is_numeric($value)) {
            $this->context->addViolation($constraint->invalidMessage, array(
                '{{ value }}' => $value,
            ));

            return;
        }

        if ($value > $constraint->max) {
            $this->context->addViolation($constraint->maxMessage, array(
                '{{ value }}' => $value,
                '{{ limit }}' => $constraint->max,
            ));

            return;
        }

        if ($value < $constraint->min) {
            $this->context->addViolation($constraint->minMessage, array(
                '{{ value }}' => $value,
                '{{ limit }}' => $constraint->min,
            ));
        }
    }
}
