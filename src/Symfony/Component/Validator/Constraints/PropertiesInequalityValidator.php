<?php

namespace Symfony\Component\Validator\Constraints;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Form\Util\PropertyPath;

/**
 * @author Julien Stoeffler <julien@nectalis.com>
 */
class PropertiesInequalityValidator extends ConstraintValidator
{

    public function validate($value, Constraint $constraint) {

        $lessValuePropertyPath = new PropertyPath($constraint->lessValuePropertyPath);
        $lessValue = $lessValuePropertyPath->getValue($value);

        $greaterValuePropertyPath = new PropertyPath($constraint->greaterValuePropertyPath);
        $greaterValue = $greaterValuePropertyPath->getValue($value);
		
		if(is_numeric($lessValue) && is_numeric($greaterValue)) {
			if($lessValue > $greaterValue || ($constraint->strict && ($lessValue == $greaterValue)) )
			$this->context->addViolation($constraint->message, array(
	            '{{ lessProperty }}' => $lessValuePropertyPath,
	            '{{ greaterProperty }}' => $greaterValuePropertyPath,
	            '{{ lessWord }}' => $constraint->lessWord,
	        ));
		} else if($lessValue instanceof \DateTime && $greaterValue instanceof \DateTime) {
	        if($lessValue->format('U') > $greaterValue->format('U') || ($constraint->strict && ($lessValue->format('U') == $greaterValue->format('U'))))
			$this->context->addViolation($constraint->message, array(
	            '{{ lessProperty }}' => $lessValuePropertyPath,
	            '{{ greaterProperty }}' => $greaterValuePropertyPath,
	            '{{ lessWord }}' => $constraint->lessWord,
	        ));
		}
    }

}
