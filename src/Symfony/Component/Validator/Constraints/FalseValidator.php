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
class FalseValidator extends ConstraintValidator
{
    /**
     * {@inheritDoc}
     *
     * @since v2.1.0
     */
    public function validate($value, Constraint $constraint)
    {
        if (null === $value || false === $value || 0 === $value || '0' === $value) {
            return;
        }

        $this->context->addViolation($constraint->message);
    }
}
