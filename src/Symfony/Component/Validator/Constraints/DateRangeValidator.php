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

use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Constraint;

/**
 * @author Alexander Volochnev <admin@toplimit.ru>
 */
class DateRangeValidator extends ConstraintValidator
{
    /**
     * {@inheritDoc}
     */
    public function validate($value, Constraint $constraint)
    {
        if (null === $value) {
            return;
        }

        if (!$value instanceof \DateTime) {
            $this->context->addViolation($constraint->invalidMessage);

            return;
        }

        if (null !== $constraint->max && $value > $constraint->max) {
            $this->context->addViolation($constraint->maxMessage, array(
                '{{ value }}' => $value->format($constraint->format),
                '{{ limit }}' => $constraint->max->format($constraint->format),
            ));

            return;
        }

        if (null !== $constraint->min && $value < $constraint->min) {
            $this->context->addViolation($constraint->minMessage, array(
                '{{ value }}' => $value->format($constraint->format),
                '{{ limit }}' => $constraint->min->format($constraint->format),
            ));
        }
    }
}
