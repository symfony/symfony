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
use Symfony\Component\Validator\Constraints\Collection\Required;

/**
 * @api
 */
class CollectionValidator extends ConstraintValidator
{
    /**
     * Checks if the passed value is valid.
     *
     * @param mixed      $value      The value that should be validated
     * @param Constraint $constraint The constraint for the validation
     *
     * @return Boolean Whether or not the value is valid
     *
     * @api
     */
    public function isValid($value, Constraint $constraint)
    {
        if (null === $value) {
            return true;
        }

        if (!is_array($value) && !($value instanceof \Traversable && $value instanceof \ArrayAccess)) {
            throw new UnexpectedTypeException($value, 'array or Traversable and ArrayAccess');
        }

        $walker = $this->context->getGraphWalker();
        $group = $this->context->getGroup();
        $propertyPath = $this->context->getPropertyPath();

        $missingFields = array();
        $extraFields = array();

        foreach ($value as $field => $fieldValue) {
            $extraFields[$field] = $fieldValue;
        }

        foreach ($constraint->fields as $field => $fieldConstraint) {
            if (
                // bug fix issue #2779
                (is_array($value) && array_key_exists($field, $value)) ||
                ($value instanceof \ArrayAccess && $value->offsetExists($field))
            ) {
                if ($fieldConstraint instanceof Required || $fieldConstraint instanceof Optional) {
                    $constraints = $fieldConstraint->constraints;
                } else {
                    $constraints = $fieldConstraint;
                }

                // cannot simply cast to array, because then the object is converted to an
                // array instead of wrapped inside
                $constraints = is_array($constraints) ? $constraints : array($constraints);

                foreach ($constraints as $constr) {
                    $walker->walkConstraint($constr, $value[$field], $group, $propertyPath.'['.$field.']');
                }

                unset($extraFields[$field]);
            } elseif (!$fieldConstraint instanceof Optional) {
                $missingFields[] = $field;
            }
        }

        if (count($extraFields) > 0 && !$constraint->allowExtraFields) {
            $this->setMessage($constraint->extraFieldsMessage, array(
                '{{ fields }}' => '"'.implode('", "', array_keys($extraFields)).'"'
            ));

            return false;
        }

        if (count($missingFields) > 0 && !$constraint->allowMissingFields) {
            $this->setMessage($constraint->missingFieldsMessage, array(
                '{{ fields }}' => '"'.implode('", "', $missingFields).'"'
            ));

            return false;
        }

        return true;
    }
}
