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
class TypeValidator extends ConstraintValidator
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

        $type = $constraint->type == 'boolean' ? 'bool' : $constraint->type;
        $function = 'is_'.$type;

        if (function_exists($function) && call_user_func($function, $value)) {
            return true;
        } else if ($value instanceof $constraint->type) {
            return true;
        }

        $this->setMessage($constraint->message, array(
            '{{ value }}' => is_object($value) ? get_class($value) : is_array($value) ? 'Array' : (string) $value,
            '{{ type }}'  => $constraint->type,
        ));

        return false;
    }
}
