<?php

namespace Symfony\Components\Validator\Constraints;

use Symfony\Components\Validator\Constraint;
use Symfony\Components\Validator\ConstraintValidator;
use Symfony\Components\Validator\Exception\ConstraintDefinitionException;
use Symfony\Components\Validator\Exception\UnexpectedTypeException;

class CollectionValidator extends ConstraintValidator
{
    public function isValid($value, Constraint $constraint)
    {
        if ($value === null) {
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

        foreach ($constraint->fields as $field => $constraints) {
            if (array_key_exists($field, $value)) {
                // cannot simply cast to array, because then the object is converted to an
                // array instead of wrapped inside
                $constraints = is_array($constraints) ? $constraints : array($constraints);

                foreach ($constraints as $constr) {
                    $walker->walkConstraint($constr, $value[$field], $group, $propertyPath.'['.$field.']');
                }

                unset($extraFields[$field]);
            } else {
                $missingFields[] = $field;
            }
        }

        if (count($extraFields) > 0 && !$constraint->allowExtraFields) {
            $this->setMessage($constraint->extraFieldsMessage, array(
                'fields' => '"'.implode('", "', array_keys($extraFields)).'"'
            ));

            return false;
        }

        if (count($missingFields) > 0 && !$constraint->allowMissingFields) {
            $this->setMessage($constraint->missingFieldsMessage, array(
                'fields' => '"'.implode('", "', $missingFields).'"'
            ));

            return false;
        }

        return true;
    }
}