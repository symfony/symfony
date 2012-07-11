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
 * AnyValidator class.
 *
 * Validates against all constraint and fires a violation
 * only if not validated against any of given constraint.
 * If successfully validated against at least one constraint
 * value is considered to be valid.
 *
 * @author Oleg Stepura <github@oleg.stepura.com>
 */
class AnyOfValidator extends ConstraintValidator
{
    /**
     * {@inheritDoc}
     */
    public function validate($value, Constraint $constraint)
    {
        $violationListBefore = clone $this->context->getViolations();

        // cannot simply cast to array, because then the object is converted to an
        // array instead of wrapped inside
        $constraints = is_array($constraint->constraints) ? $constraint->constraints : array($constraint->constraints);

        $walker = $this->context->getGraphWalker();

        $violationCountPrevious = $violationListBefore->count();
        $violationListAfter = $this->context->getViolations();
        $validationFailed = true;

        foreach ($constraints as $constr) {
            $walker->walkConstraint($constr, $value, '', '');
            $violationCount = $violationListAfter->count();

            if ($violationCount === $violationCountPrevious) {
                // At least one constraint did not fail
                $validationFailed = false;
            }

            $violationCountPrevious = $violationCount;
        }

        foreach ($violationListAfter as $id => $violation) {
            if (!$violationListBefore->has($id)) {
                $violationListAfter->remove($id);
            }
        }

        if ($validationFailed) {
            $constraintClassList = array();
            foreach ($constraints as $constr) {
                $constraintClassList[] = get_class($constr);
            }

            $this->context->addViolation(
                $constraint->message,
                array(
                    '{{ value }}' => $value,
                    '{{ constraints }}' => implode(', ', $constraintClassList)
                )
            );
        }
    }
}
