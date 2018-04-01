<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\Validator\Constraints;

use Symphony\Component\Validator\Constraint;
use Symphony\Component\Validator\ConstraintValidator;
use Symphony\Component\Validator\Exception\ConstraintDefinitionException;
use Symphony\Component\Validator\Exception\UnexpectedTypeException;

/**
 * Validator for Callback constraint.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class CallbackValidator extends ConstraintValidator
{
    /**
     * {@inheritdoc}
     */
    public function validate($object, Constraint $constraint)
    {
        if (!$constraint instanceof Callback) {
            throw new UnexpectedTypeException($constraint, __NAMESPACE__.'\Callback');
        }

        $method = $constraint->callback;
        if ($method instanceof \Closure) {
            $method($object, $this->context, $constraint->payload);
        } elseif (is_array($method)) {
            if (!is_callable($method)) {
                if (isset($method[0]) && is_object($method[0])) {
                    $method[0] = get_class($method[0]);
                }
                throw new ConstraintDefinitionException(sprintf('%s targeted by Callback constraint is not a valid callable', json_encode($method)));
            }

            call_user_func($method, $object, $this->context, $constraint->payload);
        } elseif (null !== $object) {
            if (!method_exists($object, $method)) {
                throw new ConstraintDefinitionException(sprintf('Method "%s" targeted by Callback constraint does not exist in class %s', $method, get_class($object)));
            }

            $reflMethod = new \ReflectionMethod($object, $method);

            if ($reflMethod->isStatic()) {
                $reflMethod->invoke(null, $object, $this->context, $constraint->payload);
            } else {
                $reflMethod->invoke($object, $this->context, $constraint->payload);
            }
        }
    }
}
