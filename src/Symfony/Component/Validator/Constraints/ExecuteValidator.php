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
use Symfony\Component\Validator\Exception\ConstraintDefinitionException;

/**
 * Validator for Execute constraint
 *
 * @author Bernhard Schussek <bernhard.schussek@symfony.com>
 */
class ExecuteValidator extends ConstraintValidator
{
    public function isValid($object, Constraint $constraint)
    {
        if (null === $object) {
            return true;
        }

        $methods = (array)$constraint->methods;
        $context = $this->context;

        // save context state
        $currentClass = $context->getCurrentClass();
        $currentProperty = $context->getCurrentProperty();
        $group = $context->getGroup();
        $propertyPath = $context->getPropertyPath();

        foreach ($methods as $method) {
            if (!method_exists($object, $method)) {
                throw new ConstraintDefinitionException(sprintf('Method "%s" targeted by Execute constraint does not exist', $method));
            }

            $object->$method($context);

            // restore context state
            $context->setCurrentClass($currentClass);
            $context->setCurrentProperty($currentProperty);
            $context->setGroup($group);
            $context->setPropertyPath($propertyPath);
        }

        return true;
    }
}
