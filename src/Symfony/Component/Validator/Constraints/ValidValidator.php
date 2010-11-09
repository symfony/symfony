<?php

namespace Symfony\Component\Validator\Constraints;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\ConstraintDefinitionException;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

class ValidValidator extends ConstraintValidator
{
    public function isValid($value, Constraint $constraint)
    {
        if ($value === null) {
            return true;
        }

        $walker = $this->context->getGraphWalker();
        $group = $this->context->getGroup();
        $propertyPath = $this->context->getPropertyPath();
        $factory = $this->context->getClassMetadataFactory();

        if (!is_array($value) && !is_object($value)) {
            throw new UnexpectedTypeException($value, 'object or array');
        } else if ($constraint->class && !$value instanceof $constraint->class) {
            $this->setMessage($constraint->message, array('class' => $constraint->class));

            return false;
        } else if (!is_array($value)) {
            $metadata = $factory->getClassMetadata(get_class($value));
            $walker->walkClass($metadata, $value, $group, $propertyPath);
        }

        if (is_array($value) || $value instanceof \Traversable) {
            foreach ($value as $key => $element) {
                $walker->walkConstraint($constraint, $element, $group, $propertyPath.'['.$key.']');
            }
        }

        return true;
    }
}