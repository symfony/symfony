<?php

namespace Symfony\Component\Validator\Constraints;

/*
 * This file is part of the Symfony framework.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

class AllValidator extends ConstraintValidator
{
    public function isValid($value, Constraint $constraint)
    {
        if ($value === null) {
            return true;
        }

        if (!is_array($value) && !$value instanceof \Traversable) {
            throw new UnexpectedTypeException($value, 'array or Traversable');
        }

        $walker = $this->context->getGraphWalker();
        $group = $this->context->getGroup();
        $propertyPath = $this->context->getPropertyPath();

        // cannot simply cast to array, because then the object is converted to an
        // array instead of wrapped inside
        $constraints = is_array($constraint->constraints) ? $constraint->constraints : array($constraint->constraints);

        foreach ($value as $key => $element) {
            foreach ($constraints as $constr) {
                $walker->walkConstraint($constr, $element, $group, $propertyPath.'['.$key.']');
            }
        }

        return true;
    }
}