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
 * @author Bernhard Schussek <bschussek@gmail.com>
 *
 * @api
 */
class CallbackValidator extends ConstraintValidator
{
    /**
     * {@inheritDoc}
     */
    public function validate($object, Constraint $constraint)
    {
        if (null === $currentObject = $this->context->getCurrentObject()) {
            return;
        }

        // has to be an array so that we can differentiate between callables
        // and method names
        if (!is_array($constraint->methods)) {
            throw new UnexpectedTypeException($constraint->methods, 'array');
        }

        $methods = $constraint->methods;
        $propertyLevel = null !== $this->context->getCurrentProperty();

        foreach ($methods as $method) {
            if (is_array($method) || $method instanceof \Closure) {
                if (!is_callable($method)) {
                    throw new ConstraintDefinitionException(sprintf('"%s::%s" targeted by Callback constraint is not a valid callable', $method[0], $method[1]));
                }

                call_user_func($method, $object, $this->context);
            } else {
                if (!method_exists($currentObject, $method)) {
                    throw new ConstraintDefinitionException(sprintf('Method "%s" targeted by Callback constraint does not exist', $method));
                }

                // If validator was defined on a property level passing value to the method
                if ($propertyLevel) {
                    $currentObject->$method($object, $this->context);
                } else {
                    $currentObject->$method($this->context);
                }
            }
        }
    }
}
