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
use Symfony\Component\Validator\Exception\ConstraintDefinitionException;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

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
     * {@inheritdoc}
     */
    public function validate($object, Constraint $constraint)
    {
        if (!$constraint instanceof Callback) {
            throw new UnexpectedTypeException($constraint, __NAMESPACE__.'\Callback');
        }

        if (null !== $constraint->callback && null !== $constraint->methods) {
            throw new ConstraintDefinitionException(
                'The Callback constraint supports either the option "callback" '.
                'or "methods", but not both at the same time.'
            );
        }

        // has to be an array so that we can differentiate between callables
        // and method names
        if (null !== $constraint->methods && !is_array($constraint->methods)) {
            throw new UnexpectedTypeException($constraint->methods, 'array');
        }

        $methods = $constraint->methods ?: array($constraint->callback);

        foreach ($methods as $method) {
            if (is_array($method) || $method instanceof \Closure) {
                if (!is_callable($method)) {
                    throw new ConstraintDefinitionException(sprintf('"%s::%s" targeted by Callback constraint is not a valid callable', $method[0], $method[1]));
                }

                call_user_func($method, $object, $this->context);

                continue;
            }

            if (null === $object) {
                continue;
            }

            if (!method_exists($object, $method)) {
                throw new ConstraintDefinitionException(sprintf('Method "%s" targeted by Callback constraint does not exist', $method));
            }

            $reflMethod = new \ReflectionMethod($object, $method);

            if ($reflMethod->isStatic()) {
                $reflMethod->invoke(null, $object, $this->context);
            } else {
                $reflMethod->invoke($object, $this->context);
            }
        }
    }
}
