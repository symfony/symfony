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

class TypeValidator extends ConstraintValidator
{
    public function isValid($value, Constraint $constraint)
    {
        if (null === $value) {
            return true;
        }

        $type = $constraint->type == 'boolean' ? 'bool' : $constraint->type;
        $function = 'is_' . $type;

        if (function_exists($function) && call_user_func($function, $value)) {
            return true;
        } else if ($value instanceof $constraint->type) {
            return true;
        }

        $this->setMessage($constraint->message, array(
            '{{ value }}' => $value,
            '{{ type }}'  => $constraint->type,
        ));

        return false;
    }
}