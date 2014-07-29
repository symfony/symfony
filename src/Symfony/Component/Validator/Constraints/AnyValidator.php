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
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

/**
 * @author Cas Leentfaar <info@casleentfaar.com>
 *
 * @api
 */
class AnyValidator extends ConstraintValidator
{
    /**
     * {@inheritdoc}
     */
    public function validate($value, Constraint $constraint)
    {
        if (!$constraint instanceof Any) {
            throw new UnexpectedTypeException($constraint, __NAMESPACE__ . '\Any');
        }

        if (null === $value) {
            return;
        }

        $atLeastOneValid = false;
        $context         = $this->context;
        $group           = $context->getGroup();

        foreach ($constraint->constraints as $subConstraint) {
            $context->validateValue($value, $subConstraint, '', $group);
            $violations = $context->getViolations();
            if ($violations && $violations->count() === 0) {
                $atLeastOneValid = true;
            }
        }

        if (true === $atLeastOneValid) {
            if ($context->getViolations()) {
                foreach ($context->getViolations() as $offset => $violation) {
                    $context->getViolations()->remove($offset);
                }
            }

            return;
        }

        $context->addViolation($constraint->message, array(
            '{{ value }}' => $value
        ));
    }
}
