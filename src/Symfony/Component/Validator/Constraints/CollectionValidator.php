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

/**
 * @author Bernhard Schussek <bschussek@gmail.com>
 *
 * @api
 */
class CollectionValidator extends ConstraintValidator
{
    /**
     * {@inheritdoc}
     */
    public function validate($value, Constraint $constraint)
    {
        if (null === $value) {
            return;
        }

        if (!is_array($value) && !($value instanceof \Traversable && $value instanceof \ArrayAccess)) {
            throw new UnexpectedTypeException($value, 'array or Traversable and ArrayAccess');
        }

        // We need to keep the initialized context when CollectionValidator
        // calls itself recursively (Collection constraints can be nested).
        // Since the context of the validator is overwritten when initialize()
        // is called for the nested constraint, the outer validator is
        // acting on the wrong context when the nested validation terminates.
        //
        // A better solution - which should be approached in Symfony 3.0 - is to
        // remove the initialize() method and pass the context as last argument
        // to validate() instead.
        $context = $this->context;
        $group = $context->getGroup();

        foreach ($constraint->fields as $field => $fieldConstraint) {
            if (
                // bug fix issue #2779
                (is_array($value) && array_key_exists($field, $value)) ||
                ($value instanceof \ArrayAccess && $value->offsetExists($field))
            ) {
                foreach ($fieldConstraint->constraints as $constr) {
                    $context->validateValue($value[$field], $constr, '['.$field.']', $group);
                }
            } elseif (!$fieldConstraint instanceof Optional && !$constraint->allowMissingFields) {
                $context->addViolationAt('['.$field.']', $constraint->missingFieldsMessage, array(
                    '{{ field }}' => $field
                ), null);
            }
        }

        if (!$constraint->allowExtraFields) {
            foreach ($value as $field => $fieldValue) {
                if (!isset($constraint->fields[$field])) {
                    $context->addViolationAt('['.$field.']', $constraint->extraFieldsMessage, array(
                        '{{ field }}' => $field
                    ), $fieldValue);
                }
            }
        }
    }
}
