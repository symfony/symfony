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
use Symfony\Component\Validator\Constraints\Collection\Optional;

/**
 * @author Bernhard Schussek <bschussek@gmail.com>
 *
 * @api
 */
class CollectionValidator extends ConstraintValidator
{
    /**
     * {@inheritDoc}
     */
    public function validate($value, Constraint $constraint)
    {
        if (null === $value) {
            return;
        }

        if (!is_array($value) && !($value instanceof \Traversable && $value instanceof \ArrayAccess)) {
            throw new UnexpectedTypeException($value, 'array or Traversable and ArrayAccess');
        }

        $walker = $this->context->getGraphWalker();
        $group = $this->context->getGroup();
        $propertyPath = $this->context->getPropertyPath();

        foreach ($constraint->fields as $field => $fieldConstraint) {
            if (
                // bug fix issue #2779
                (is_array($value) && array_key_exists($field, $value)) ||
                ($value instanceof \ArrayAccess && $value->offsetExists($field))
            ) {
                foreach ($fieldConstraint->constraints as $constr) {
                    $walker->walkConstraint($constr, $value[$field], $group, $propertyPath.'['.$field.']');
                }
            } elseif (!$fieldConstraint instanceof Optional && !$constraint->allowMissingFields) {
                $this->context->addViolationAtSubPath('['.$field.']', $constraint->missingFieldsMessage, array(
                    '{{ field }}' => $field
                ), null);
            }
        }

        if (!$constraint->allowExtraFields) {
            foreach ($value as $field => $fieldValue) {
                if (!isset($constraint->fields[$field])) {
                    $this->context->addViolationAtSubPath('['.$field.']', $constraint->extraFieldsMessage, array(
                        '{{ field }}' => $field
                    ), $fieldValue);
                }
            }
        }
    }
}
