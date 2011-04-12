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
 * Validator for Callback constraint
 *
 * @author Bernhard Schussek <bernhard.schussek@symfony.com>
 */
class CallbackValidator extends ConstraintValidator
{
    public function isValid($object, Constraint $constraint)
    {
        if (null === $object) {
            return true;
        }

        // has to be an array so that we can differentiate between callables
        // and method names
        if (!is_array($constraint->methods)) {
            throw new UnexpectedTypeException($constraint->methods, 'array');
        }

        $methods = $constraint->methods;
        $context = $this->context;

        // save context state
        $currentClass = $context->getCurrentClass();
        $currentProperty = $context->getCurrentProperty();
        $group = $context->getGroup();
        $propertyPath = $context->getPropertyPath();

        foreach ($methods as $method) {
            if (is_array($method)) {
                if (!is_callable($method)) {
                    throw new ConstraintDefinitionException(sprintf('"%s::%s" targeted by Callback constraint is not a valid callable', $method[0], $method[1]));
                }

                call_user_func($method, $object, $context);
            } else {
                if (!method_exists($object, $method)) {
                    throw new ConstraintDefinitionException(sprintf('Method "%s" targeted by Callback constraint does not exist', $method));
                }

                $object->$method($context);
            }

            // restore context state
            $context->setCurrentClass($currentClass);
            $context->setCurrentProperty($currentProperty);
            $context->setGroup($group);
            $context->setPropertyPath($propertyPath);
        }

        return true;
    }
}
