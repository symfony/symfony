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
class MinValidator extends ConstraintValidator
{
    /**
     * Checks if the passed value is valid.
     *
     * @param mixed      $value      The value that should be validated
     * @param Constraint $constraint The constraint for the validation
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
                '{{ limit }}' => $constraint->limit,
            ));

            return;
        }

        if ($value < $constraint->limit) {
            $this->context->addViolation($constraint->message, array(
                '{{ value }}' => $value,
                '{{ limit }}' => $constraint->limit,
            ));
        }
    }
}
