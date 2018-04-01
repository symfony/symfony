<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\Validator\Constraints;

use Symphony\Component\Validator\Constraint;
use Symphony\Component\Validator\ConstraintValidator;
use Symphony\Component\Validator\Exception\UnexpectedTypeException;

/**
 * @author Christian Flothmann <christian.flothmann@sensiolabs.de>
 */
class ValidValidator extends ConstraintValidator
{
    public function validate($value, Constraint $constraint)
    {
        if (!$constraint instanceof Valid) {
            throw new UnexpectedTypeException($constraint, __NAMESPACE__.'\Valid');
        }

        if (null === $value) {
            return;
        }

        $this->context
            ->getValidator()
            ->inContext($this->context)
            ->validate($value, null, array($this->context->getGroup()));
    }
}
