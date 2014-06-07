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
 * @author Elnur Abdurrakhimov <elnur@elnur.pro>
 */
class IntegerValidator extends ConstraintValidator
{
    /**
     * {@inheritdoc}
     */
    public function validate($value, Constraint $constraint)
    {
        if (null === $value) {
            return;
        }

        if (false === $value || true === $value || array() === $value || $value != filter_var($value, FILTER_VALIDATE_INT)) {
            $this->context->addViolation($constraint->message);
        }
    }
}
