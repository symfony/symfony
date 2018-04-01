<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\Validator\Tests\Fixtures;

use Symphony\Component\Validator\Constraint;
use Symphony\Component\Validator\ConstraintValidator;

class FailingConstraintValidator extends ConstraintValidator
{
    public function validate($value, Constraint $constraint)
    {
        $this->context->addViolation($constraint->message, array());
    }
}
