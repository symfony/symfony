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
 *
 * @since v2.0.0
 */
class TrueValidator extends ConstraintValidator
{
    /**
     * {@inheritDoc}
     *
     * @since v2.1.0
     */
    public function validate($value, Constraint $constraint)
    {
        if (null === $value) {
            return;
        }

        if (true !== $value && 1 !== $value && '1' !== $value) {
            $this->context->addViolation($constraint->message);
        }
    }
}
