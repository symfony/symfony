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
use Symfony\Component\Validator\Constraints\Optional;
use Symfony\Component\Validator\Mapping\ValueMetadata;

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

        $group = $this->context->getGroup();
        $map = new ValueMetadata();

        foreach ($constraint->fields as $field => $fieldConstraint) {
            $map->setConstraints($fieldConstraint->constraints);

            if (
                // bug fix issue #2779
                (is_array($value) && array_key_exists($field, $value)) ||
                ($value instanceof \ArrayAccess && $value->offsetExists($field))
            ) {
                foreach ($map->findConstraints($group) as $constr) {
                    $this->context->validateValue($value[$field], $constr, '['.$field.']', $group);
                }
            } elseif ($map->hasConstraints($group) && !$fieldConstraint instanceof Optional && !$constraint->allowMissingFields) {
                $this->context->addViolationAt('['.$field.']', $constraint->missingFieldsMessage, array(
                    '{{ field }}' => $field
                ), null);
            }
        }

        if (!$constraint->allowExtraFields) {
            foreach ($value as $field => $fieldValue) {
                if (!isset($constraint->fields[$field])) {
                    $this->context->addViolationAt('['.$field.']', $constraint->extraFieldsMessage, array(
                        '{{ field }}' => $field
                    ), $fieldValue);
                }
            }
        }
    }
}
