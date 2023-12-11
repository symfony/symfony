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

use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\ConstraintDefinitionException;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

/**
 * @author Émile PRÉVOT <emile@level21.io>
 */
class ThisableMessageValidator extends ConstraintValidator
{
    public function validate($value, Constraint $constraint)
    {
        if (!$constraint instanceof ThisableMessage) {
            throw new UnexpectedTypeException($constraint, ThisableMessage::class);
        }

        $context = $this->context;

        $validator = $context->getValidator();
        $propertyAccessor = PropertyAccess::createPropertyAccessor();

        $object = $context->getObject();
        $root = $context->getRoot();

        if (null === $root) {
            throw new ConstraintDefinitionException('The current root is not accessible');
        }
        if (null === $object) {
            throw new ConstraintDefinitionException('The current object is not accessible');
        }

        foreach ($constraint->constraints as $subConstraint) {
            $violationList = $validator->validate($value, $subConstraint);
            foreach ($violationList as $oldViolation) {
                $matches = [];
                preg_match_all('/{{ this\.(.*?) }}/s', $oldViolation->getMessage(), $matches);
                $newViolation = $this->context->buildViolation($oldViolation->getMessage(), $oldViolation->getParameters());
                $keys = $matches[1] ?? [];
                $keys = array_merge($keys, $constraint->addThisParameters);
                foreach ($keys as $key) {
                    $newViolation->setParameter('{{ this.'.$key.' }}', $propertyAccessor->getValue($object, $key));
                }

                $matches = [];
                preg_match_all('/{{ root\.(.*?) }}/s', $oldViolation->getMessage(), $matches);
                $keys = $matches[1] ?? [];
                $keys = array_merge($keys, $constraint->addRootParameters);
                foreach ($keys as $key) {
                    $newViolation->setParameter('{{ root.'.$key.' }}', $propertyAccessor->getValue($root, $key));
                }
                $newViolation->addViolation();
            }
        }
    }
}
