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
     * @return Boolean Whether or not the value is valid
     *
     * @api
     */
    public function isValid($value, Constraint $constraint)
    {
        if (null === $value) {
            return true;
        }

        if (!is_numeric($value)) {
            $this->setMessage($constraint->invalidMessage, array(
                '{{ value }}' => $value,
                '{{ limit }}' => $constraint->limit,
            ));

            return false;
        }

        if ($value < $constraint->limit) {
            $this->setMessage($constraint->message, array(
                '{{ value }}' => $value,
                '{{ limit }}' => $constraint->limit,
            ));

            return false;
        }

        return true;
    }
}
