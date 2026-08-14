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
 * Validator for Callback constraint.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class CallbackValidator extends ConstraintValidator
{
    /**
     * @psalm-suppress ParamNameMismatch
     */
    public function validate(mixed $object, Constraint $constraint): void
    {
        if (!$constraint instanceof Callback) {
            throw new UnexpectedTypeException($constraint, Callback::class);
        }

        $method = $constraint->callback;
        if ($method instanceof \Closure) {
            $return = $method($object, $this->context, $constraint->payload);
        } elseif (\is_array($method)) {
            if (!\is_callable($method)) {
                if (isset($method[0]) && \is_object($method[0])) {
                    $method[0] = $method[0]::class;
                }
                throw new ConstraintDefinitionException(json_encode($method).' targeted by Callback constraint is not a valid callable.');
            }

            $return = $method($object, $this->context, $constraint->payload);
        } elseif (null !== $object) {
            if (!method_exists($object, $method)) {
                throw new ConstraintDefinitionException(\sprintf('Method "%s" targeted by Callback constraint does not exist in class "%s".', $method, get_debug_type($object)));
            }

            $reflMethod = new \ReflectionMethod($object, $method);

            if ($reflMethod->isStatic()) {
                $return = $reflMethod->invoke(null, $object, $this->context, $constraint->payload);
            } else {
                $return = $reflMethod->invoke($object, $this->context, $constraint->payload);
            }
        } else {
            return;
        }

        if (null === $constraint->message) {
            return;
        }

        if (!\is_bool($return)) {
            $target = $this->context->getClassName();

            if (null !== $target && null !== $propertyName = $this->context->getPropertyName()) {
                $target .= '::$'.$propertyName;
            }

            $target ??= $this->context->getPropertyPath();

            throw new ConstraintDefinitionException(\sprintf('The callback targeted by the Callback constraint%s must return a boolean when the "message" option is set, "%s" returned.', $target ? \sprintf(' on "%s"', $target) : '', get_debug_type($return)));
        }

        if (!$return) {
            $this->context->buildViolation($constraint->message)
                ->setParameter('{{ value }}', $this->formatValue($object))
                ->setCode(Callback::NOT_SATISFIED_ERROR)
                ->addViolation();
        }
    }
}
