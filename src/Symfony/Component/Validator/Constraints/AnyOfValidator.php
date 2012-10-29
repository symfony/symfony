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
 * AnyOfValidator class.
 *
 * Validates against all constraints and leaves all violations only if not
 * validated against any of given constraint. If successfully validated against
 * at least one constraint value is considered to be valid and violations
 * are rolled back.
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
        $walker = $this->context->getGraphWalker();

        $violationList = $this->context->getViolations();
        $violationListBefore = iterator_to_array($violationList);
        $violationCountPrevious = $violationList->count();
        $validationFailed = true;

        foreach ($constraint->constraints as $constr) {
            $walker->walkConstraint($constr, $value, '', '');
            $violationCount = $violationList->count();

            if ($violationCount === $violationCountPrevious) {
                // At least one constraint did not fail
                $validationFailed = false;
            }

            $violationCountPrevious = $violationCount;
        }

        if (!$validationFailed) {
            foreach ($violationList as $id => $violation) {
                if (!isset($violationListBefore[$id])) {
                    $violationList->remove($id);
                }
            }
        }
    }
}
