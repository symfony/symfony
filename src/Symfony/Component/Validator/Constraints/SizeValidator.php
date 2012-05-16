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

class SizeValidator extends ConstraintValidator
{
    /**
     * {@inheritDoc}
     */
    public function validate($value, Constraint $constraint)
    {
        if (null === $constraint->min && null === $constraint->max) {
            throw new ConstraintDefinitionException(
                'Either "min" or "max" must be specified on constraint Size'
            );
        }

        if (null === $constraint->type) {
            $type = $this->guessType($value);
        } else {
            $type = $constraint->type;
        }

        switch ($type) {
            case Size::TYPE_STRING:
                return $this->validateString($value, $constraint);
            case Size::TYPE_COLLECTION:
                return $this->validateCollection($value, $constraint);
            default:
                throw new ConstraintDefinitionException(sprintf(
                    'The "type" on constraint Size must be either "%s" or "%s", "%s" given.',
                    Size::TYPE_STRING,
                    Size::TYPE_COLLECTION,
                    $type
                ));
        }

    }

    private function validateString($value, Constraint $constraint)
    {
        if (null === $value || '' === $value) {
            return;
        }

        if (!is_scalar($value) && !(is_object($value) && method_exists($value, '__toString'))) {
            throw new UnexpectedTypeException($value, 'string, scalar or object with __toString()');
        }

        $value = (string) $value;

        if (function_exists('grapheme_strlen') && 'UTF-8' === $constraint->charset) {
            $length = grapheme_strlen($value);
        } elseif (function_exists('mb_strlen')) {
            $length = mb_strlen($value, $constraint->charset);
        } else {
            $length = strlen($value);
        }

        $this->validateSize(
            $constraint,
            $length,
            Size::TYPE_STRING,
            array('{{ value }}' => $value)
        );
    }

    private function validateCollection($value, Constraint $constraint)
    {
        if (null === $value) {
            return;
        }

        if (!is_array($value) && !$value instanceof \Countable) {
            throw new UnexpectedTypeException($value, 'array or Countable');
        }

        $count = count($value);

        $this->validateSize(
            $constraint,
            $count,
            Size::TYPE_COLLECTION,
            array('{{ count }}' => $count)
        );
    }

    private function validateSize(Constraint $constraint, $size, $type, array $parameters)
    {
        if ($constraint->min == $constraint->max && $size != $constraint->max) {
            $this->context->addViolation(
                $constraint->getExactMessage($type),
                array_merge(array('{{ limit }}' => $constraint->max), $parameters),
                null,
                (int) $constraint->max
            );

            return;
        }

        if (null !== $constraint->max && $size > $constraint->max) {
            $this->context->addViolation(
                $constraint->getMaxMessage($type),
                array_merge(array('{{ limit }}' => $constraint->max), $parameters),
                null,
                (int) $constraint->max
            );

            return;
        }

        if (null !== $constraint->min && $size < $constraint->min) {
            $this->context->addViolation(
                $constraint->getMinMessage($type),
                array_merge(array('{{ limit }}' => $constraint->min), $parameters),
                null,
                (int) $constraint->min
            );
        }
    }

    private function guessType($value)
    {
        if (null === $value || is_scalar($value)) {
            return Size::TYPE_STRING;
        }

        if (is_object($value) && method_exists($value, '__toString')) {
            if ($value instanceof \Countable) {
                throw new \RuntimeException(
                    'The "type" must be specified on constraint Size because the '.
                    'validator is not able to guess it since the value is an object '.
                    'implementing both the __toString() method and the Countable '.
                    'interface.'
                );
            }

            return Size::TYPE_STRING;
        }

        if (is_array($value) || $value instanceof \Countable) {
            return Size::TYPE_COLLECTION;
        }

        throw new UnexpectedTypeException(
            $value, 'scalar, string, array, Countable or object with __toString()'
        );
    }
}
